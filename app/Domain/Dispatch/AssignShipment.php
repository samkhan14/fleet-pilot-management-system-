<?php

declare(strict_types=1);

namespace App\Domain\Dispatch;

use App\Domain\Identity\OrganizationContext;
use App\Domain\Notifications\QueueShipmentNotification;
use App\Domain\Shared\Activity;
use App\Domain\Shared\Payloads;
use App\Domain\Tracking\RecordEvent;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\IdempotencyKey;
use App\Models\Shipment;
use App\Models\Vehicle;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AssignShipment
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
        abort_unless($ctx->allows('dispatch.manage'), 403);
        $data = Validator::make($input, [
            'driver_id' => ['required', 'integer'],
            'vehicle_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($ctx, $shipment, $data, $idempotencyKey): Shipment {
            if ($replay = $this->replay($ctx, $shipment->id, $data, $idempotencyKey)) {
                return $replay;
            }

            $driver = Driver::query()->where('organization_id', $ctx->organization->id)->whereKey($data['driver_id'])->lockForUpdate()->first();
            $vehicle = Vehicle::query()->where('organization_id', $ctx->organization->id)->whereKey($data['vehicle_id'])->lockForUpdate()->first();
            $shipment = Shipment::query()->where('organization_id', $ctx->organization->id)->whereKey($shipment->id)->lockForUpdate()->firstOrFail();

            if (! $driver || ! $vehicle) {
                throw ValidationException::withMessages(['driver_id' => 'Choose a driver and vehicle in this organization.']);
            }
            $this->assertResources($driver, $vehicle);
            if (! in_array($shipment->status, ['ready_for_dispatch', 'failed_pickup', 'assigned'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Only shipments that are ready, previously failed at pickup, or already assigned can be dispatched.',
                ]);
            }
            if ($shipment->current_trip_id) {
                throw ValidationException::withMessages([
                    'trip' => 'This shipment is on a trip. Change the trip instead of assigning it alone.',
                ]);
            }

            $this->assertNoOverlap($ctx, $driver->id, $vehicle->id, $shipment->pickup_window_start, $shipment->delivery_window_end, $shipment->current_assignment_id);

            if ($shipment->current_assignment_id) {
                Assignment::query()->whereKey($shipment->current_assignment_id)->where('status', 'active')->update([
                    'status' => 'cancelled',
                    'active_shipment_key' => null,
                    'active_trip_key' => null,
                    'cancelled_at' => now(),
                    'cancel_reason' => 'Reassigned',
                ]);
            }

            try {
                $assignment = Assignment::query()->create([
                    'organization_id' => $ctx->organization->id,
                    'shipment_id' => $shipment->id,
                    'driver_id' => $driver->id,
                    'vehicle_id' => $vehicle->id,
                    'status' => 'active',
                    'window_start' => $shipment->pickup_window_start,
                    'window_end' => $shipment->delivery_window_end,
                    'notes' => $data['notes'] ?? null,
                    'created_by' => $ctx->user->id,
                ]);
            } catch (QueryException $exception) {
                throw ValidationException::withMessages([
                    'driver_id' => 'Another dispatch saved an assignment for this shipment first.',
                ]);
            }

            $shipment->fill([
                'status' => 'assigned',
                'current_driver_id' => $driver->id,
                'current_vehicle_id' => $vehicle->id,
                'current_assignment_id' => $assignment->id,
            ])->save();

            $type = $shipment->wasChanged('status') ? 'dispatch_assigned' : 'reassigned';
            $event = $this->events->write(
                $ctx,
                $shipment,
                $type,
                $type === 'reassigned' ? 'Dispatch assignment was updated.' : 'Shipment was dispatched.',
                'assigned',
            );
            $settings = $ctx->organization->settings()->firstOrFail();
            $this->notifications->enqueue($shipment->fresh(['customer.contacts', 'currentDriver']), $settings, 'dispatch_confirmation', $event->id);
            $this->notifications->enqueue($shipment, $settings, 'driver_assignment', $event->id);
            $this->activity->record($ctx, 'shipment.assigned', 'shipment', $shipment->id, [
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
            ]);
            $this->remember($ctx, $data, $idempotencyKey, $shipment);

            return $shipment->refresh();
        });
    }

    public function unassign(OrganizationContext $ctx, Shipment $shipment, string $reason): Shipment
    {
        abort_unless($ctx->allows('dispatch.manage'), 403);
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'A reason is required to cancel an assignment.']);
        }

        return DB::transaction(function () use ($ctx, $shipment, $reason): Shipment {
            $shipment = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            if ($shipment->status !== 'assigned') {
                throw ValidationException::withMessages(['status' => 'Only an assigned shipment can be unassigned.']);
            }
            if ($shipment->current_assignment_id) {
                Assignment::query()->whereKey($shipment->current_assignment_id)->update([
                    'status' => 'cancelled',
                    'active_shipment_key' => null,
                    'active_trip_key' => null,
                    'cancelled_at' => now(),
                    'cancel_reason' => $reason,
                ]);
            }
            $shipment->fill([
                'status' => 'ready_for_dispatch',
                'current_driver_id' => null,
                'current_vehicle_id' => null,
                'current_assignment_id' => null,
                'current_trip_id' => null,
            ])->save();
            $this->events->write($ctx, $shipment, 'ready_for_dispatch', 'Assignment was cancelled.', 'ready_for_dispatch', $reason);
            $this->activity->record($ctx, 'shipment.unassigned', 'shipment', $shipment->id);

            return $shipment->refresh();
        });
    }

    public function assertNoOverlap(
        OrganizationContext $ctx,
        int $driverId,
        int $vehicleId,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $ignoreAssignmentId = null,
    ): void {
        $settings = $ctx->organization->settings;
        if ($settings?->allow_overlapping_assignments) {
            return;
        }

        $overlap = function (string $column, int $id) use ($start, $end, $ignoreAssignmentId): bool {
            return Assignment::query()
                ->where($column, $id)
                ->where('status', 'active')
                ->when($ignoreAssignmentId, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
                ->where('window_start', '<', $end)
                ->where('window_end', '>', $start)
                ->exists();
        };

        if ($overlap('driver_id', $driverId)) {
            throw ValidationException::withMessages([
                'driver_id' => 'That driver already has an overlapping assignment.',
            ]);
        }
        if ($overlap('vehicle_id', $vehicleId)) {
            throw ValidationException::withMessages([
                'vehicle_id' => 'That vehicle already has an overlapping assignment.',
            ]);
        }
    }

    private function assertResources(Driver $driver, Vehicle $vehicle): void
    {
        if ($driver->status !== 'active' || $driver->availability !== 'available') {
            throw ValidationException::withMessages([
                'driver_id' => 'Inactive or unavailable drivers cannot be assigned.',
            ]);
        }
        if ($vehicle->status !== 'active' || $vehicle->availability !== 'available' || $vehicle->maintenance_status === 'out_of_service') {
            throw ValidationException::withMessages([
                'vehicle_id' => 'Inactive, unavailable, or out-of-service vehicles cannot be assigned.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function replay(OrganizationContext $ctx, int $shipmentId, array $data, ?string $key): ?Shipment
    {
        if (! $key) {
            return null;
        }
        $existing = IdempotencyKey::query()
            ->where('organization_id', $ctx->organization->id)
            ->where('key', $key)
            ->lockForUpdate()
            ->first();
        if (! $existing) {
            return null;
        }
        $hash = $this->hash($shipmentId, $data);
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function remember(OrganizationContext $ctx, array $data, ?string $key, Shipment $shipment): void
    {
        if (! $key) {
            return;
        }
        IdempotencyKey::query()->create([
            'organization_id' => $ctx->organization->id,
            'key' => $key,
            'request_hash' => $this->hash($shipment->id, $data),
            'response' => ['shipment_id' => $shipment->id],
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hash(int $shipmentId, array $data): string
    {
        return Payloads::hash([
            'shipment' => $shipmentId,
            'driver_id' => $data['driver_id'],
            'vehicle_id' => $data['vehicle_id'],
            'notes' => $data['notes'] ?? null,
        ]);
    }
}
