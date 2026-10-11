<?php

declare(strict_types=1);

namespace App\Domain\Shipments;

use App\Domain\Identity\OrganizationContext;
use App\Domain\Shared\Activity;
use App\Domain\Shared\Clocks;
use App\Domain\Shared\Sequences;
use App\Domain\Tracking\RecordEvent;
use App\Models\Customer;
use App\Models\Shipment;
use App\Models\ShipmentOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class CreateShipment
{
    public function __construct(
        private Sequences $sequences,
        private RecordEvent $events,
        private Activity $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function handle(OrganizationContext $ctx, array $input): Shipment
    {
        abort_unless($ctx->allows('shipments.write'), 403);

        $data = Validator::make($input, [
            'customer_id' => ['required', 'integer'],
            'order_id' => ['nullable', 'integer'],
            'customer_reference' => ['nullable', 'string', 'max:80'],
            'service_type' => ['required', Rule::in(['standard', 'express', 'same_day', 'freight', 'courier'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'description' => ['required', 'string', 'max:2000'],
            'handling_instructions' => ['nullable', 'string', 'max:2000'],
            'declared_value_cents' => ['nullable', 'integer', 'min:0'],
            'weight_kg' => ['nullable', 'numeric', 'min:0'],
            'length_cm' => ['nullable', 'numeric', 'min:0'],
            'width_cm' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'pickup_window_start' => ['required', 'date'],
            'pickup_window_end' => ['required', 'date', 'after:pickup_window_start'],
            'delivery_window_start' => ['required', 'date', 'after_or_equal:pickup_window_start'],
            'delivery_window_end' => ['required', 'date', 'after:delivery_window_start'],
            'service_area' => ['nullable', 'string', 'max:80'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'pickup' => ['required', 'array'],
            'delivery' => ['required', 'array'],
            'pickup.line1' => ['required', 'string', 'max:160'],
            'pickup.city' => ['required', 'string', 'max:80'],
            'pickup.country' => ['required', 'string', 'max:56'],
            'pickup.line2' => ['nullable', 'string', 'max:160'],
            'pickup.region' => ['nullable', 'string', 'max:80'],
            'pickup.postal_code' => ['nullable', 'string', 'max:20'],
            'pickup.contact_name' => ['nullable', 'string', 'max:120'],
            'pickup.contact_phone' => ['nullable', 'string', 'max:40'],
            'pickup.instructions' => ['nullable', 'string', 'max:500'],
            'delivery.line1' => ['required', 'string', 'max:160'],
            'delivery.city' => ['required', 'string', 'max:80'],
            'delivery.country' => ['required', 'string', 'max:56'],
            'delivery.line2' => ['nullable', 'string', 'max:160'],
            'delivery.region' => ['nullable', 'string', 'max:80'],
            'delivery.postal_code' => ['nullable', 'string', 'max:20'],
            'delivery.contact_name' => ['nullable', 'string', 'max:120'],
            'delivery.contact_phone' => ['nullable', 'string', 'max:40'],
            'delivery.instructions' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'max:40'],
            'items.*.description' => ['required', 'string', 'max:200'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        $customerId = $data['customer_id'] ?? null;
        if (! is_numeric($customerId)) {
            throw ValidationException::withMessages(['customer_id' => 'Choose a customer.']);
        }
        $customer = Customer::query()
            ->where('organization_id', $ctx->organization->id)
            ->whereKey((int) $customerId)
            ->firstOrFail();

        if (! empty($data['order_id'])) {
            $orderId = $data['order_id'];
            if (! is_numeric($orderId)) {
                throw ValidationException::withMessages(['order_id' => 'Choose an order for this customer.']);
            }
            ShipmentOrder::query()
                ->where('organization_id', $ctx->organization->id)
                ->where('customer_id', $customer->id)
                ->whereKey((int) $orderId)
                ->firstOrFail();
        }

        return DB::transaction(function () use ($ctx, $data, $customer): Shipment {
            $timezone = $ctx->organization->timezone ?: 'UTC';
            $pickupStart = Clocks::at($data['pickup_window_start'], $timezone);
            $pickupEnd = Clocks::at($data['pickup_window_end'], $timezone);
            $deliveryStart = Clocks::at($data['delivery_window_start'], $timezone);
            $deliveryEnd = Clocks::at($data['delivery_window_end'], $timezone);
            $number = $this->sequences->next($ctx->organization->id, 'shipment', 'SHP');
            $shipment = Shipment::query()->create([
                'organization_id' => $ctx->organization->id,
                'order_id' => $data['order_id'] ?? null,
                'customer_id' => $customer->id,
                'shipment_number' => $number,
                'customer_reference' => $data['customer_reference'] ?? null,
                'service_type' => $data['service_type'],
                'priority' => $data['priority'],
                'status' => 'draft',
                'description' => $data['description'],
                'handling_instructions' => $data['handling_instructions'] ?? null,
                'declared_value_cents' => $data['declared_value_cents'] ?? null,
                'currency' => $ctx->organization->currency,
                'weight_kg' => $data['weight_kg'] ?? null,
                'length_cm' => $data['length_cm'] ?? null,
                'width_cm' => $data['width_cm'] ?? null,
                'height_cm' => $data['height_cm'] ?? null,
                'pickup_window_start' => $pickupStart,
                'pickup_window_end' => $pickupEnd,
                'delivery_window_start' => $deliveryStart,
                'delivery_window_end' => $deliveryEnd,
                'planned_pickup_at' => $pickupStart,
                'planned_delivery_at' => $deliveryEnd,
                'service_area' => $data['service_area'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'created_by' => $ctx->user->id,
            ]);

            foreach (['pickup', 'delivery'] as $kind) {
                $address = $data[$kind];
                $shipment->pickupAddress()->getRelated()->newQuery()->create([
                    'shipment_id' => $shipment->id,
                    'kind' => $kind,
                    'line1' => $address['line1'],
                    'line2' => $address['line2'] ?? null,
                    'city' => $address['city'],
                    'region' => $address['region'] ?? null,
                    'postal_code' => $address['postal_code'] ?? null,
                    'country' => $address['country'],
                    'contact_name' => $address['contact_name'] ?? null,
                    'contact_phone' => $address['contact_phone'] ?? null,
                    'instructions' => $address['instructions'] ?? null,
                ]);
            }

            foreach ($data['items'] ?? [] as $item) {
                $shipment->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'weight_kg' => $item['weight_kg'] ?? null,
                ]);
            }

            $this->events->write($ctx, $shipment, 'created', 'Shipment created.', 'draft');
            $this->activity->record($ctx, 'shipment.created', 'shipment', $shipment->id);

            return $shipment;
        });
    }
}
