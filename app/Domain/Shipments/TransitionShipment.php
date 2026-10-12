<?php

declare(strict_types=1);

namespace App\Domain\Shipments;

use App\Domain\Dispatch\AssignShipment;
use App\Domain\Identity\OrganizationContext;
use App\Domain\Identity\Permissions;
use App\Domain\Notifications\QueueShipmentNotification;
use App\Domain\Shared\Activity;
use App\Domain\Shared\Clocks;
use App\Domain\Shared\Payloads;
use App\Domain\Tracking\RecordEvent;
use App\Models\Assignment;
use App\Models\DeliveryEvidence;
use App\Models\IdempotencyKey;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\StoredFile;
use App\Models\Trip;
use App\Models\TripStop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class TransitionShipment
{
    public function __construct(
        private RecordEvent $events,
        private Activity $activity,
        private QueueShipmentNotification $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(OrganizationContext $ctx, Shipment $shipment, array $input, ?string $idempotencyKey = null): Shipment
    {
        $to = (string) ($input['to'] ?? '');
        Validator::make($input, [
            'to' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
            'customer_message' => ['nullable', 'string', 'max:240'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'signature_data' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($ctx, $shipment, $input, $to, $idempotencyKey): Shipment {
            if ($idempotencyKey) {
                $hash = Payloads::hash([
                    'shipment' => $shipment->id,
                    'to' => $to,
                    'reason' => $input['reason'] ?? null,
                    'customer_message' => $input['customer_message'] ?? null,
                    'recipient_name' => $input['recipient_name'] ?? null,
                ]);
                $existing = IdempotencyKey::query()
                    ->where('organization_id', $ctx->organization->id)
                    ->where('key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();
                if ($existing) {
                    if ($existing->request_hash !== $hash) {
                        throw ValidationException::withMessages([
                            'idempotency' => 'This idempotency key was already used for a different request.',
                        ]);
                    }

                    $replayId = $existing->response['shipment_id'] ?? null;
                    if (! is_int($replayId)) {
                        throw new RuntimeException('Stored idempotency response is missing a shipment id.');
                    }

                    return Shipment::query()->whereKey($replayId)->firstOrFail();
                }
            }

            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $this->authorize($ctx, $shipment, $to);
            ShipmentState::assert($shipment->status, $to);

            if (in_array($to, ShipmentState::REASON_REQUIRED, true) && blank($input['reason'] ?? null)) {
                throw ValidationException::withMessages([
                    'reason' => 'A reason is required for this transition.',
                ]);
            }

            $evidence = null;
            if ($to === 'delivered' || ! empty($input['recipient_name']) || ! empty($input['signature_data']) || request()->hasFile('photo')) {
                $evidence = $this->storeEvidence($ctx, $shipment, $input, $to === 'failed_delivery' ? 'failed_attempt' : 'delivery');
            }

            if ($to === 'delivered') {
                $this->assertProof($ctx, $shipment, $evidence);
            }

            $now = now();
            $updates = ['status' => $to];
            if ($to === 'picked_up' && $shipment->actual_pickup_at === null) {
                $updates['actual_pickup_at'] = $now;
            }
            if ($to === 'delivered') {
                $updates['actual_delivery_at'] = $now;
                $updates['completed_at'] = $now;
            }
            if ($to === 'returned_to_sender') {
                $updates['completed_at'] = $now;
            }
            if ($to === 'cancelled') {
                $updates['cancelled_at'] = $now;
            }

            if (in_array($to, ['ready_for_dispatch', 'on_hold', 'cancelled', 'returned_to_sender'], true)) {
                $this->releaseAssignment($shipment, $to === 'cancelled' || $to === 'returned_to_sender' ? 'completed' : 'cancelled', (string) ($input['reason'] ?? ''));
                $updates['current_driver_id'] = null;
                $updates['current_vehicle_id'] = null;
                $updates['current_assignment_id'] = null;
                if (in_array($to, ['ready_for_dispatch', 'cancelled', 'returned_to_sender'], true)) {
                    $updates['current_trip_id'] = null;
                }
            }

            if ($to === 'delivered') {
                $this->releaseAssignment($shipment, 'completed', null);
            }

            $shipment->fill($updates)->save();
            $this->syncStops($shipment, $to);

            if (in_array($to, ['failed_delivery', 'failed_pickup', 'damaged_or_missing'], true)) {
                $this->openAutomaticException($ctx, $shipment, $to, (string) $input['reason']);
            }

            $type = $this->eventType($to);
            $event = $this->events->write(
                $ctx,
                $shipment,
                $type,
                $this->publicMessage($to, $input['customer_message'] ?? null),
                $to,
                $input['reason'] ?? null,
            );

            $settings = $ctx->organization->settings()->firstOrFail();
            $template = match ($to) {
                'delivered' => 'delivered',
                'failed_delivery' => 'failed_delivery',
                default => null,
            };
            if ($template) {
                $this->notifications->enqueue($shipment, $settings, $template, $event->id);
            }

            $this->activity->record($ctx, 'shipment.transition', 'shipment', $shipment->id, [
                'to' => $to,
            ]);

            if ($idempotencyKey) {
                IdempotencyKey::query()->create([
                    'organization_id' => $ctx->organization->id,
                    'key' => $idempotencyKey,
                    'request_hash' => Payloads::hash([
                        'shipment' => $shipment->id,
                        'to' => $to,
                        'reason' => $input['reason'] ?? null,
                        'customer_message' => $input['customer_message'] ?? null,
                        'recipient_name' => $input['recipient_name'] ?? null,
                    ]),
                    'response' => ['shipment_id' => $shipment->id, 'status' => $to],
                    'created_at' => $now,
                ]);
            }

            return $shipment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function reschedule(OrganizationContext $ctx, Shipment $shipment, array $input): Shipment
    {
        abort_unless($ctx->allows('shipments.write') || $ctx->allows('dispatch.manage'), 403);
        $data = Validator::make($input, [
            'pickup_window_start' => ['required', 'date'],
            'pickup_window_end' => ['required', 'date', 'after:pickup_window_start'],
            'delivery_window_start' => ['required', 'date'],
            'delivery_window_end' => ['required', 'date', 'after:delivery_window_start'],
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        if (in_array($shipment->status, ShipmentState::TERMINAL, true)) {
            throw ValidationException::withMessages([
                'status' => 'Completed shipments cannot be rescheduled.',
            ]);
        }

        return DB::transaction(function () use ($ctx, $shipment, $data): Shipment {
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $previousDelivery = $shipment->delivery_window_end;
            $timezone = $ctx->organization->timezone ?: 'UTC';
            $pickupStart = Clocks::at($data['pickup_window_start'], $timezone);
            $pickupEnd = Clocks::at($data['pickup_window_end'], $timezone);
            $deliveryStart = Clocks::at($data['delivery_window_start'], $timezone);
            $deliveryEnd = Clocks::at($data['delivery_window_end'], $timezone);
            $shipment->fill([
                'pickup_window_start' => $pickupStart,
                'pickup_window_end' => $pickupEnd,
                'delivery_window_start' => $deliveryStart,
                'delivery_window_end' => $deliveryEnd,
                'planned_pickup_at' => $pickupStart,
                'planned_delivery_at' => $deliveryEnd,
            ])->save();

            $assignment = Assignment::query()
                ->whereKey($shipment->current_assignment_id)
                ->where('status', 'active')
                ->first();
            if ($assignment) {
                $assignment->fill([
                    'window_start' => $pickupStart,
                    'window_end' => $deliveryEnd,
                ])->save();
                app(AssignShipment::class)->assertNoOverlap(
                    $ctx,
                    (int) $assignment->driver_id,
                    (int) $assignment->vehicle_id,
                    $assignment->window_start,
                    $assignment->window_end,
                    $assignment->id,
                );
            }

            $event = $this->events->write($ctx, $shipment, 'rescheduled', 'The schedule was updated.', $shipment->status, $data['reason']);
            if ($shipment->delivery_window_end->greaterThan($previousDelivery)) {
                $settings = $ctx->organization->settings()->firstOrFail();
                $this->notifications->enqueue($shipment, $settings, 'delay', $event->id);
            }
            $this->activity->record($ctx, 'shipment.rescheduled', 'shipment', $shipment->id);

            return $shipment->refresh();
        });
    }

    /**
     * @return list<string>
     */
    public function allowedTargets(OrganizationContext $ctx, Shipment $shipment): array
    {
        $targets = [];
        foreach (ShipmentState::TRANSITIONS[$shipment->status] ?? [] as $to) {
            try {
                $this->authorize($ctx, $shipment, $to);
            } catch (AuthorizationException) {
                continue;
            }
            $targets[] = $to;
        }

        return $targets;
    }

    private function authorize(OrganizationContext $ctx, Shipment $shipment, string $to): void
    {
        $needed = Permissions::STATUS_PERMISSION[$to] ?? null;
        if ($needed === null) {
            throw ValidationException::withMessages(['status' => 'Unknown status.']);
        }

        $isAssignedDriver = $ctx->driverId && (int) $shipment->current_driver_id === (int) $ctx->driverId;
        $driverAllowed = $isAssignedDriver
            && $ctx->allows('shipments.driver_action')
            && in_array($to, Permissions::DRIVER_ACTIONS, true);

        if ($ctx->driverId && ! $ctx->allows('dispatch.manage') && ! $isAssignedDriver) {
            throw new AuthorizationException('Drivers can update only their assigned shipments.');
        }

        if (! $ctx->allows($needed) && ! $driverAllowed) {
            throw new AuthorizationException('You cannot make this status change.');
        }
    }

    private function assertProof(OrganizationContext $ctx, Shipment $shipment, ?DeliveryEvidence $evidence): void
    {
        $settings = $ctx->organization->settings()->firstOrFail();
        $evidence ??= $shipment->evidences()->where('type', 'delivery')->latest('id')->first();
        if (! $evidence) {
            throw ValidationException::withMessages([
                'evidence' => 'Delivery evidence is required before a shipment can be delivered.',
            ]);
        }
        if ($settings->require_recipient_name && blank($evidence->recipient_name)) {
            throw ValidationException::withMessages([
                'recipient_name' => 'A recipient name is required.',
            ]);
        }
        $kinds = $evidence->files()->pluck('kind')->all();
        if ($settings->require_pod_photo && ! in_array('photo', $kinds, true)) {
            throw ValidationException::withMessages(['photo' => 'A delivery photo is required.']);
        }
        if ($settings->require_pod_signature && ! in_array('signature', $kinds, true)) {
            throw ValidationException::withMessages([
                'signature_data' => 'A signature image is required. This is a captured image, not a certified electronic signature.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function storeEvidence(OrganizationContext $ctx, Shipment $shipment, array $input, string $type): ?DeliveryEvidence
    {
        $hasFile = request()->hasFile('photo');
        $hasSignature = filled($input['signature_data'] ?? null);
        $hasName = filled($input['recipient_name'] ?? null);
        $hasNotes = filled($input['notes'] ?? null);
        if (! $hasFile && ! $hasSignature && ! $hasName && ! $hasNotes) {
            return null;
        }

        abort_unless($ctx->allows('evidence.write') || $ctx->allows('shipments.driver_action') || $ctx->allows('shipments.close'), 403);

        $evidence = DeliveryEvidence::query()->create([
            'organization_id' => $ctx->organization->id,
            'shipment_id' => $shipment->id,
            'type' => $type,
            'recipient_name' => $input['recipient_name'] ?? null,
            'notes' => $input['notes'] ?? null,
            'captured_at' => now(),
            'actor_id' => $ctx->user->id,
            'amends_id' => $input['amends_id'] ?? null,
        ]);

        if ($hasFile) {
            $file = request()->file('photo');
            Validator::make(['photo' => $file], [
                'photo' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.config('fleetpilot.evidence_max_kilobytes')],
            ])->validate();
            $this->storeBinary($ctx, $evidence->id, $file->getContent(), $file->getClientOriginalName(), (string) $file->getMimeType(), 'photo');
        }

        if ($hasSignature) {
            $binary = $this->decodeSignature((string) $input['signature_data']);
            $this->storeBinary($ctx, $evidence->id, $binary, 'signature.png', 'image/png', 'signature');
        }

        return $evidence;
    }

    private function decodeSignature(string $data): string
    {
        if (! str_starts_with($data, 'data:image/png;base64,')) {
            throw ValidationException::withMessages([
                'signature_data' => 'Signature must be a PNG image.',
            ]);
        }
        $binary = base64_decode(substr($data, strlen('data:image/png;base64,')), true);
        if ($binary === false || strlen($binary) > 2_000_000 || ! str_starts_with($binary, "\x89PNG")) {
            throw ValidationException::withMessages([
                'signature_data' => 'Signature image is invalid.',
            ]);
        }

        return $binary;
    }

    private function storeBinary(OrganizationContext $ctx, int $evidenceId, string $binary, string $name, string $mime, string $kind): void
    {
        $path = $ctx->organization->id.'/'.bin2hex(random_bytes(16));
        Storage::disk('local')->put('evidence/'.$path, $binary);
        StoredFile::query()->create([
            'organization_id' => $ctx->organization->id,
            'disk' => 'local',
            'path' => 'evidence/'.$path,
            'original_name' => $name,
            'mime_type' => $mime,
            'size_bytes' => strlen($binary),
            'checksum_sha256' => hash('sha256', $binary),
            'kind' => $kind,
            'evidence_id' => $evidenceId,
            'created_by' => $ctx->user->id,
        ]);
    }

    private function releaseAssignment(Shipment $shipment, string $status, ?string $reason): void
    {
        if (! $shipment->current_assignment_id) {
            return;
        }
        Assignment::query()->whereKey($shipment->current_assignment_id)->where('status', 'active')->update([
            'status' => $status,
            'active_shipment_key' => null,
            'active_trip_key' => null,
            'cancelled_at' => $status === 'cancelled' ? now() : null,
            'cancel_reason' => $reason,
        ]);
    }

    private function syncStops(Shipment $shipment, string $to): void
    {
        if (! $shipment->current_trip_id) {
            return;
        }
        $now = now();
        if ($to === 'picked_up') {
            TripStop::query()->where('trip_id', $shipment->current_trip_id)->where('shipment_id', $shipment->id)->where('stop_type', 'pickup')->update([
                'status' => 'completed',
                'actual_departure' => $now,
            ]);
        }
        if ($to === 'delivered') {
            TripStop::query()->where('trip_id', $shipment->current_trip_id)->where('shipment_id', $shipment->id)->where('stop_type', 'delivery')->update([
                'status' => 'completed',
                'actual_departure' => $now,
            ]);
        }
        if ($to === 'failed_pickup') {
            TripStop::query()->where('trip_id', $shipment->current_trip_id)->where('shipment_id', $shipment->id)->where('stop_type', 'pickup')->update(['status' => 'failed']);
        }
        if ($to === 'failed_delivery') {
            TripStop::query()->where('trip_id', $shipment->current_trip_id)->where('shipment_id', $shipment->id)->where('stop_type', 'delivery')->update(['status' => 'failed']);
        }

        $stops = TripStop::query()->where('trip_id', $shipment->current_trip_id)->get();
        $terminal = $stops->every(fn (TripStop $stop): bool => in_array($stop->status, ['completed', 'skipped'], true));
        $started = $stops->contains(fn (TripStop $stop): bool => $stop->status !== 'pending');
        Trip::query()->whereKey($shipment->current_trip_id)->update([
            'status' => $terminal ? 'completed' : ($started ? 'in_progress' : 'planned'),
        ]);
    }

    private function openAutomaticException(OrganizationContext $ctx, Shipment $shipment, string $to, string $reason): void
    {
        $category = match ($to) {
            'failed_pickup' => 'delayed_pickup',
            'damaged_or_missing' => 'damaged_goods',
            default => 'failed_delivery',
        };
        $exception = ShipmentException::query()->create([
            'organization_id' => $ctx->organization->id,
            'shipment_id' => $shipment->id,
            'trip_id' => $shipment->current_trip_id,
            'category' => $category,
            'severity' => 'high',
            'status' => 'open',
            'description' => $reason,
            'reported_by' => $ctx->user->id,
        ]);
        $shipment->forceFill(['current_exception_id' => $exception->id])->save();
        $event = $this->events->write($ctx, $shipment, 'exception_recorded', 'An exception was recorded.', $shipment->status, $reason);
        $settings = $ctx->organization->settings()->firstOrFail();
        $this->notifications->enqueue($shipment, $settings, 'exception_opened', $event->id);
    }

    private function eventType(string $to): string
    {
        return match ($to) {
            'ready_for_dispatch' => 'ready_for_dispatch',
            'picked_up' => 'pickup_completed',
            'in_transit' => 'in_transit',
            'delivered' => 'delivered',
            'failed_pickup' => 'pickup_failed',
            'failed_delivery' => 'delivery_failed',
            'cancelled' => 'cancelled',
            'on_hold' => 'on_hold',
            'damaged_or_missing' => 'exception_recorded',
            'returned_to_sender' => 'returned',
            default => 'status_changed',
        };
    }

    private function publicMessage(string $to, ?string $customerMessage): string
    {
        if (filled($customerMessage)) {
            return $customerMessage;
        }

        return match ($to) {
            'ready_for_dispatch' => 'Shipment is ready for dispatch.',
            'picked_up' => 'Pickup completed.',
            'in_transit' => 'Shipment is in transit.',
            'delivered' => 'Shipment was delivered.',
            'failed_pickup' => 'Pickup was not completed.',
            'failed_delivery' => 'Delivery was not completed.',
            'cancelled' => 'Shipment was cancelled.',
            'on_hold' => 'Shipment is on hold.',
            'damaged_or_missing' => 'A damage or shortage exception was recorded.',
            'returned_to_sender' => 'Shipment is returning to the sender.',
            default => 'Shipment was updated.',
        };
    }
}
