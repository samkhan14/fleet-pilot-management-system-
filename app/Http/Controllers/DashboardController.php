<?php

namespace App\Http\Controllers;

use App\Domain\Identity\OrganizationContext;
use App\Domain\Reporting\DashboardMetrics;
use App\Http\Controllers\Concerns\UsesOrganization;
use App\Http\Presenters\OperationsPresenter;
use App\Models\Customer;
use App\Models\Driver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use UsesOrganization;

    public function __invoke(Request $request, DashboardMetrics $metrics, OperationsPresenter $presenter): Response
    {
        if (! app()->bound(OrganizationContext::class)) {
            return Inertia::render('dashboard', [
                'board' => null,
                'queue' => null,
            ]);
        }

        $ctx = $this->authorizePermission('dashboard.read');
        $filters = $request->only(['date', 'driver_id', 'customer_id', 'service_area', 'status']);
        $queue = $ctx->shipments()->with([
            'customer:id,name,company_name',
            'pickupAddress',
            'deliveryAddress',
            'currentDriver:id,name',
            'currentVehicle:id,fleet_number',
        ])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $queue->where('status', $request->string('status'));
        }
        if ($request->filled('driver_id')) {
            $queue->where('current_driver_id', $request->integer('driver_id'));
        }
        if ($request->filled('customer_id')) {
            $queue->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('service_area')) {
            $queue->where('service_area', $request->string('service_area'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $queue->where(function ($query) use ($term): void {
                $query->where('shipment_number', 'like', $term)
                    ->orWhere('customer_reference', 'like', $term);
            });
        }

        return Inertia::render('dashboard', [
            'board' => $metrics->build($ctx, $filters),
            'filters' => $filters,
            'queue' => $queue->paginate(12)->withQueryString()->through(
                fn ($shipment) => $presenter->shipmentList($shipment, $ctx)
            ),
            'drivers' => Driver::query()->where('organization_id', $ctx->organization->id)->orderBy('name')->get(['id', 'name']),
            'customers' => $ctx->role === 'customer' ? [] : Customer::query()->where('organization_id', $ctx->organization->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
