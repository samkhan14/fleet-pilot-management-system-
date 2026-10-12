<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shipment extends Model
{
    protected $fillable = [
        'organization_id', 'order_id', 'customer_id', 'shipment_number', 'customer_reference',
        'service_type', 'priority', 'status', 'description', 'handling_instructions',
        'declared_value_cents', 'currency', 'weight_kg', 'length_cm', 'width_cm', 'height_cm',
        'pickup_window_start', 'pickup_window_end', 'delivery_window_start', 'delivery_window_end',
        'planned_pickup_at', 'planned_delivery_at', 'actual_pickup_at', 'actual_delivery_at',
        'service_area', 'internal_notes', 'current_driver_id', 'current_vehicle_id', 'current_trip_id',
        'current_assignment_id', 'current_exception_id', 'created_by', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'pickup_window_start' => 'datetime',
            'pickup_window_end' => 'datetime',
            'delivery_window_start' => 'datetime',
            'delivery_window_end' => 'datetime',
            'planned_pickup_at' => 'datetime',
            'planned_delivery_at' => 'datetime',
            'actual_pickup_at' => 'datetime',
            'actual_delivery_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'weight_kg' => 'decimal:3',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<ShipmentOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(ShipmentOrder::class, 'order_id');
    }

    /** @return HasOne<ShipmentAddress, $this> */
    public function pickupAddress(): HasOne
    {
        return $this->hasOne(ShipmentAddress::class)->where('kind', 'pickup');
    }

    /** @return HasOne<ShipmentAddress, $this> */
    public function deliveryAddress(): HasOne
    {
        return $this->hasOne(ShipmentAddress::class)->where('kind', 'delivery');
    }

    /** @return HasMany<ShipmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    /** @return HasMany<TrackingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TrackingEvent::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /** @return BelongsTo<Driver, $this> */
    public function currentDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'current_driver_id');
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function currentVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'current_vehicle_id');
    }

    /** @return BelongsTo<Trip, $this> */
    public function currentTrip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'current_trip_id');
    }

    /** @return HasMany<DeliveryEvidence, $this> */
    public function evidences(): HasMany
    {
        return $this->hasMany(DeliveryEvidence::class);
    }

    /** @return HasMany<ShipmentException, $this> */
    public function exceptions(): HasMany
    {
        return $this->hasMany(ShipmentException::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<Charge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /** @return HasMany<TripStop, $this> */
    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class);
    }
}
