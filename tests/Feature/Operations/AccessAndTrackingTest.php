<?php

use App\Domain\Dispatch\AssignShipment;
use App\Domain\Notifications\ProcessNotificationJobs;
use App\Domain\Notifications\QueueShipmentNotification;
use App\Domain\Shipments\TransitionShipment;
use App\Domain\Tracking\IssueTrackingToken;
use App\Models\Customer;
use App\Models\Document;
use App\Models\NotificationJob;
use App\Models\StoredFile;
use App\Models\TrackingToken;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\Support\OperationsWorld;

test('organizations and roles cannot see each others shipments', function () {
    $first = OperationsWorld::boot('harbor-a');
    $second = OperationsWorld::boot('harbor-b');
    $shipment = OperationsWorld::shipment($first, ['internal_notes' => 'Dock code 441.']);
    $customerUser = User::factory()->create(['email' => 'andrea@example.test']);
    $first['provision']->addMember($first['organization'], $customerUser, 'customer', $first['customer']->id);
    $otherCustomer = Customer::query()->create([
        'organization_id' => $first['organization']->id,
        'name' => 'Cedar & Rye Market',
        'email' => 'orders@cedarrye.example',
    ]);
    $hidden = OperationsWorld::shipment($first, [
        'customer_id' => $otherCustomer->id,
        'description' => 'Hidden from Northwind.',
    ]);

    $this->actingAs($second['owner'])->get(route('shipments.show', $shipment))->assertNotFound();
    $this->actingAs($customerUser)->get(route('shipments.show', $hidden))->assertNotFound();
    $this->actingAs($customerUser)
        ->get(route('shipments.show', $shipment))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('shipment.internal_notes', null)
            ->where('shipment.driver_phone', null)
            ->has('charges', 0));

    $finance = User::factory()->create(['email' => 'finance@example.test']);
    $first['provision']->addMember($first['organization'], $finance, 'finance');
    $this->actingAs($finance)
        ->post(route('shipments.assign', $shipment), ['driver_id' => $first['driver']->id, 'vehicle_id' => $first['vehicle']->id])
        ->assertForbidden();
});

test('public tracking hides internal details and rejects expired or revoked links', function () {
    $world = OperationsWorld::boot('harbor-track');
    $shipment = OperationsWorld::ready($world);
    app(AssignShipment::class)->handle($world['context'], $shipment, [
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
    ]);
    $shipment = app(TransitionShipment::class)->handle($world['context'], $shipment->fresh(), [
        'to' => 'picked_up',
    ]);
    $shipment = app(TransitionShipment::class)->handle($world['context'], $shipment, [
        'to' => 'failed_delivery',
        'reason' => 'Receiver was not on site. Internal only.',
        'customer_message' => 'The delivery attempt did not succeed.',
    ]);
    $issued = app(IssueTrackingToken::class)->issue($world['context'], $shipment);

    $this->get(route('track.show', $issued['token']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('track')
            ->where('shipment.shipment_number', $shipment->shipment_number)
            ->where('shipment.pickup_city', 'Green Bay')
            ->missing('shipment.pickup'))
        ->assertDontSee('418 Harbor Ave', false)
        ->assertDontSee('Do not share this note', false)
        ->assertDontSee('555-0172', false)
        ->assertDontSee('Internal only', false)
        ->assertSee('The delivery attempt did not succeed.', false);

    $token = TrackingToken::query()->where('shipment_id', $shipment->id)->firstOrFail();
    $token->forceFill(['expires_at' => now()->subMinute()])->save();
    $this->get(route('track.show', $issued['token']))->assertStatus(410);

    $again = app(IssueTrackingToken::class)->issue($world['context'], $shipment->fresh());
    $live = TrackingToken::query()->where('token_hash', hash('sha256', $again['token']))->firstOrFail();
    app(IssueTrackingToken::class)->revoke($world['context'], $live);
    $this->get(route('track.show', $again['token']))->assertStatus(410);
});

test('unknown tracking links are rate limited', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.44']);

    foreach (range(1, 30) as $attempt) {
        $this->get('/track/not-a-real-token-value-xx')->assertNotFound();
    }

    $this->get('/track/not-a-real-token-value-xx')->assertStatus(429);
});

