<?php

use App\Domain\Dispatch\AssignShipment;
use App\Domain\Dispatch\PlanTrip;
use App\Domain\Shipments\TransitionShipment;
use App\Models\Assignment;
use App\Models\Driver;
use App\Models\TripStop;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\Support\OperationsWorld;

test('dispatch rejects an overlapping driver and a second active assignment', function () {
    $world = OperationsWorld::boot('harbor-dispatch');
    $first = OperationsWorld::ready($world);
    $second = OperationsWorld::ready($world, ['description' => 'Second load.']);
    $assign = app(AssignShipment::class);

    $assign->handle($world['context'], $first, [
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
    ]);

    expect(fn () => $assign->handle($world['context'], $second, [
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
    ]))->toThrow(ValidationException::class);

    $otherVehicle = Vehicle::query()->create([
        'organization_id' => $world['organization']->id,
        'fleet_number' => 'HP-99',
        'vehicle_type' => 'cargo_van',
        'availability' => 'available',
        'status' => 'active',
        'maintenance_status' => 'ok',
    ]);
    $otherDriver = Driver::query()->create([
        'organization_id' => $world['organization']->id,
        'name' => 'Luis Ortega',
        'kind' => 'contractor',
        'availability' => 'available',
        'status' => 'active',
    ]);
    $assign->handle($world['context'], $first->fresh(), [
        'driver_id' => $otherDriver->id,
        'vehicle_id' => $otherVehicle->id,
    ]);

    expect(Assignment::query()->where('shipment_id', $first->id)->where('status', 'active')->count())->toBe(1);
    expect($first->fresh()->current_driver_id)->toBe($otherDriver->id);

    expect(fn () => Assignment::query()->create([
        'organization_id' => $world['organization']->id,
        'shipment_id' => $first->id,
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
        'status' => 'active',
        'window_start' => $first->pickup_window_start,
        'window_end' => $first->delivery_window_end,
    ]))->toThrow(QueryException::class);
});

test('an out of service vehicle cannot be assigned', function () {
    $world = OperationsWorld::boot('harbor-oos');
    $shipment = OperationsWorld::ready($world);
    $world['vehicle']->forceFill(['maintenance_status' => 'out_of_service'])->save();

    expect(fn () => app(AssignShipment::class)->handle($world['context'], $shipment, [
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
    ]))->toThrow(ValidationException::class);
});

test('trip stop order is manual and a trip shipment cannot be assigned alone', function () {
    $world = OperationsWorld::boot('harbor-trip');
    $first = OperationsWorld::ready($world, [
        'pickup_window_start' => '2026-10-13T13:00:00-05:00',
        'pickup_window_end' => '2026-10-13T14:00:00-05:00',
        'delivery_window_start' => '2026-10-13T18:00:00-05:00',
        'delivery_window_end' => '2026-10-13T19:00:00-05:00',
    ]);
    $second = OperationsWorld::ready($world, [
        'description' => 'Second stop.',
        'pickup_window_start' => '2026-10-13T15:00:00-05:00',
        'pickup_window_end' => '2026-10-13T16:00:00-05:00',
        'delivery_window_start' => '2026-10-13T20:00:00-05:00',
        'delivery_window_end' => '2026-10-13T21:00:00-05:00',
    ]);

    $trip = app(PlanTrip::class)->create($world['context'], [
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
        'shipment_ids' => [$first->id, $second->id],
    ]);
    $ordered = $trip->stops->sortBy('sequence')->pluck('id')->all();
    $reversed = array_reverse($ordered);
    app(PlanTrip::class)->reorder($world['context'], $trip, $reversed);

    expect(TripStop::query()->where('trip_id', $trip->id)->orderBy('sequence')->pluck('id')->all())->toBe($reversed);
    expect($first->fresh()->status)->toBe('assigned');

    expect(fn () => app(AssignShipment::class)->handle($world['context'], $first->fresh(), [
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
    ]))->toThrow(ValidationException::class);
});

test('a driver can advance only the assigned shipment', function () {
    $world = OperationsWorld::boot('harbor-driver');
    $mine = OperationsWorld::ready($world);
    $other = OperationsWorld::ready($world, ['description' => 'Someone else.']);
    $otherDriver = Driver::query()->create([
        'organization_id' => $world['organization']->id,
        'name' => 'Luis Ortega',
        'kind' => 'employee',
        'availability' => 'available',
        'status' => 'active',
    ]);
    $otherVehicle = Vehicle::query()->create([
        'organization_id' => $world['organization']->id,
        'fleet_number' => 'HP-22',
        'vehicle_type' => 'box_truck',
        'availability' => 'available',
        'status' => 'active',
        'maintenance_status' => 'ok',
    ]);
    app(AssignShipment::class)->handle($world['context'], $mine, [
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
    ]);
    app(AssignShipment::class)->handle($world['context'], $other, [
        'driver_id' => $otherDriver->id,
        'vehicle_id' => $otherVehicle->id,
        'notes' => 'Separate window is still the same clock, so give this one a later day.',
    ]);

    $driverUser = User::factory()->create(['email' => 'driver@example.test']);
    $world['provision']->addMember($world['organization'], $driverUser, 'driver', null, $world['driver']->id);

    $this->actingAs($driverUser)
        ->post(route('drive.act', $mine), ['to' => 'picked_up'])
        ->assertRedirect();
    expect($mine->fresh()->status)->toBe('picked_up');

    $this->actingAs($driverUser)
        ->post(route('drive.act', $other))
        ->assertNotFound();

    $this->actingAs($driverUser)
        ->get(route('shipments.show', $other))
        ->assertNotFound();
});

test('delivery requires the configured proof and does not need a dispatcher', function () {
    $world = OperationsWorld::boot('harbor-pod');
    $shipment = OperationsWorld::ready($world);
    app(AssignShipment::class)->handle($world['context'], $shipment, [
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
    ]);
    $shipment = app(TransitionShipment::class)->handle($world['context'], $shipment->fresh(), ['to' => 'picked_up']);
    $shipment = app(TransitionShipment::class)->handle($world['context'], $shipment, ['to' => 'in_transit']);

    expect(fn () => app(TransitionShipment::class)->handle($world['context'], $shipment, ['to' => 'delivered']))
        ->toThrow(ValidationException::class);

    $delivered = app(TransitionShipment::class)->handle($world['context'], $shipment->fresh(), [
        'to' => 'delivered',
        'recipient_name' => 'June Harlow',
    ]);
    expect($delivered->status)->toBe('delivered');
    expect($delivered->actual_delivery_at)->not->toBeNull();
    expect($delivered->charges()->count())->toBe(0);
});
