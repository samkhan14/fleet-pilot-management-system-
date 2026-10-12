<?php

use App\Domain\Billing\RecordCharge;
use App\Domain\Dispatch\AssignShipment;
use App\Models\NotificationJob;
use App\Models\PermissionGrant;
use App\Models\Shipment;
use App\Models\TrackingToken;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Support\OperationsWorld;

test('a new member can sign in with the password they were given', function () {
    $world = OperationsWorld::boot('harbor-member');

    $this->actingAs($world['owner'])
        ->post(route('organization.members.store'), [
            'name' => 'Chris Dalton',
            'email' => 'chris.dalton@harborpine.example',
            'password' => 'Warehouse!2026',
            'role' => 'warehouse',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'chris.dalton@harborpine.example')->first();
    expect($user)->not->toBeNull();
    expect(Hash::check('Warehouse!2026', (string) $user?->password))->toBeTrue();
});

test('role grants can be narrowed from the organization screen', function () {
    $world = OperationsWorld::boot('harbor-grants');

    $this->actingAs($world['owner'])
        ->put(route('organization.permissions.update'), [
            'role' => 'dispatcher',
            'permissions' => ['dashboard.read', 'shipments.read'],
        ])
        ->assertRedirect();

    expect(PermissionGrant::query()->where('role', 'dispatcher')->pluck('permission')->all())
        ->toEqualCanonicalizing(['dashboard.read', 'shipments.read']);
});

test('shipment desk actions cover schedule, release, exceptions, documents, and tracking links', function () {
    Storage::fake('local');
    $world = OperationsWorld::boot('harbor-desk');
    $ready = OperationsWorld::ready($world);
    $assign = app(AssignShipment::class);
    $assign->handle($world['context'], $ready, [
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
    ]);

    $this->actingAs($world['owner'])
        ->post(route('shipments.unassign', $ready), ['reason' => 'Driver called out.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    expect($ready->fresh()->status)->toBe('ready_for_dispatch');

    $this->actingAs($world['owner'])
        ->post(route('shipments.reschedule', $ready), [
            'pickup_window_start' => '2026-10-13T09:00',
            'pickup_window_end' => '2026-10-13T11:00',
            'delivery_window_start' => '2026-10-13T13:00',
            'delivery_window_end' => '2026-10-13T15:00',
            'reason' => 'Dock moved the appointment.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($world['owner'])
        ->post(route('shipments.exceptions.store', $ready), [
            'category' => 'delayed_pickup',
            'severity' => 'high',
            'description' => 'The dock is closed until noon.',
        ])
        ->assertRedirect();
    expect($ready->fresh()->current_exception_id)->not->toBeNull();

    $this->actingAs($world['owner'])
        ->post(route('documents.store'), [
            'shipment_id' => $ready->id,
            'title' => 'Bill of lading',
            'doc_type' => 'bill_of_lading',
            'visibility' => 'customer_visible',
            'file' => UploadedFile::fake()->create('bol.pdf', 20, 'application/pdf'),
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($world['owner'])
        ->post(route('shipments.token', $ready))
        ->assertRedirect()
        ->assertSessionHas('tracking_token');

    $raw = (string) session('tracking_token');
    $rowId = TrackingToken::query()->where('token_hash', hash('sha256', $raw))->value('id');

    $this->actingAs($world['owner'])
        ->post(route('shipments.token.revoke', ['shipment' => $ready->id, 'token' => $rowId]))
        ->assertRedirect();

    $this->get('/track/'.$raw)->assertGone();
});

test('fleet, billing, customer, and notification screens accept their updates', function () {
    $world = OperationsWorld::boot('harbor-books');
    $shipment = OperationsWorld::shipment($world);
    $charge = app(RecordCharge::class)->create($world['context'], $shipment, [
        'description' => 'Linehaul',
        'kind' => 'base',
        'amount_cents' => 18000,
    ]);
    NotificationJob::query()->create([
        'organization_id' => $world['organization']->id,
        'shipment_id' => $shipment->id,
        'dedupe_key' => 'desk-1',
        'template' => 'dispatch_confirmation',
        'channel' => 'mail',
        'recipient' => 'dispatch@northwind.example',
        'payload' => ['subject' => 'Assigned', 'body' => 'A driver is assigned.'],
        'status' => 'sent',
        'attempts' => 1,
    ]);

    $this->actingAs($world['owner'])
        ->patch(route('drivers.update', $world['driver']), [
            'availability' => 'off_duty',
            'status' => 'active',
        ])
        ->assertRedirect();
    expect($world['driver']->fresh()->availability)->toBe('off_duty');

    $this->actingAs($world['owner'])
        ->patch(route('vehicles.update', $world['vehicle']), [
            'availability' => 'unavailable',
            'status' => 'active',
            'maintenance_status' => 'due',
        ])
        ->assertRedirect();
    expect($world['vehicle']->fresh()->maintenance_status)->toBe('due');

    $this->actingAs($world['owner'])
        ->patch(route('billing.update', $charge), [
            'billing_status' => 'invoiced',
            'payment_status' => 'paid',
            'invoice_reference' => 'INV-3001',
        ])
        ->assertRedirect();
    expect($charge->fresh()->payment_status)->toBe('paid');

    $this->actingAs($world['owner'])
        ->patch(route('customers.update', $world['customer']), [
            'name' => 'Northwind Foods',
            'email' => 'desk@northwind.example',
        ])
        ->assertRedirect();
    expect($world['customer']->fresh()->name)->toBe('Northwind Foods');

    $this->actingAs($world['owner'])
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('notifications/index')->has('jobs.data', 1));
});

test('the shipment page only offers statuses the role can apply', function () {
    $world = OperationsWorld::boot('harbor-next');
    $shipment = OperationsWorld::shipment($world);

    $this->actingAs($world['owner'])
        ->get(route('shipments.show', $shipment))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('next_statuses.0.value', 'ready_for_dispatch')
            ->has('next_statuses', 2));

    expect(Shipment::query()->find($shipment->id))->not->toBeNull();
});
