<?php

namespace Database\Seeders;

use App\Domain\Billing\RecordCharge;
use App\Domain\Dispatch\AssignShipment;
use App\Domain\Dispatch\PlanTrip;
use App\Domain\Identity\OrganizationContext;
use App\Domain\Identity\ProvisionOrganization;
use App\Domain\Shipments\CreateShipment;
use App\Domain\Shipments\TransitionShipment;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerContact;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use App\Models\TrackingToken;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    public const PASSWORD = 'PilotDemo!2026';

    public const PUBLIC_TRACKING_TOKEN = 'hbpine-demo-public-track-7f3a9c2e';

    public function run(): void
    {
        $provision = app(ProvisionOrganization::class);
        $organization = $provision->create('Harbor & Pine Cartage', 'harbor-pine', 'America/Chicago', 'USD');
        $now = CarbonImmutable::now('America/Chicago');

        $users = [
            'owner' => $this->user('Mara Ellison', 'mara.ellison@harborpine.example'),
            'operations_manager' => $this->user('Jules Okonkwo', 'jules.okonkwo@harborpine.example'),
            'dispatcher' => $this->user('Owen Park', 'owen.park@harborpine.example'),
            'driver' => $this->user('Nia Rahman', 'nia.rahman@harborpine.example'),
            'warehouse' => $this->user('Chris Dalton', 'chris.dalton@harborpine.example'),
            'customer_service' => $this->user('Priya Shah', 'priya.shah@harborpine.example'),
            'finance' => $this->user('Helen Cho', 'helen.cho@harborpine.example'),
            'customer' => $this->user('Andrea Moss', 'andrea.moss@harborpine.example'),
        ];

        $customers = [
            'northwind' => $this->customer($organization->id, 'Northwind Provisions', 'dispatch@northwind.example', '555-0140', 'Green Bay', 'Andrea Moss', '418 Harbor Ave', '54302', 'Dock 2, call ahead'),
            'lumen' => $this->customer($organization->id, 'Lumen Glassworks', 'shipping@lumenglass.example', '555-0166', 'Milwaukee', 'Elena Cho', '12 Kiln Street', '53204', 'Freight elevator only'),
            'cedar' => $this->customer($organization->id, 'Cedar & Rye Market', 'orders@cedarrye.example', '555-0119', 'Madison', 'Tom Adler', '640 State Street', '53703', 'No box truck in the alley'),
            'oakline' => $this->customer($organization->id, 'Oakline Medical Supply', 'receiving@oakline.example', '555-0188', 'Appleton', 'Ruth Keller', '77 Clinic Drive', '54911', 'Ask for receiving'),
            'riverbend' => $this->customer($organization->id, 'Riverbend Print Co', 'plant@riverbendprint.example', '555-0133', 'Racine', 'Luis Vega', '9 Paper Mill Road', '53403', 'Use the north gate'),
        ];

        $drivers = [
            'nia' => $this->driver($organization->id, 'Nia Rahman', 'nia.rahman@harborpine.example', '555-0172', 'employee', 'available'),
            'luis' => $this->driver($organization->id, 'Luis Ortega', 'luis.ortega@harborpine.example', '555-0173', 'contractor', 'available'),
            'samir' => $this->driver($organization->id, 'Samir Adeyemi', 'samir.adeyemi@harborpine.example', '555-0174', 'employee', 'available'),
            'elena' => $this->driver($organization->id, 'Elena Voss', 'elena.voss@harborpine.example', '555-0175', 'employee', 'available'),
            'mateo' => $this->driver($organization->id, 'Mateo Ruiz', 'mateo.ruiz@harborpine.example', '555-0176', 'contractor', 'available'),
            'imani' => $this->driver($organization->id, 'Imani Brooks', 'imani.brooks@harborpine.example', '555-0177', 'employee', 'available'),
            'noah' => $this->driver($organization->id, 'Noah Pell', 'noah.pell@harborpine.example', '555-0178', 'employee', 'available'),
            'helena' => $this->driver($organization->id, 'Helena Brooks', 'helena.brooks@harborpine.example', '555-0179', 'employee', 'available'),
            'derek' => $this->driver($organization->id, 'Derek Holm', 'derek.holm@harborpine.example', '555-0180', 'employee', 'off_duty', 'On leave through Friday. Not available for dispatch.'),
        ];

        $vehicles = [
            'hp14' => $this->vehicle($organization->id, 'HP-14', 'cargo_van', 1200),
            'hp22' => $this->vehicle($organization->id, 'HP-22', 'box_truck', 3500),
            'hp31' => $this->vehicle($organization->id, 'HP-31', 'sprinter', 1500),
            'hp08' => $this->vehicle($organization->id, 'HP-08', 'cargo_van', 1100),
            'hp41' => $this->vehicle($organization->id, 'HP-41', 'box_truck', 4000),
            'hp55' => $this->vehicle($organization->id, 'HP-55', 'straight_truck', 6000),
            'hp70' => $this->vehicle($organization->id, 'HP-70', 'cargo_van', 1200),
            'hp60' => $this->vehicle($organization->id, 'HP-60', 'sprinter', 1400),
            'hp19' => $this->vehicle($organization->id, 'HP-19', 'cargo_van', 1200),
            'hp02' => $this->vehicle($organization->id, 'HP-02', 'straight_truck', 7000, 'out_of_service', 'Brake inspection open. Do not dispatch.'),
        ];

        $provision->addMember($organization, $users['owner'], 'owner');
        $provision->addMember($organization, $users['operations_manager'], 'operations_manager');
        $provision->addMember($organization, $users['dispatcher'], 'dispatcher');
        $provision->addMember($organization, $users['driver'], 'driver', null, $drivers['nia']->id);
        $provision->addMember($organization, $users['warehouse'], 'warehouse');
        $provision->addMember($organization, $users['customer_service'], 'customer_service');
        $provision->addMember($organization, $users['finance'], 'finance');
        $provision->addMember($organization, $users['customer'], 'customer', $customers['northwind']->id);

        $ctx = $provision->contextFor($users['owner']);
        $order = ShipmentOrder::query()->create([
            'organization_id' => $organization->id,
            'customer_id' => $customers['northwind']->id,
            'order_number' => 'ORD-1044',
            'customer_reference' => 'PO-8831',
            'status' => 'open',
            'notes' => 'Weekly grocery replenishment.',
            'created_by' => $users['owner']->id,
        ]);

        $deliveredToday = $this->advance($ctx, $this->draft($ctx, $customers['lumen'], [
            'customer_reference' => 'LG-2201',
            'service_type' => 'express',
            'priority' => 'high',
            'description' => 'Crated sheet glass, eight panes.',
            'handling_instructions' => 'Glass. Keep upright and strap the crate.',
            'declared_value_cents' => 186000,
            'weight_kg' => 240,
            'service_area' => 'Milwaukee',
            'internal_notes' => 'Customer asked us not to mention the broken sample from last month.',
            'pickup_window_start' => $now->subHours(8)->toIso8601String(),
            'pickup_window_end' => $now->subHours(7)->toIso8601String(),
            'delivery_window_start' => $now->subHours(3)->toIso8601String(),
            'delivery_window_end' => $now->addHour()->toIso8601String(),
            'pickup' => $this->stop('12 Kiln Street', 'Milwaukee', '53204', 'Elena Cho', '555-0166', 'Freight elevator only'),
            'delivery' => $this->stop('400 Gallery Row', 'Milwaukee', '53202', 'June Harlow', '555-0194', 'Call from the alley'),
            'items' => [['description' => 'Sheet glass crate', 'quantity' => 8, 'weight_kg' => 30]],
        ]), [
            ['to' => 'ready_for_dispatch'],
        ]);
        $deliveredToday = app(AssignShipment::class)->handle($ctx, $deliveredToday, [
            'driver_id' => $drivers['elena']->id,
            'vehicle_id' => $vehicles['hp08']->id,
            'notes' => 'Downtown delivery, no overnight hold.',
        ]);
        $deliveredToday = $this->advance($ctx, $deliveredToday, [
            ['to' => 'picked_up'],
            ['to' => 'in_transit'],
            [
                'to' => 'delivered',
                'recipient_name' => 'June Harlow',
                'notes' => 'Left with the gallery receiving desk.',
                'customer_message' => 'Delivered to the gallery receiving desk.',
                'signature_data' => $this->signature(),
            ],
        ]);

        $returned = $this->advance($ctx, $this->draft($ctx, $customers['cedar'], [
            'customer_reference' => 'CR-118',
            'description' => 'Refrigerated bakery racks. Returned after the store refused the late window.',
            'service_area' => 'Madison',
            'pickup_window_start' => $now->subDays(2)->setTime(8, 0)->toIso8601String(),
            'pickup_window_end' => $now->subDays(2)->setTime(10, 0)->toIso8601String(),
            'delivery_window_start' => $now->subDays(2)->setTime(13, 0)->toIso8601String(),
            'delivery_window_end' => $now->subDays(2)->setTime(16, 0)->toIso8601String(),
            'pickup' => $this->stop('640 State Street', 'Madison', '53703', 'Tom Adler', '555-0119'),
            'delivery' => $this->stop('18 Market Square', 'Madison', '53703', 'Store closing crew', '555-0101'),
        ]), [['to' => 'ready_for_dispatch']]);
        $returned = app(AssignShipment::class)->handle($ctx, $returned, [
            'driver_id' => $drivers['elena']->id,
            'vehicle_id' => $vehicles['hp08']->id,
        ]);
        $this->advance($ctx, $returned, [
            ['to' => 'picked_up'],
            ['to' => 'in_transit'],
            ['to' => 'returned_to_sender', 'reason' => 'Receiver closed before the truck arrived and refused a next-day hold.'],
        ]);

        $this->advance($ctx, $this->draft($ctx, $customers['northwind'], [
            'order_id' => $order->id,
            'customer_reference' => 'PO-8831',
            'priority' => 'high',
            'description' => 'Weekly grocery replenishment, dry goods only.',
            'service_area' => 'Green Bay',
            'internal_notes' => 'Account is 12 days past terms. Dispatch is still authorized.',
            'pickup_window_start' => $now->addMinutes(90)->toIso8601String(),
            'pickup_window_end' => $now->addHours(3)->toIso8601String(),
            'delivery_window_start' => $now->addHours(6)->toIso8601String(),
            'delivery_window_end' => $now->addHours(9)->toIso8601String(),
            'pickup' => $this->stop('418 Harbor Ave', 'Green Bay', '54302', 'Andrea Moss', '555-0148', 'Dock 2'),
            'delivery' => $this->stop('90 Pine Dock', 'Milwaukee', '53202', 'Receiving', '555-0190'),
            'items' => [['description' => 'Dry grocery pallet', 'quantity' => 2, 'weight_kg' => 400]],
        ]), [['to' => 'ready_for_dispatch']]);
        $assigned = Shipment::query()->where('customer_reference', 'PO-8831')->firstOrFail();
        app(AssignShipment::class)->handle($ctx, $assigned, [
            'driver_id' => $drivers['nia']->id,
            'vehicle_id' => $vehicles['hp14']->id,
            'notes' => 'Nia is already in Green Bay.',
        ]);

        $picked = $this->advance($ctx, $this->draft($ctx, $customers['oakline'], [
            'customer_reference' => 'OM-774',
            'service_type' => 'courier',
            'description' => 'Sealed medical supply totes. No temperature hold required.',
            'service_area' => 'Appleton',
            'pickup_window_start' => $now->subHours(5)->toIso8601String(),
            'pickup_window_end' => $now->subHours(4)->toIso8601String(),
            'delivery_window_start' => $now->addHour()->toIso8601String(),
            'delivery_window_end' => $now->addHours(4)->toIso8601String(),
            'pickup' => $this->stop('77 Clinic Drive', 'Appleton', '54911', 'Ruth Keller', '555-0188'),
            'delivery' => $this->stop('210 Lakeshore Clinic', 'Oshkosh', '54901', 'Receiving desk', '555-0160'),
        ]), [['to' => 'ready_for_dispatch']]);
        $picked = app(AssignShipment::class)->handle($ctx, $picked, [
            'driver_id' => $drivers['luis']->id,
            'vehicle_id' => $vehicles['hp22']->id,
        ]);
        $this->advance($ctx, $picked, [['to' => 'picked_up']]);

        $inTransit = $this->advance($ctx, $this->draft($ctx, $customers['riverbend'], [
            'customer_reference' => 'RB-19',
            'service_type' => 'freight',
            'priority' => 'urgent',
            'description' => 'Paper rolls for the afternoon press run.',
            'weight_kg' => 900,
            'service_area' => 'Racine',
            'internal_notes' => 'Press room will not accept a partial delivery.',
            'pickup_window_start' => $now->subHours(10)->toIso8601String(),
            'pickup_window_end' => $now->subHours(9)->toIso8601String(),
            'delivery_window_start' => $now->subHours(6)->toIso8601String(),
            'delivery_window_end' => $now->subHours(4)->toIso8601String(),
            'pickup' => $this->stop('9 Paper Mill Road', 'Racine', '53403', 'Luis Vega', '555-0133'),
            'delivery' => $this->stop('55 Press House', 'Kenosha', '53140', 'Night shift lead', '555-0122'),
        ]), [['to' => 'ready_for_dispatch']]);
        $inTransit = app(AssignShipment::class)->handle($ctx, $inTransit, [
            'driver_id' => $drivers['samir']->id,
            'vehicle_id' => $vehicles['hp31']->id,
        ]);
        $inTransit = $this->advance($ctx, $inTransit, [
            ['to' => 'picked_up'],
            ['to' => 'in_transit', 'customer_message' => 'The paper rolls left Racine and are on the way to Kenosha.'],
        ]);
        TrackingToken::query()->create([
            'organization_id' => $organization->id,
            'shipment_id' => $inTransit->id,
            'token_hash' => hash('sha256', self::PUBLIC_TRACKING_TOKEN),
            'expires_at' => $now->addDays(30)->utc(),
            'created_by' => $users['owner']->id,
        ]);

        $failed = $this->advance($ctx, $this->draft($ctx, $customers['cedar'], [
            'customer_reference' => 'CR-204',
            'description' => 'Produce crates for the Saturday market.',
            'service_area' => 'Madison',
            'pickup_window_start' => $now->subHours(6)->toIso8601String(),
            'pickup_window_end' => $now->subHours(5)->toIso8601String(),
            'delivery_window_start' => $now->subHour()->toIso8601String(),
            'delivery_window_end' => $now->addHours(3)->toIso8601String(),
            'pickup' => $this->stop('640 State Street', 'Madison', '53703', 'Tom Adler', '555-0119'),
            'delivery' => $this->stop('2 Farmers Lane', 'Middleton', '53562', 'Market tent 14', '555-0108'),
        ]), [['to' => 'ready_for_dispatch']]);
        $failed = app(AssignShipment::class)->handle($ctx, $failed, [
            'driver_id' => $drivers['mateo']->id,
            'vehicle_id' => $vehicles['hp41']->id,
        ]);
        $this->advance($ctx, $failed, [
            ['to' => 'picked_up'],
            ['to' => 'in_transit'],
            [
                'to' => 'failed_delivery',
                'reason' => 'Market tent was packed down and nobody was left to sign.',
                'customer_message' => 'We could not complete the Middleton delivery. Dispatch will call with a new window.',
            ],
        ]);

        $damaged = $this->advance($ctx, $this->draft($ctx, $customers['lumen'], [
            'customer_reference' => 'LG-2208',
            'description' => 'Replacement mirror panels.',
            'handling_instructions' => 'Two-person unload.',
            'service_area' => 'Milwaukee',
            'pickup_window_start' => $now->subHours(7)->toIso8601String(),
            'pickup_window_end' => $now->subHours(6)->toIso8601String(),
            'delivery_window_start' => $now->addHours(2)->toIso8601String(),
            'delivery_window_end' => $now->addHours(5)->toIso8601String(),
            'pickup' => $this->stop('12 Kiln Street', 'Milwaukee', '53204', 'Elena Cho', '555-0166'),
            'delivery' => $this->stop('88 Hotel Lobby', 'Waukesha', '53186', 'Front desk', '555-0155'),
        ]), [['to' => 'ready_for_dispatch']]);
        $damaged = app(AssignShipment::class)->handle($ctx, $damaged, [
            'driver_id' => $drivers['imani']->id,
            'vehicle_id' => $vehicles['hp55']->id,
        ]);
        $this->advance($ctx, $damaged, [
            ['to' => 'picked_up'],
            ['to' => 'in_transit'],
            ['to' => 'damaged_or_missing', 'reason' => 'One panel corner was crushed when the crate shifted. Two panels remain intact.'],
        ]);

        $failedPickup = $this->advance($ctx, $this->draft($ctx, $customers['oakline'], [
            'customer_reference' => 'OM-781',
            'description' => 'Return totes from the clinic.',
            'service_area' => 'Appleton',
            'pickup_window_start' => $now->subHours(9)->toIso8601String(),
            'pickup_window_end' => $now->subHours(8)->toIso8601String(),
            'delivery_window_start' => $now->addHours(2)->toIso8601String(),
            'delivery_window_end' => $now->addHours(6)->toIso8601String(),
            'pickup' => $this->stop('77 Clinic Drive', 'Appleton', '54911', 'Ruth Keller', '555-0188'),
            'delivery' => $this->stop('15 Warehouse Way', 'Green Bay', '54304', 'Returns cage', '555-0144'),
        ]), [['to' => 'ready_for_dispatch']]);
        $failedPickup = app(AssignShipment::class)->handle($ctx, $failedPickup, [
            'driver_id' => $drivers['noah']->id,
            'vehicle_id' => $vehicles['hp70']->id,
        ]);
        $this->advance($ctx, $failedPickup, [
            ['to' => 'failed_pickup', 'reason' => 'Clinic receiving was in a lockdown drill and could not release the totes.'],
        ]);

        $this->draft($ctx, $customers['riverbend'], [
            'customer_reference' => 'RB-22',
            'description' => 'Ink drums. Still being counted on the dock.',
            'service_area' => 'Racine',
            'pickup_window_start' => $now->addDay()->setTime(9, 0)->toIso8601String(),
            'pickup_window_end' => $now->addDay()->setTime(11, 0)->toIso8601String(),
            'delivery_window_start' => $now->addDay()->setTime(13, 0)->toIso8601String(),
            'delivery_window_end' => $now->addDay()->setTime(15, 0)->toIso8601String(),
            'pickup' => $this->stop('9 Paper Mill Road', 'Racine', '53403', 'Luis Vega', '555-0133'),
            'delivery' => $this->stop('4 Bindery Lane', 'Racine', '53402', 'Bindery lead', '555-0130'),
        ]);

        $this->advance($ctx, $this->draft($ctx, $customers['northwind'], [
            'customer_reference' => 'PO-8840',
            'description' => 'Spare pallet of oats. Waiting on a truck.',
            'service_area' => 'Green Bay',
            'pickup_window_start' => $now->addHours(4)->toIso8601String(),
            'pickup_window_end' => $now->addHours(6)->toIso8601String(),
            'delivery_window_start' => $now->addDay()->setTime(8, 0)->toIso8601String(),
            'delivery_window_end' => $now->addDay()->setTime(11, 0)->toIso8601String(),
            'pickup' => $this->stop('418 Harbor Ave', 'Green Bay', '54302', 'Andrea Moss', '555-0148'),
            'delivery' => $this->stop('90 Pine Dock', 'Milwaukee', '53202', 'Receiving', '555-0190'),
        ]), [['to' => 'ready_for_dispatch']]);

        $this->advance($ctx, $this->draft($ctx, $customers['cedar'], [
            'customer_reference' => 'CR-190',
            'description' => 'Cheese wheels held for a temperature check.',
            'service_area' => 'Madison',
            'pickup_window_start' => $now->subHours(20)->toIso8601String(),
            'pickup_window_end' => $now->subHours(18)->toIso8601String(),
            'delivery_window_start' => $now->addHours(2)->toIso8601String(),
            'delivery_window_end' => $now->addHours(6)->toIso8601String(),
            'pickup' => $this->stop('640 State Street', 'Madison', '53703', 'Tom Adler', '555-0119'),
            'delivery' => $this->stop('70 Capitol Square', 'Madison', '53703', 'Cafe buyer', '555-0112'),
        ]), [
            ['to' => 'ready_for_dispatch'],
            ['to' => 'on_hold', 'reason' => 'Warehouse wants a pulp temperature before the cheese leaves the dock.'],
        ]);

        $this->advance($ctx, $this->draft($ctx, $customers['oakline'], [
            'customer_reference' => 'OM-760',
            'description' => 'Cancelled sample shipment.',
            'service_area' => 'Appleton',
            'pickup_window_start' => $now->addDay()->setTime(10, 0)->toIso8601String(),
            'pickup_window_end' => $now->addDay()->setTime(12, 0)->toIso8601String(),
            'delivery_window_start' => $now->addDay()->setTime(14, 0)->toIso8601String(),
            'delivery_window_end' => $now->addDay()->setTime(16, 0)->toIso8601String(),
            'pickup' => $this->stop('77 Clinic Drive', 'Appleton', '54911', 'Ruth Keller', '555-0188'),
            'delivery' => $this->stop('1 Lab Court', 'Neenah', '54956', 'Lab receiving', '555-0181'),
        ]), [
            ['to' => 'ready_for_dispatch'],
            ['to' => 'cancelled', 'reason' => 'Customer cancelled the sample before pickup.'],
        ]);

        $tripA = $this->advance($ctx, $this->draft($ctx, $customers['northwind'], [
            'customer_reference' => 'PO-8852',
            'description' => 'Bottle returns, first stop.',
            'service_area' => 'Green Bay',
            'pickup_window_start' => $now->addDay()->setTime(8, 0)->toIso8601String(),
            'pickup_window_end' => $now->addDay()->setTime(9, 0)->toIso8601String(),
            'delivery_window_start' => $now->addDay()->setTime(14, 0)->toIso8601String(),
            'delivery_window_end' => $now->addDay()->setTime(16, 0)->toIso8601String(),
            'pickup' => $this->stop('418 Harbor Ave', 'Green Bay', '54302', 'Andrea Moss', '555-0148'),
            'delivery' => $this->stop('30 Depot Street', 'Manitowoc', '54220', 'Depot clerk', '555-0128'),
        ]), [['to' => 'ready_for_dispatch']]);
        $tripB = $this->advance($ctx, $this->draft($ctx, $customers['riverbend'], [
            'customer_reference' => 'RB-30',
            'description' => 'Printed posters, second stop.',
            'service_area' => 'Green Bay',
            'pickup_window_start' => $now->addDay()->setTime(10, 0)->toIso8601String(),
            'pickup_window_end' => $now->addDay()->setTime(11, 0)->toIso8601String(),
            'delivery_window_start' => $now->addDay()->setTime(15, 0)->toIso8601String(),
            'delivery_window_end' => $now->addDay()->setTime(17, 0)->toIso8601String(),
            'pickup' => $this->stop('9 Paper Mill Road', 'Racine', '53403', 'Luis Vega', '555-0133'),
            'delivery' => $this->stop('44 Hall Avenue', 'Sheboygan', '53081', 'Box office', '555-0129'),
        ]), [['to' => 'ready_for_dispatch']]);
        app(PlanTrip::class)->create($ctx, [
            'driver_id' => $drivers['helena']->id,
            'vehicle_id' => $vehicles['hp60']->id,
            'shipment_ids' => [$tripA->id, $tripB->id],
            'planned_departure' => $now->addDay()->setTime(7, 30)->toIso8601String(),
            'notes' => 'Manual stop order: Green Bay pickup, Racine pickup, then the two deliveries. Not an optimized route.',
        ]);

        $charges = app(RecordCharge::class);
        $linehaul = $charges->create($ctx, $deliveredToday, [
            'description' => 'Linehaul, Milwaukee kiln to gallery',
            'kind' => 'base',
            'amount_cents' => 24500,
            'invoice_reference' => 'INV-2044',
        ]);
        $charges->create($ctx, $deliveredToday, [
            'description' => 'Liftgate',
            'kind' => 'accessorial',
            'amount_cents' => 3500,
        ]);
        $charges->create($ctx, $deliveredToday, [
            'description' => 'Account allowance',
            'kind' => 'discount',
            'amount_cents' => 1500,
        ]);
        $charges->update($ctx, $linehaul, [
            'billing_status' => 'invoiced',
            'payment_status' => 'paid',
            'invoice_reference' => 'INV-2044',
            'external_accounting_ref' => 'QB-DEMO-2044',
        ]);
        $open = Shipment::query()->where('customer_reference', 'PO-8840')->firstOrFail();
        $charges->create($ctx, $open, [
            'description' => 'Quoted linehaul, not yet invoiced',
            'kind' => 'base',
            'amount_cents' => 18000,
        ]);
    }

    private function user(string $name, string $email): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'email_verified_at' => now(),
        ]);
    }

    private function customer(
        int $organizationId,
        string $name,
        string $email,
        string $phone,
        string $city,
        string $contact,
        string $line1,
        string $postal,
        string $instructions,
    ): Customer {
        $customer = Customer::query()->create([
            'organization_id' => $organizationId,
            'name' => $name,
            'company_name' => $name,
            'email' => $email,
            'phone' => $phone,
            'billing_name' => $name,
            'billing_email' => $email,
            'billing_line1' => $line1,
            'billing_city' => $city,
            'billing_region' => 'WI',
            'billing_postal_code' => $postal,
            'billing_country' => 'United States',
        ]);
        CustomerContact::query()->create([
            'customer_id' => $customer->id,
            'name' => $contact,
            'email' => $email,
            'phone' => $phone,
            'role_title' => 'Shipping contact',
            'authorized' => true,
        ]);
        CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'label' => 'Main dock',
            'line1' => $line1,
            'city' => $city,
            'region' => 'WI',
            'postal_code' => $postal,
            'country' => 'United States',
            'contact_name' => $contact,
            'contact_phone' => $phone,
            'instructions' => $instructions,
        ]);

        return $customer;
    }

    private function driver(int $organizationId, string $name, string $email, string $phone, string $kind, string $availability, ?string $notes = null): Driver
    {
        return Driver::query()->create([
            'organization_id' => $organizationId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'kind' => $kind,
            'availability' => $availability,
            'status' => 'active',
            'license_number' => 'WI-'.strtoupper(substr(md5($email), 0, 6)),
            'license_expires_on' => now()->addYear()->toDateString(),
            'notes' => $notes,
        ]);
    }

    private function vehicle(int $organizationId, string $fleet, string $type, int $capacity, string $maintenance = 'ok', ?string $notes = null): Vehicle
    {
        return Vehicle::query()->create([
            'organization_id' => $organizationId,
            'fleet_number' => $fleet,
            'registration' => 'WI '.$fleet,
            'vehicle_type' => $type,
            'capacity_kg' => $capacity,
            'availability' => $maintenance === 'out_of_service' ? 'unavailable' : 'available',
            'status' => 'active',
            'maintenance_status' => $maintenance,
            'notes' => $notes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function draft(OrganizationContext $ctx, Customer $customer, array $overrides): Shipment
    {
        return app(CreateShipment::class)->handle($ctx, array_replace_recursive([
            'customer_id' => $customer->id,
            'service_type' => 'standard',
            'priority' => 'normal',
            'description' => 'General freight.',
            'declared_value_cents' => 25000,
            'weight_kg' => 100,
            'currency' => 'USD',
            'pickup' => $this->stop('418 Harbor Ave', 'Green Bay', '54302', 'Dock', '555-0140'),
            'delivery' => $this->stop('90 Pine Dock', 'Milwaukee', '53202', 'Receiving', '555-0190'),
            'items' => [['description' => 'Carton', 'quantity' => 1, 'weight_kg' => 10]],
        ], $overrides));
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function advance(OrganizationContext $ctx, Shipment $shipment, array $steps): Shipment
    {
        foreach ($steps as $step) {
            $shipment = app(TransitionShipment::class)->handle($ctx, $shipment, $step);
        }

        return $shipment;
    }

    /**
     * @return array<string, string>
     */
    private function stop(string $line1, string $city, string $postal, string $contact, string $phone, ?string $instructions = null): array
    {
        return [
            'line1' => $line1,
            'city' => $city,
            'region' => 'WI',
            'postal_code' => $postal,
            'country' => 'United States',
            'contact_name' => $contact,
            'contact_phone' => $phone,
            'instructions' => $instructions ?? '',
        ];
    }

    private function signature(): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        if ($png === false) {
            throw new RuntimeException('Demo signature could not be decoded.');
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
