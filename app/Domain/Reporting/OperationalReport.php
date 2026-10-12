<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Identity\OrganizationContext;
use App\Models\Charge;
use App\Models\Shipment;
use App\Models\ShipmentException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class OperationalReport
{
    /**
     * Period bounds are organization-local midnights converted to UTC.
     * Cancelled shipments are excluded from on-time and failed-delivery rates.
     * Revenue is the sum of non-void charges created in the period. A delivery
     * does not change payment status.
     *
     * @return array<string, mixed>
     */
    public function build(OrganizationContext $ctx, string $from, string $to): array
    {
        abort_unless($ctx->allows('reports.read') || $ctx->allows('finance.read'), 403);
        $timezone = $ctx->organization->timezone ?: 'UTC';
        $start = CarbonImmutable::parse($from, $timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $timezone)->addDay()->startOfDay()->utc();
        $orgId = $ctx->organization->id;

        $created = Shipment::query()
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byService = Shipment::query()
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('service_type, count(*) as total')
            ->groupBy('service_type')
            ->pluck('total', 'service_type');

        $delivered = Shipment::query()
            ->where('organization_id', $orgId)
            ->where('status', 'delivered')
            ->where('actual_delivery_at', '>=', $start)
            ->where('actual_delivery_at', '<', $end);
        $deliveredCount = (clone $delivered)->count();
        $onTimeDelivery = (clone $delivered)->whereColumn('actual_delivery_at', '<=', 'delivery_window_end')->count();

        $picked = Shipment::query()
            ->where('organization_id', $orgId)
            ->whereNotNull('actual_pickup_at')
            ->where('status', '!=', 'cancelled')
            ->where('actual_pickup_at', '>=', $start)
            ->where('actual_pickup_at', '<', $end);
        $pickedCount = (clone $picked)->count();
        $onTimePickup = (clone $picked)->whereColumn('actual_pickup_at', '<=', 'pickup_window_end')->count();

        $failedShipments = DB::table('tracking_events')
            ->join('shipments', 'shipments.id', '=', 'tracking_events.shipment_id')
            ->where('tracking_events.organization_id', $orgId)
            ->where('tracking_events.type', 'delivery_failed')
            ->where('shipments.status', '!=', 'cancelled')
            ->where('tracking_events.occurred_at', '>=', $start)
            ->where('tracking_events.occurred_at', '<', $end)
            ->distinct('tracking_events.shipment_id')
            ->count('tracking_events.shipment_id');
        $attemptDenom = $deliveredCount + $failedShipments;

        $duration = Shipment::query()
            ->where('organization_id', $orgId)
            ->where('status', 'delivered')
            ->where('actual_delivery_at', '>=', $start)
            ->where('actual_delivery_at', '<', $end)
            ->whereNotNull('actual_pickup_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, actual_pickup_at, actual_delivery_at)) as minutes')
            ->value('minutes');

        $exceptions = ShipmentException::query()
            ->where('organization_id', $orgId)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $charges = Charge::query()
            ->where('organization_id', $orgId)
            ->where('billing_status', '!=', 'void')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->get(['kind', 'amount_cents', 'payment_status']);
        $gross = (int) $charges->whereIn('kind', ['base', 'accessorial'])->sum('amount_cents');
        $discounts = (int) $charges->where('kind', 'discount')->sum('amount_cents');
        $paid = (int) $charges->where('payment_status', 'paid')->whereIn('kind', ['base', 'accessorial'])->sum('amount_cents');

        $byCustomer = Shipment::query()
            ->where('shipments.organization_id', $orgId)
            ->where('shipments.created_at', '>=', $start)
            ->where('shipments.created_at', '<', $end)
            ->where('shipments.status', '!=', 'cancelled')
            ->join('customers', 'customers.id', '=', 'shipments.customer_id')
            ->selectRaw('customers.name as name, count(*) as total')
            ->groupBy('customers.name')
            ->orderByDesc('total')
            ->limit(12)
            ->get();

        return [
            'from' => $from,
            'to' => $to,
            'timezone' => $timezone,
            'shipments_by_status' => $created,
            'shipments_by_service' => $byService,
            'on_time_delivery_rate' => $deliveredCount === 0 ? null : round($onTimeDelivery / $deliveredCount, 3),
            'on_time_delivery_count' => $onTimeDelivery,
            'delivered_count' => $deliveredCount,
            'on_time_pickup_rate' => $pickedCount === 0 ? null : round($onTimePickup / $pickedCount, 3),
            'on_time_pickup_count' => $onTimePickup,
            'picked_count' => $pickedCount,
            'failed_delivery_rate' => $attemptDenom === 0 ? null : round($failedShipments / $attemptDenom, 3),
            'failed_delivery_shipments' => $failedShipments,
            'average_transit_minutes' => $duration === null ? null : (int) round((float) $duration),
            'exceptions_by_category' => $exceptions,
            'charges' => [
                'net_cents' => $gross - $discounts,
                'gross_cents' => $gross,
                'discount_cents' => $discounts,
                'paid_cents' => $paid,
                'currency' => $ctx->organization->currency,
            ],
            'by_customer' => $byCustomer,
            'definitions' => [
                'on_time_delivery' => 'Delivered shipments in the period whose actual delivery time is at or before the delivery window end, divided by delivered shipments in the period. Cancelled shipments are excluded.',
                'failed_delivery' => 'Distinct non-cancelled shipments with a delivery-failed event in the period, divided by that count plus deliveries completed in the period.',
                'revenue' => 'Non-void base and accessorial charges minus discounts, created in the period. Paid is a separate sum and is never set by delivery.',
            ],
        ];
    }
}
