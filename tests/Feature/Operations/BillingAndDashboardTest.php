<?php

use App\Domain\Billing\RecordCharge;
use App\Domain\Dispatch\AssignShipment;
use App\Domain\Reporting\DashboardMetrics;
use App\Domain\Reporting\OperationalReport;
use App\Domain\Shipments\TransitionShipment;
use App\Models\Driver;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Tests\Support\OperationsWorld;

test('discounts cannot exceed the subtotal and payment is independent of delivery', function () {
    Date::setTestNow(CarbonImmutable::parse('2026-10-11 16:00:00', 'America/Chicago'));
    $world = OperationsWorld::boot('harbor-bill');
    $shipment = OperationsWorld::shipment($world);
    $charges = app(RecordCharge::class);

    $base = $charges->create($world['context'], $shipment, [
        'description' => 'Linehaul',
        'kind' => 'base',
        'amount_cents' => 10000,
    ]);
    expect(fn () => $charges->create($world['context'], $shipment, [
        'description' => 'Too much',
        'kind' => 'discount',
        'amount_cents' => 10001,
    ]))->toThrow(ValidationException::class);

    $charges->create($world['context'], $shipment, [
        'description' => 'Allowance',
        'kind' => 'discount',
        'amount_cents' => 2000,
    ]);
    $charges->update($world['context'], $base, [
        'billing_status' => 'invoiced',
        'payment_status' => 'paid',
        'invoice_reference' => 'INV-1',
        'external_accounting_ref' => null,
    ]);

    expect($shipment->fresh()->status)->toBe('draft');
    expect($base->fresh()->payment_status)->toBe('paid');

    $report = app(OperationalReport::class)->build(
        $world['context'],
        '2026-10-11',
        '2026-10-11',
    );
    expect($report['charges']['gross_cents'])->toBe(10000);
    expect($report['charges']['discount_cents'])->toBe(2000);
    expect($report['charges']['net_cents'])->toBe(8000);
    expect($report['charges']['paid_cents'])->toBe(10000);
    Date::setTestNow();
});

