<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesOrganization;
use App\Http\Presenters\OperationsPresenter;
use App\Models\Driver;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FleetController extends Controller
{
    use UsesOrganization;

    public function drivers(OperationsPresenter $presenter): Response
    {
        $ctx = $this->authorizePermission('drivers.read');
        $drivers = Driver::query()->where('organization_id', $ctx->organization->id)->orderBy('name')->get();

        return Inertia::render('drivers/index', [
            'drivers' => $drivers->map(fn (Driver $driver) => $presenter->driver($driver, $ctx)),
        ]);
    }

    public function storeDriver(Request $request): RedirectResponse
    {
        $ctx = $this->authorizePermission('drivers.write');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email'],
            'kind' => ['required', Rule::in(['employee', 'contractor'])],
            'availability' => ['required', Rule::in(['available', 'unavailable', 'off_duty'])],
            'license_number' => ['nullable', 'string', 'max:80'],
            'license_expires_on' => ['nullable', 'date'],
        ]);
        Driver::query()->create([
            'organization_id' => $ctx->organization->id,
            'status' => 'active',
            ...$data,
        ]);

        return back();
    }

    public function updateDriver(Request $request, int $driver): RedirectResponse
    {
        $ctx = $this->authorizePermission('drivers.write');
        $record = Driver::query()->where('organization_id', $ctx->organization->id)->findOrFail($driver);
        $data = $request->validate([
            'availability' => ['required', Rule::in(['available', 'unavailable', 'off_duty'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'license_number' => ['nullable', 'string', 'max:80'],
            'license_expires_on' => ['nullable', 'date'],
        ]);
        $record->fill($data)->save();

        return back();
    }

    public function vehicles(OperationsPresenter $presenter): Response
    {
        $ctx = $this->authorizePermission('vehicles.read');
        $vehicles = Vehicle::query()->where('organization_id', $ctx->organization->id)->orderBy('fleet_number')->get();

        return Inertia::render('vehicles/index', [
            'vehicles' => $vehicles->map(fn (Vehicle $vehicle) => $presenter->vehicle($vehicle)),
        ]);
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        $ctx = $this->authorizePermission('vehicles.write');
        $data = $request->validate([
            'fleet_number' => ['required', 'string', 'max:40'],
            'registration' => ['nullable', 'string', 'max:40'],
            'vehicle_type' => ['required', Rule::in(['van', 'box_truck', 'tractor', 'pickup', 'cargo_bike', 'other'])],
            'capacity_kg' => ['nullable', 'numeric', 'min:0'],
            'availability' => ['required', Rule::in(['available', 'unavailable'])],
        ]);
        Vehicle::query()->create([
            'organization_id' => $ctx->organization->id,
            'status' => 'active',
            'maintenance_status' => 'ok',
            ...$data,
        ]);

        return back();
    }

    public function updateVehicle(Request $request, int $vehicle): RedirectResponse
    {
        $ctx = $this->authorizePermission('vehicles.write');
        $record = Vehicle::query()->where('organization_id', $ctx->organization->id)->findOrFail($vehicle);
        $data = $request->validate([
            'availability' => ['required', Rule::in(['available', 'unavailable'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'maintenance_status' => ['required', Rule::in(['ok', 'due', 'out_of_service'])],
        ]);
        $record->fill($data)->save();

        return back();
    }
}
