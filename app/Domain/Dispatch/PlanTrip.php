<?php

declare(strict_types=1);

namespace App\Domain\Dispatch;

use App\Domain\Identity\OrganizationContext;
use App\Domain\Notifications\QueueShipmentNotification;
use App\Domain\Shared\Activity;
use App\Domain\Shared\Clocks;
use App\Domain\Shared\Sequences;
use App\Domain\Tracking\RecordEvent;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\TripStop;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PlanTrip
{
    public function __construct(
        private Sequences $sequences,
        private AssignShipment $assignments,
        private RecordEvent $events,
        private Activity $activity,
        private QueueShipmentNotification $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(OrganizationContext $ctx, array $input): Trip
    {
        abort_unless($ctx->allows('trips.write') && $ctx->allows('dispatch.manage'), 403);
        $data = Validator::make($input, [
            'driver_id' => ['required', 'integer'],
            'vehicle_id' => ['required', 'integer'],
            'shipment_ids' => ['required', 'array', 'min:1', 'max:40'],
            'shipment_ids.*' => ['integer', 'distinct'],
            'planned_departure' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        return DB::transaction(function () use ($ctx, $data): Trip {
            $rawIds = $data['shipment_ids'] ?? null;
            if (! is_array($rawIds)) {
                throw ValidationException::withMessages(['shipment_ids' => 'Shipments are required.']);
            }
            $driver = Driver::query()->where('organization_id', $ctx->organization->id)->whereKey($data['driver_id'])->lockForUpdate()->firstOrFail();
            $vehicle = Vehicle::query()->where('organization_id', $ctx->organization->id)->whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();
            $ids = collect($rawIds)->map(fn (mixed $id): int => (int) $id)->sort()->values();
            foreach ($ids as $id) {
                Shipment::query()->whereKey($id)->lockForUpdate()->first();
            }
            $shipments = Shipment::query()
                ->where('organization_id', $ctx->organization->id)
                ->whereIn('id', $rawIds)
                ->get()
                ->keyBy('id');
            if ($shipments->count() !== count($rawIds)) {
                throw ValidationException::withMessages(['shipment_ids' => 'One or more shipments are not in this organization.']);
            }

            $ordered = collect($rawIds)->map(function (mixed $id) use ($shipments): Shipment {
                $shipment = $shipments->get((int) $id);
                if (! $shipment instanceof Shipment) {
                    throw ValidationException::withMessages(['shipment_ids' => 'One or more shipments are not in this organization.']);
                }

                return $shipment;
            });
            foreach ($ordered as $shipment) {
                if ($shipment->status !== 'ready_for_dispatch' || $shipment->current_assignment_id) {
                    throw ValidationException::withMessages([
                        'shipment_ids' => $shipment->shipment_number.' is not waiting for dispatch.',
                    ]);
                }
            }

            $start = $ordered->min('pickup_window_start');
            $end = $ordered->max('delivery_window_end');
            $this->assignments->assertNoOverlap($ctx, $driver->id, $vehicle->id, $start, $end);

            $trip = Trip::query()->create([
                'organization_id' => $ctx->organization->id,
                'trip_number' => $this->sequences->next($ctx->organization->id, 'trip', 'TRP'),
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'status' => 'planned',
                'planned_departure' => isset($data['planned_departure'])
                    ? Clocks::at((string) $data['planned_departure'], $ctx->organization->timezone ?: 'UTC')
                    : $start,
                'notes' => $data['notes'] ?? null,
                'created_by' => $ctx->user->id,
            ]);

            $assignment = Assignment::query()->create([
                'organization_id' => $ctx->organization->id,
                'trip_id' => $trip->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'status' => 'active',
                'window_start' => $start,
                'window_end' => $end,
                'notes' => $data['notes'] ?? null,
                'created_by' => $ctx->user->id,
            ]);

            $sequence = 1;
            $settings = $ctx->organization->settings()->firstOrFail();
            foreach ($ordered as $shipment) {
                foreach (['pickup', 'delivery'] as $type) {
                    TripStop::query()->create([
                        'trip_id' => $trip->id,
                        'shipment_id' => $shipment->id,
                        'stop_type' => $type,
                        'sequence' => $sequence,
                        'status' => 'pending',
                        'planned_arrival' => $type === 'pickup' ? $shipment->pickup_window_start : $shipment->delivery_window_start,
                    ]);
                    $sequence++;
                }
                $shipment->fill([
                    'status' => 'assigned',
                    'current_driver_id' => $driver->id,
                    'current_vehicle_id' => $vehicle->id,
                    'current_trip_id' => $trip->id,
                    'current_assignment_id' => $assignment->id,
                ])->save();
                $event = $this->events->write($ctx, $shipment, 'dispatch_assigned', 'Shipment was added to a trip.', 'assigned');
                $fresh = $shipment->fresh(['customer.contacts', 'currentDriver']);
                $this->notifications->enqueue($fresh, $settings, 'dispatch_confirmation', $event->id);
                $this->notifications->enqueue($fresh, $settings, 'driver_assignment', $event->id);
            }

            $this->activity->record($ctx, 'trip.created', 'trip', $trip->id);

            return $trip->load('stops');
        });
    }

    /**
     * @param  list<int>  $stopIds
     */
    public function reorder(OrganizationContext $ctx, Trip $trip, array $stopIds): Trip
    {
        abort_unless($ctx->allows('trips.write'), 403);

        return DB::transaction(function () use ($ctx, $trip, $stopIds): Trip {
            $stops = TripStop::query()->where('trip_id', $trip->id)->orderBy('sequence')->get();
            $existing = $stops->pluck('id')->sort()->values()->all();
            $incoming = collect($stopIds)->map(fn ($id): int => (int) $id)->sort()->values()->all();
            if ($existing !== $incoming) {
                throw ValidationException::withMessages([
                    'stops' => 'Provide every stop on the trip, in the new order.',
                ]);
            }
            foreach ($stopIds as $index => $id) {
                TripStop::query()->whereKey($id)->update(['sequence' => 1000 + $index + 1]);
            }
            foreach ($stopIds as $index => $id) {
                TripStop::query()->whereKey($id)->update(['sequence' => $index + 1]);
            }
            $this->activity->record($ctx, 'trip.reordered', 'trip', $trip->id);

            return $trip->fresh('stops');
        });
    }
}