test('dashboard counts follow the documented definitions', function () {
    Date::setTestNow(CarbonImmutable::parse('2026-10-11 16:00:00', 'America/Chicago'));
    $world = OperationsWorld::boot('harbor-board');
    $ctx = $world['context'];

    $ready = OperationsWorld::ready($world, [
        'pickup_window_start' => '2026-10-11T18:00:00-05:00',
        'pickup_window_end' => '2026-10-11T19:00:00-05:00',
        'delivery_window_start' => '2026-10-12T09:00:00-05:00',
        'delivery_window_end' => '2026-10-12T11:00:00-05:00',
    ]);
    $assigned = OperationsWorld::ready($world, [
        'description' => 'Assigned today.',
        'pickup_window_start' => '2026-10-11T17:00:00-05:00',
        'pickup_window_end' => '2026-10-11T18:00:00-05:00',
        'delivery_window_start' => '2026-10-11T19:00:00-05:00',
        'delivery_window_end' => '2026-10-11T21:00:00-05:00',
    ]);
    $picked = OperationsWorld::ready($world, [
        'description' => 'Already picked up.',
        'pickup_window_start' => '2026-10-11T08:00:00-05:00',
        'pickup_window_end' => '2026-10-11T09:00:00-05:00',
        'delivery_window_start' => '2026-10-11T12:00:00-05:00',
        'delivery_window_end' => '2026-10-11T20:00:00-05:00',
    ]);
    $late = OperationsWorld::ready($world, [
        'description' => 'Running late.',
        'pickup_window_start' => '2026-10-10T08:00:00-05:00',
        'pickup_window_end' => '2026-10-10T09:00:00-05:00',
        'delivery_window_start' => '2026-10-10T12:00:00-05:00',
        'delivery_window_end' => '2026-10-10T14:00:00-05:00',
    ]);
    $done = OperationsWorld::ready($world, [
        'description' => 'Delivered today.',
        'pickup_window_start' => '2026-10-11T07:00:00-05:00',
        'pickup_window_end' => '2026-10-11T08:00:00-05:00',
        'delivery_window_start' => '2026-10-11T09:00:00-05:00',
        'delivery_window_end' => '2026-10-11T18:00:00-05:00',
    ]);
    $failed = OperationsWorld::ready($world, [
        'description' => 'Failed attempt.',
        'pickup_window_start' => '2026-10-11T09:00:00-05:00',
        'pickup_window_end' => '2026-10-11T10:00:00-05:00',
        'delivery_window_start' => '2026-10-11T11:00:00-05:00',
        'delivery_window_end' => '2026-10-11T20:00:00-05:00',
    ]);
    $cancelled = OperationsWorld::shipment($world, [
        'description' => 'Cancelled.',
        'pickup_window_start' => '2026-10-11T06:00:00-05:00',
        'pickup_window_end' => '2026-10-11T07:00:00-05:00',
        'delivery_window_start' => '2026-10-11T08:00:00-05:00',
        'delivery_window_end' => '2026-10-11T09:00:00-05:00',
    ]);
    app(TransitionShipment::class)->handle($ctx, $cancelled, [
        'to' => 'cancelled',
        'reason' => 'Duplicate draft cancelled.',
    ]);

    $fleet = [
        $world['vehicle'],
    ];
    foreach (['HP-22', 'HP-31', 'HP-41', 'HP-02'] as $index => $number) {
        $fleet[] = Vehicle::query()->create([
            'organization_id' => $world['organization']->id,
            'fleet_number' => $number,
            'vehicle_type' => 'cargo_van',
            'availability' => $number === 'HP-02' ? 'unavailable' : 'available',
            'status' => 'active',
            'maintenance_status' => $number === 'HP-02' ? 'out_of_service' : 'ok',
        ]);
    }
    $drivers = [$world['driver']];
    foreach (['Luis Ortega', 'Samir Adeyemi', 'Mateo Ruiz', 'Derek Holm'] as $name) {
        $drivers[] = Driver::query()->create([
            'organization_id' => $world['organization']->id,
            'name' => $name,
            'kind' => 'employee',
            'availability' => $name === 'Derek Holm' ? 'off_duty' : 'available',
            'status' => 'active',
        ]);
    }
    $assign = app(AssignShipment::class);
    $transition = app(TransitionShipment::class);
    $assign->handle($ctx, $assigned, ['driver_id' => $drivers[0]->id, 'vehicle_id' => $fleet[0]->id]);
    $assign->handle($ctx, $picked, ['driver_id' => $drivers[1]->id, 'vehicle_id' => $fleet[1]->id]);
    $transition->handle($ctx, $picked->fresh(), ['to' => 'picked_up']);
    $assign->handle($ctx, $late, ['driver_id' => $drivers[2]->id, 'vehicle_id' => $fleet[2]->id]);
    $late = $transition->handle($ctx, $late->fresh(), ['to' => 'picked_up']);
    $transition->handle($ctx, $late, ['to' => 'in_transit']);
    $assign->handle($ctx, $done, ['driver_id' => $drivers[3]->id, 'vehicle_id' => $fleet[3]->id]);
    $done = $transition->handle($ctx, $done->fresh(), ['to' => 'picked_up']);
    $done = $transition->handle($ctx, $done, ['to' => 'in_transit']);
    $transition->handle($ctx, $done, ['to' => 'delivered', 'recipient_name' => 'June Harlow']);
    $assign->handle($ctx, $failed, ['driver_id' => $drivers[3]->id, 'vehicle_id' => $fleet[3]->id]);
    $failed = $transition->handle($ctx, $failed->fresh(), ['to' => 'picked_up']);
    $failed = $transition->handle($ctx, $failed, ['to' => 'in_transit']);
    $transition->handle($ctx, $failed, [
        'to' => 'failed_delivery',
        'reason' => 'Nobody available to receive.',
    ]);

    $board = app(DashboardMetrics::class)->build($ctx, ['date' => '2026-10-11']);

    expect($board['kpis'])->toMatchArray([
        'awaiting_dispatch' => 1,
        'unassigned' => 1,
        'scheduled_pickups' => 1,
        'active' => 2,
        'delayed' => 1,
        'failed_attempts' => 1,
        'completed' => 1,
        'open_exceptions' => 1,
        'drivers_available' => 4,
        'drivers_total' => 5,
        'vehicles_in_use' => 3,
        'vehicles_available_fleet' => 4,
        'vehicle_utilization' => 0.75,
    ]);
    expect($board['kpis']['deliveries_due'])->toBe(3);

    $this->actingAs($world['owner'])
        ->get(route('dashboard', ['date' => '2026-10-11']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('board.kpis.completed', 1)
            ->where('board.kpis.awaiting_dispatch', 1)
            ->where('queue.per_page', 12));

    Date::setTestNow();
});
