<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'key', 'request_hash', 'response', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
