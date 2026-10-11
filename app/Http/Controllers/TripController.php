<?php

namespace App\Http\Controllers;

use App\Domain\Dispatch\PlanTrip;
use App\Http\Controllers\Concerns\UsesOrganization;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\Trip;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TripController extends Controller
{
    use UsesOrganization;

    public function index(): Response
    {
        $ctx = $this->authorizePermission('trips.read');
        $trips = Trip::query()->where('organization_id', $ctx->organization->id)->with(['driver:id,name', 'vehicle:id,fleet_number'])->withCount('stops')->latest()->paginate(20);

        return Inertia::render('trips/index', [
            'trips' => $trips,
            'drivers' => $ctx->allows('trips.write') ? Driver::query()->where('organization_id', $ctx->organization->id)->where('status', 'active')->get(['id', 'name', 'availability']) : [],
            'vehicles' => $ctx->allows('trips.write') ? Vehicle::query()->where('organization_id', $ctx->organization->id)->where('status', 'active')->get(['id', 'fleet_number', 'availability', 'maintenance_status']) : [],
            'ready' => $ctx->allows('trips.write') ? Shipment::query()->where('organization_id', $ctx->organization->id)->where('status', 'ready_for_dispatch')->orderBy('pickup_window_start')->get(['id', 'shipment_number', 'pickup_window_start']) : [],
        ]);
    }

    public function store(Request $request, PlanTrip $trips): RedirectResponse
    {
        $ctx = $this->context();
        $trip = $trips->create($ctx, $request->all());

        return redirect()->route('trips.show', $trip);
    }

    public function show(int $trip): Response
    {
        $ctx = $this->authorizePermission('trips.read');
        $record = Trip::query()->where('organization_id', $ctx->organization->id)->with(['driver', 'vehicle', 'stops.shipment.pickupAddress', 'stops.shipment.deliveryAddress'])->findOrFail($trip);

        return Inertia::render('trips/show', ['trip' => $record]);
    }

    public function reorder(Request $request, int $trip, PlanTrip $trips): RedirectResponse
    {
        $ctx = $this->context();
        $record = Trip::query()->where('organization_id', $ctx->organization->id)->findOrFail($trip);
        $data = $request->validate(['stops' => ['required', 'array']]);
        $trips->reorder($ctx, $record, $data['stops']);

        return back();
    }
}
