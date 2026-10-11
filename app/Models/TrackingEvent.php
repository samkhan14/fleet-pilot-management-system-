<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'shipment_id', 'type', 'status', 'message', 'reason',
        'actor_id', 'metadata', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
