<?php

namespace Tests\Support;

use App\Domain\Identity\OrganizationContext;
use App\Domain\Identity\ProvisionOrganization;
use App\Domain\Shipments\CreateShipment;
use App\Domain\Shipments\TransitionShipment;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\Driver;
use App\Models\Organization;
use App\Models\Shipment;
use App\Models\User;
use App\Models\Vehicle;

final class OperationsWorld
{
    /**
     * @return array{
     *     organization: Organization,
     *     owner: User,
     *     context: OrganizationContext,
     *     customer: Customer,
     *     driver: Driver,
     *     vehicle: Vehicle,
     *     provision: ProvisionOrganization
     * }
     */
    public static function boot(string $slug = 'harbor-test'): array
    {
        $provision = app(ProvisionOrganization::class);
        $organization = $provision->create('Harbor Test Cartage', $slug, 'America/Chicago', 'USD');
        $owner = User::factory()->create([
            'name' => 'Mara Ellison',
            'email' => $slug.'-owner@example.test',
        ]);
        $provision->addMember($organization, $owner, 'owner');
        $customer = Customer::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Northwind Provisions',
            'company_name' => 'Northwind Provisions',
            'email' => 'dispatch@northwind.example',
            'phone' => '555-0140',
            'billing_city' => 'Green Bay',
            'billing_region' => 'WI',
            'billing_country' => 'United States',
        ]);
        CustomerAddress::query()->create([
            'customer_id' => $customer->id,
            'label' => 'Dock',
            'line1' => '418 Harbor Ave',
            'city' => 'Green Bay',
            'region' => 'WI',
            'postal_code' => '54302',
            'country' => 'United States',
            'contact_name' => 'Andrea Moss',
            'contact_phone' => '555-0148',
        ]);
        $driver = Driver::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Nia Rahman',
            'phone' => '555-0172',
            'email' => 'nia.rahman@harborpine.example',
            'kind' => 'employee',
            'availability' => 'available',
            'status' => 'active',
            'license_number' => 'WI-DEMO-1001',
        ]);
        $vehicle = Vehicle::query()->create([
            'organization_id' => $organization->id,
            'fleet_number' => 'HP-14',
            'registration' => 'WI DEMO 14',
            'vehicle_type' => 'cargo_van',
            'capacity_kg' => 1200,
            'availability' => 'available',
            'status' => 'active',
            'maintenance_status' => 'ok',
        ]);

        return [
            'organization' => $organization,
            'owner' => $owner,
            'context' => $provision->contextFor($owner),
            'customer' => $customer,
            'driver' => $driver,
            'vehicle' => $vehicle,
            'provision' => $provision,
        ];
    }

    /**
     * @param  array<string, mixed>  $world
     * @param  array<string, mixed>  $overrides
     */
    public static function shipment(array $world, array $overrides = []): Shipment
    {
        /** @var OrganizationContext $ctx */
        $ctx = $world['context'];
        $payload = array_replace_recursive([
            'customer_id' => $world['customer']->id,
            'service_type' => 'standard',
            'priority' => 'normal',
            'description' => 'Four crates of canned tomatoes.',
            'handling_instructions' => 'Keep upright.',
            'declared_value_cents' => 42000,
            'weight_kg' => 180,
            'pickup_window_start' => '2026-10-12T13:00:00-05:00',
            'pickup_window_end' => '2026-10-12T15:00:00-05:00',
            'delivery_window_start' => '2026-10-12T18:00:00-05:00',
            'delivery_window_end' => '2026-10-12T20:00:00-05:00',
            'service_area' => 'Green Bay',
            'internal_notes' => 'Do not share this note with the customer.',
            'pickup' => [
                'line1' => '418 Harbor Ave',
                'city' => 'Green Bay',
                'region' => 'WI',
                'postal_code' => '54302',
                'country' => 'United States',
                'contact_name' => 'Andrea Moss',
                'contact_phone' => '555-0148',
            ],
            'delivery' => [
                'line1' => '90 Pine Dock',
                'city' => 'Milwaukee',
                'region' => 'WI',
                'postal_code' => '53202',
                'country' => 'United States',
                'contact_name' => 'June Harlow',
                'contact_phone' => '555-0194',
            ],
            'items' => [
                ['description' => 'Canned tomatoes', 'quantity' => 4, 'weight_kg' => 45],
            ],
        ], $overrides);

        return app(CreateShipment::class)->handle($ctx, $payload);
    }

    /**
     * @param  array<string, mixed>  $world
     */
    public static function ready(array $world, array $overrides = []): Shipment
    {
        $shipment = self::shipment($world, $overrides);

        return app(TransitionShipment::class)->handle($world['context'], $shipment, [
            'to' => 'ready_for_dispatch',
        ]);
    }
}
