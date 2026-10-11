<?php

namespace App\Http\Presenters;

use App\Domain\Identity\OrganizationContext;
use App\Models\Charge;
use App\Models\Driver;
use App\Models\Shipment;
use App\Models\ShipmentAddress;
use App\Models\TrackingEvent;
use App\Models\Vehicle;

final class OperationsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function shipmentList(Shipment $shipment, OrganizationContext $ctx): array
    {
        return [
            'id' => $shipment->id,
            'shipment_number' => $shipment->shipment_number,
            'customer_reference' => $shipment->customer_reference,
            'status' => $shipment->status,
            'priority' => $shipment->priority,
            'service_type' => $shipment->service_type,
            'service_area' => $shipment->service_area,
            'customer' => $shipment->customer?->only(['id', 'name', 'company_name']),
            'pickup_city' => $shipment->pickupAddress?->city,
            'delivery_city' => $shipment->deliveryAddress?->city,
            'pickup_window_start' => $shipment->pickup_window_start->toIso8601String(),
            'pickup_window_end' => $shipment->pickup_window_end->toIso8601String(),
            'delivery_window_start' => $shipment->delivery_window_start->toIso8601String(),
            'delivery_window_end' => $shipment->delivery_window_end->toIso8601String(),
            'planned_pickup_at' => $shipment->planned_pickup_at?->toIso8601String(),
            'planned_delivery_at' => $shipment->planned_delivery_at?->toIso8601String(),
            'actual_pickup_at' => $shipment->actual_pickup_at?->toIso8601String(),
            'actual_delivery_at' => $shipment->actual_delivery_at?->toIso8601String(),
            'driver' => $this->driverName($shipment, $ctx),
            'vehicle' => $shipment->currentVehicle?->only(['id', 'fleet_number']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function shipmentDetail(Shipment $shipment, OrganizationContext $ctx): array
    {
        $internal = $ctx->allows('shipments.write') || $ctx->allows('dispatch.manage');
        $row = $this->shipmentList($shipment, $ctx);
        $row['description'] = $shipment->description;
        $row['handling_instructions'] = $shipment->handling_instructions;
        $row['internal_notes'] = $internal ? $shipment->internal_notes : null;
        $row['declared_value_cents'] = $ctx->allows('finance.read') || $ctx->allows('shipments.write') ? $shipment->declared_value_cents : null;
        $row['currency'] = $shipment->currency;
        $row['weight_kg'] = $shipment->weight_kg;
        $row['length_cm'] = $shipment->length_cm;
        $row['width_cm'] = $shipment->width_cm;
        $row['height_cm'] = $shipment->height_cm;
        $row['pickup'] = $this->address($shipment->pickupAddress, $ctx);
        $row['delivery'] = $this->address($shipment->deliveryAddress, $ctx);
        $row['items'] = $shipment->items->map(fn ($item): array => [
            'description' => $item->description,
            'quantity' => $item->quantity,
            'weight_kg' => $item->weight_kg,
        ])->all();
        $row['driver_phone'] = $this->showDriverPhone($ctx) ? $shipment->currentDriver?->phone : null;
        $row['current_driver_id'] = $shipment->current_driver_id;
        $row['current_vehicle_id'] = $shipment->current_vehicle_id;
        $row['current_trip_id'] = $shipment->current_trip_id;

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function event(TrackingEvent $event, bool $includeReason): array
    {
        return [
            'id' => $event->id,
            'type' => $event->type,
            'status' => $event->status,
            'message' => $event->message,
            'reason' => $includeReason ? $event->reason : null,
            'occurred_at' => $event->occurred_at->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function address(?ShipmentAddress $address, OrganizationContext $ctx): ?array
    {
        if (! $address) {
            return null;
        }
        $showContact = $ctx->role !== 'customer';

        return [
            'line1' => $address->line1,
            'line2' => $address->line2,
            'city' => $address->city,
            'region' => $address->region,
            'postal_code' => $address->postal_code,
            'country' => $address->country,
            'contact_name' => $showContact ? $address->contact_name : null,
            'contact_phone' => $showContact ? $address->contact_phone : null,
            'instructions' => $address->instructions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function driver(Driver $driver, OrganizationContext $ctx): array
    {
        $row = [
            'id' => $driver->id,
            'name' => $driver->name,
            'kind' => $driver->kind,
            'availability' => $driver->availability,
            'status' => $driver->status,
            'phone' => $ctx->allows('drivers.read') ? $driver->phone : null,
            'email' => $ctx->allows('drivers.read') ? $driver->email : null,
        ];
        if ($ctx->allows('drivers.read_sensitive')) {
            $row['license_number'] = $driver->license_number;
            $row['license_expires_on'] = $driver->license_expires_on?->toDateString();
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function vehicle(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'fleet_number' => $vehicle->fleet_number,
            'registration' => $vehicle->registration,
            'vehicle_type' => $vehicle->vehicle_type,
            'capacity_kg' => $vehicle->capacity_kg,
            'availability' => $vehicle->availability,
            'status' => $vehicle->status,
            'maintenance_status' => $vehicle->maintenance_status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function charge(Charge $charge): array
    {
        return [
            'id' => $charge->id,
            'shipment_id' => $charge->shipment_id,
            'shipment_number' => $charge->shipment?->shipment_number,
            'customer' => $charge->customer?->name,
            'description' => $charge->description,
            'kind' => $charge->kind,
            'amount_cents' => $charge->amount_cents,
            'currency' => $charge->currency,
            'billing_status' => $charge->billing_status,
            'payment_status' => $charge->payment_status,
            'invoice_reference' => $charge->invoice_reference,
            'external_accounting_ref' => $charge->external_accounting_ref,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function driverName(Shipment $shipment, OrganizationContext $ctx): ?array
    {
        if (! $shipment->currentDriver) {
            return null;
        }
        if ($ctx->role === 'customer') {
            return ['id' => $shipment->currentDriver->id, 'name' => 'Assigned driver'];
        }

        return ['id' => $shipment->currentDriver->id, 'name' => $shipment->currentDriver->name];
    }

    private function showDriverPhone(OrganizationContext $ctx): bool
    {
        return $ctx->allows('dispatch.manage') || $ctx->role === 'driver' || $ctx->allows('drivers.read');
    }
}
