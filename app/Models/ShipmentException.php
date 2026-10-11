<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentException extends Model
{
    /** @var list<string> */
    public const CATEGORIES = [
        'delayed_pickup',
        'delayed_delivery',
        'failed_delivery',
        'incorrect_address',
        'damaged_goods',
        'missing_goods',
        'vehicle_breakdown',
        'driver_unavailable',
        'customer_cancellation',
    ];

    protected $fillable = [
        'organization_id', 'shipment_id', 'trip_id', 'category', 'severity', 'status',
        'description', 'resolution_notes', 'reported_by', 'owner_id', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
