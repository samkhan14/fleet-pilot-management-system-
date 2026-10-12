<?php

use App\Domain\Shipments\TransitionShipment;
use App\Models\CustomerAddress;
use App\Models\Shipment;
use App\Models\ShipmentAddress;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Tests\Support\OperationsWorld;

test('shipment creation snapshots the address and rejects a skipped status', function () {
    $world = OperationsWorld::boot();
    $shipment = OperationsWorld::shipment($world);

    expect($shipment->shipment_number)->toStartWith('SHP-');
    expect($shipment->status)->toBe('draft');
    expect(ShipmentAddress::query()->where('shipment_id', $shipment->id)->where('kind', 'pickup')->value('line1'))->toBe('418 Harbor Ave');

    CustomerAddress::query()->where('customer_id', $world['customer']->id)->update(['line1' => '1 New Dock']);
    expect(ShipmentAddress::query()->where('shipment_id', $shipment->id)->where('kind', 'pickup')->value('line1'))->toBe('418 Harbor Ave');

    expect(fn () => app(TransitionShipment::class)->handle($world['context'], $shipment, ['to' => 'delivered', 'recipient_name' => 'June Harlow']))
        ->toThrow(ValidationException::class);

    $ready = app(TransitionShipment::class)->handle($world['context'], $shipment->fresh(), ['to' => 'ready_for_dispatch']);
    expect($ready->status)->toBe('ready_for_dispatch');
});

test('a reason is required before a shipment can be cancelled', function () {
    $world = OperationsWorld::boot('harbor-cancel');
    $shipment = OperationsWorld::shipment($world);

    $this->actingAs($world['owner'])
        ->from(route('shipments.show', $shipment))
        ->post(route('shipments.transition', $shipment), ['to' => 'cancelled'])
        ->assertRedirect()
        ->assertSessionHasErrors('reason');

    expect($shipment->fresh()->status)->toBe('draft');
});

test('the same idempotency key does not apply a transition twice', function () {
    $world = OperationsWorld::boot('harbor-idem');
    $shipment = OperationsWorld::shipment($world);

    $this->actingAs($world['owner'])
        ->withHeader('Idempotency-Key', 'cancel-once')
        ->post(route('shipments.transition', $shipment), [
            'to' => 'cancelled',
            'reason' => 'Customer called and cancelled.',
        ])
        ->assertRedirect();

    $this->actingAs($world['owner'])
        ->withHeader('Idempotency-Key', 'cancel-once')
        ->post(route('shipments.transition', $shipment), [
            'to' => 'cancelled',
            'reason' => 'Customer called and cancelled.',
        ])
        ->assertRedirect();

    expect($shipment->fresh()->status)->toBe('cancelled');
    expect($shipment->events()->where('type', 'cancelled')->count())->toBe(1);

    $this->actingAs($world['owner'])
        ->from(route('shipments.show', $shipment))
        ->withHeader('Idempotency-Key', 'cancel-once')
        ->post(route('shipments.transition', $shipment), [
            'to' => 'cancelled',
            'reason' => 'A different reason.',
        ])
        ->assertSessionHasErrors('idempotency');
});

test('shipment numbers stay unique inside an organization', function () {
    $world = OperationsWorld::boot('harbor-numbers');
    $first = OperationsWorld::shipment($world);
    $second = OperationsWorld::shipment($world, ['description' => 'Second crate.']);

    expect($first->shipment_number)->not->toBe($second->shipment_number);
    expect(Shipment::query()->where('organization_id', $world['organization']->id)->count())->toBe(2);
});

test('shipment lists are paginated', function () {
    $world = OperationsWorld::boot('harbor-pages');
    foreach (range(1, 26) as $index) {
        OperationsWorld::shipment($world, [
            'description' => 'Carton '.$index,
            'customer_reference' => 'REF-'.$index,
        ]);
    }

    $this->actingAs($world['owner'])
        ->get(route('shipments.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('shipments/index')
            ->where('shipments.total', 26)
            ->has('shipments.data', 25));

    $this->actingAs($world['owner'])
        ->get(route('shipments.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page->has('shipments.data', 1));
});

test('a user without a membership still reaches the empty dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('board', null));
});
