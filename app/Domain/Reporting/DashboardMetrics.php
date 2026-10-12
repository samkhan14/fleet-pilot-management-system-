<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Identity\OrganizationContext;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class DashboardMetrics
{
    /**
     * Snapshot metrics describe current shipment state. Completed deliveries and
     * failed attempts are counted from timestamps and events on the selected
     * organization-local day. Cancelled shipments are excluded from active,
     * delayed, and due counts.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(OrganizationContext $ctx, array $filters): array
    {
        $timezone = $ctx->organization->timezone ?: 'UTC';
        $day = isset($filters['date']) && $filters['date'] !== ''
            ? CarbonImmutable::parse((string) $filters['date'], $timezone)
            : CarbonImmutable::now($timezone);
        $start = $day->startOfDay()->utc();
        $end = $day->addDay()->startOfDay()->utc();
        $now = CarbonImmutable::now();

        $base = $ctx->shipments();
        $this->applyScope($base, $filters);

        $awaiting = (clone $base)->where('status', 'ready_for_dispatch')->count();
        $unassigned = (clone $base)->where('status', 'ready_for_dispatch')->whereNull('current_assignment_id')->count();
        $scheduled = (clone $base)->where('status', 'assigned')
            ->where('pickup_window_start', '>=', $start)
            ->where('pickup_window_start', '<', $end)
            ->count();
        $active = (clone $base)->whereIn('status', ['picked_up', 'in_transit'])->count();
        $due = (clone $base)->whereNotIn('status', ['delivered', 'cancelled', 'returned_to_sender'])
            ->where('delivery_window_start', '<', $end)
            ->where('delivery_window_end', '>=', $start)
            ->count();
        $delayed = (clone $base)->whereNotIn('status', ['delivered', 'cancelled', 'returned_to_sender', 'draft'])
            ->where(function (Builder $query) use ($now): void {
                $query->where(function (Builder $pickup) use ($now): void {
                    $pickup->whereIn('status', ['ready_for_dispatch', 'assigned', 'failed_pickup', 'on_hold'])
                        ->where('pickup_window_end', '<', $now);
                })->orWhere(function (Builder $delivery) use ($now): void {
                    $delivery->whereIn('status', ['picked_up', 'in_transit', 'failed_delivery', 'damaged_or_missing'])
                        ->where('delivery_window_end', '<', $now);
                });
            })->count();
        $completed = (clone $base)->where('status', 'delivered')
            ->where('actual_delivery_at', '>=', $start)
            ->where('actual_delivery_at', '<', $end)
            ->count();

        $failedQuery = TrackingEvent::query()
            ->where('organization_id', $ctx->organization->id)
            ->where('type', 'delivery_failed')
            ->where('occurred_at', '>=', $start)
            ->where('occurred_at', '<', $end);
        if ($ctx->role === 'customer') {
            $failedQuery->whereIn('shipment_id', (clone $base)->select('shipments.id'));
        }
        if ($ctx->role === 'driver') {
            $failedQuery->whereIn('shipment_id', (clone $base)->select('shipments.id'));
        }
        $failedAttempts = $failedQuery->count();

        $openExceptions = ShipmentException::query()
            ->where('organization_id', $ctx->organization->id)
            ->whereIn('status', ['open', 'in_progress']);
        if ($ctx->role === 'customer' || $ctx->role === 'driver') {
            $openExceptions->whereIn('shipment_id', (clone $base)->select('shipments.id'));
        }
        $openExceptionCount = $openExceptions->count();

        $drivers = Driver::query()->where('organization_id', $ctx->organization->id)->where('status', 'active')->get(['id', 'name', 'availability']);
        $workload = Shipment::query()
            ->where('organization_id', $ctx->organization->id)
            ->whereIn('status', ['assigned', 'picked_up', 'in_transit'])
            ->whereNotNull('current_driver_id')
            ->selectRaw('current_driver_id, count(*) as total')
            ->groupBy('current_driver_id')
            ->pluck('total', 'current_driver_id');

        $fleet = Vehicle::query()
            ->where('organization_id', $ctx->organization->id)
            ->where('status', 'active')
            ->where('maintenance_status', '!=', 'out_of_service')
            ->count();
        $inUse = Shipment::query()
            ->where('organization_id', $ctx->organization->id)
            ->whereIn('status', ['assigned', 'picked_up', 'in_transit'])
            ->whereNotNull('current_vehicle_id')
            ->distinct('current_vehicle_id')
            ->count('current_vehicle_id');

        return [
            'date' => $day->toDateString(),
            'timezone' => $timezone,
            'definitions' => $this->definitions(),
            'kpis' => [
                'awaiting_dispatch' => $awaiting,
                'scheduled_pickups' => $scheduled,
                'active' => $active,
                'deliveries_due' => $due,
                'delayed' => $delayed,
                'failed_attempts' => $failedAttempts,
                'unassigned' => $unassigned,
                'completed' => $completed,
                'open_exceptions' => $openExceptionCount,
                'drivers_available' => $drivers->where('availability', 'available')->count(),
                'drivers_total' => $drivers->count(),
                'vehicle_utilization' => $fleet === 0 ? 0 : round($inUse / $fleet, 3),
                'vehicles_in_use' => $inUse,
                'vehicles_available_fleet' => $fleet,
            ],
            'workload' => $drivers->map(fn (Driver $driver): array => [
                'id' => $driver->id,
                'name' => $driver->name,
                'availability' => $driver->availability,
                'shipments' => (int) ($workload[$driver->id] ?? 0),
            ])->values(),
        ];
    }

    /**
     * @param  Builder<Shipment>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyScope(Builder $query, array $filters): void
    {
        if (! empty($filters['driver_id'])) {
            $query->where('current_driver_id', (int) $filters['driver_id']);
        }
        if (! empty($filters['customer_id'])) {
            $query->where('customer_id', (int) $filters['customer_id']);
        }
        if (! empty($filters['service_area'])) {
            $query->where('service_area', $filters['service_area']);
        }
    }

    /**
     * @return array<string, string>
     */
    private function definitions(): array
    {
        return [
            'awaiting_dispatch' => 'Current shipments in Ready for Dispatch.',
            'unassigned' => 'Ready for Dispatch shipments with no active assignment. This matches awaiting dispatch when assignment rules hold.',
            'scheduled_pickups' => 'Assigned shipments whose pickup window starts on the selected organization-local day.',
            'active' => 'Current shipments that are Picked Up or In Transit.',
            'deliveries_due' => 'Non-terminal shipments, excluding cancelled and returned, whose delivery window overlaps the selected day.',
            'delayed' => 'Non-terminal, non-draft shipments whose pickup window has ended before pickup, or whose delivery window has ended while still out for delivery. Cancelled shipments are excluded.',
            'failed_attempts' => 'Delivery-failed tracking events recorded on the selected day. This counts attempts, not the current status.',
            'completed' => 'Shipments delivered on the selected day, using the server delivery timestamp.',
            'open_exceptions' => 'Exceptions currently Open or In Progress.',
            'drivers_available' => 'Active drivers marked Available. This is not filtered by the selected day.',
            'vehicle_utilization' => 'Distinct vehicles on an assigned, picked-up, or in-transit shipment, divided by active vehicles that are not out of service.',
        ];
    }
}