test('a failed notification does not change the shipment and retries stay deduped', function () {
    $world = OperationsWorld::boot('harbor-notify');
    $shipment = OperationsWorld::ready($world);
    app(AssignShipment::class)->handle($world['context'], $shipment, [
        'driver_id' => $world['driver']->id,
        'vehicle_id' => $world['vehicle']->id,
    ]);
    $shipment = app(TransitionShipment::class)->handle($world['context'], $shipment->fresh(), ['to' => 'picked_up']);
    $shipment = app(TransitionShipment::class)->handle($world['context'], $shipment, ['to' => 'in_transit']);
    $shipment = app(TransitionShipment::class)->handle($world['context'], $shipment, [
        'to' => 'delivered',
        'recipient_name' => 'June Harlow',
    ]);

    $job = NotificationJob::query()->where('shipment_id', $shipment->id)->where('template', 'delivered')->firstOrFail();
    expect($job->status)->toBe('pending');
    $before = NotificationJob::query()->where('shipment_id', $shipment->id)->count();
    app(QueueShipmentNotification::class)->enqueue(
        $shipment->fresh(['customer.contacts', 'currentDriver']),
        $world['organization']->settings()->firstOrFail(),
        'delivered',
        (int) $shipment->events()->where('type', 'delivered')->value('id'),
    );
    expect(NotificationJob::query()->where('shipment_id', $shipment->id)->count())->toBe($before);

    config([
        'fleetpilot.notification_provider' => 'mail',
        'mail.default' => 'smtp',
        'mail.mailers.smtp.transport' => 'smtp',
        'mail.mailers.smtp.host' => '127.0.0.1',
        'mail.mailers.smtp.port' => 1,
        'mail.mailers.smtp.timeout' => 1,
    ]);
    app(ProcessNotificationJobs::class)->run();

    expect($shipment->fresh()->status)->toBe('delivered');
    expect($job->fresh()->status)->toBe('failed');
    expect($job->fresh()->attempts)->toBe(1);

    config(['fleetpilot.notification_provider' => 'log']);
    app(ProcessNotificationJobs::class)->run();
    expect($job->fresh()->status)->toBe('sent');
    expect($job->fresh()->attempts)->toBe(2);
    expect($shipment->fresh()->status)->toBe('delivered');
});

test('customers cannot download internal documents from another organization', function () {
    Storage::fake('local');
    $world = OperationsWorld::boot('harbor-docs');
    $shipment = OperationsWorld::shipment($world);
    Storage::disk('local')->put('documents/secret.txt', 'internal paperwork');
    $document = Document::query()->create([
        'organization_id' => $world['organization']->id,
        'shipment_id' => $shipment->id,
        'doc_type' => 'packing_list',
        'visibility' => 'internal',
        'title' => 'Packing list',
        'created_by' => $world['owner']->id,
    ]);
    $file = StoredFile::query()->create([
        'organization_id' => $world['organization']->id,
        'disk' => 'local',
        'path' => 'documents/secret.txt',
        'original_name' => 'packing-list.txt',
        'mime_type' => 'text/plain',
        'size_bytes' => 18,
        'checksum_sha256' => hash('sha256', 'internal paperwork'),
        'kind' => 'document',
        'document_id' => $document->id,
        'created_by' => $world['owner']->id,
    ]);

    $customerUser = User::factory()->create(['email' => 'customer-docs@example.test']);
    $world['provision']->addMember($world['organization'], $customerUser, 'customer', $world['customer']->id);
    $this->actingAs($customerUser)->get(route('files.download', $file))->assertForbidden();

    $outsider = OperationsWorld::boot('harbor-docs-b');
    $this->actingAs($outsider['owner'])->get(route('files.download', $file))->assertNotFound();

    $document->forceFill(['visibility' => 'customer_visible'])->save();
    $this->actingAs($customerUser)->get(route('files.download', $file))->assertOk();
});
