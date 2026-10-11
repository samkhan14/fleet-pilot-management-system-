<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingToken extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'shipment_id', 'token_hash', 'expires_at', 'revoked_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
