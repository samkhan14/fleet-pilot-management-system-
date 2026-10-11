<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assignment extends Model
{
    protected $fillable = [
        'organization_id', 'shipment_id', 'trip_id', 'driver_id', 'vehicle_id', 'status',
        'window_start', 'window_end', 'notes', 'created_by', 'cancelled_at', 'cancel_reason',
        'active_shipment_key', 'active_trip_key',
    ];

    protected static function booted(): void
    {
        static::saving(function (Assignment $assignment): void {
            $assignment->active_shipment_key = $assignment->status === 'active' ? $assignment->shipment_id : null;
            $assignment->active_trip_key = $assignment->status === 'active' ? $assignment->trip_id : null;
        });
    }

    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Driver, $this> */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    /** @return BelongsTo<Vehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
