<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripStop extends Model
{
    protected $fillable = [
        'trip_id', 'shipment_id', 'stop_type', 'sequence', 'status',
        'planned_arrival', 'actual_arrival', 'actual_departure', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'planned_arrival' => 'datetime',
            'actual_arrival' => 'datetime',
            'actual_departure' => 'datetime',
        ];
    }

    /** @return BelongsTo<Shipment, $this> */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /** @return BelongsTo<Trip, $this> */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
