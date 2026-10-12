<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesOrganization;
use App\Http\Presenters\OperationsPresenter;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DispatchController extends Controller
{
    use UsesOrganization;

    public function index(Request $request, OperationsPresenter $presenter): Response
    {
        $ctx = $this->authorizePermission('dispatch.manage');
        $query = Shipment::query()
            ->where('organization_id', $ctx->organization->id)
            ->whereIn('status', ['ready_for_dispatch', 'assigned', 'failed_pickup', 'failed_delivery', 'on_hold'])
            ->with(['customer:id,name', 'pickupAddress', 'deliveryAddress', 'currentDriver:id,name', 'currentVehicle:id,fleet_number'])
            ->orderBy('pickup_window_start');

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority'));
        }
        if ($request->filled('service_area')) {
            $query->where('service_area', $request->string('service_area'));
        }
        if ($request->filled('date')) {
            $query->whereDate('pickup_window_start', $request->string('date'));
        }

        return Inertia::render('dispatch/index', [
            'shipments' => $query->paginate(30)->withQueryString()->through(fn ($shipment) => $presenter->shipmentList($shipment, $ctx)),
            'drivers' => Driver::query()->where('organization_id', $ctx->organization->id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'availability', 'kind']),
            'vehicles' => Vehicle::query()->where('organization_id', $ctx->organization->id)->where('status', 'active')->orderBy('fleet_number')->get(['id', 'fleet_number', 'vehicle_type', 'availability', 'maintenance_status', 'capacity_kg']),
            'filters' => $request->only(['priority', 'service_area', 'date']),
            'manual_dispatch' => true,
        ]);
    }
}
