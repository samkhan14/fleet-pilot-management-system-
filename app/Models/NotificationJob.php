<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationJob extends Model
{
    protected $fillable = [
        'organization_id', 'shipment_id', 'dedupe_key', 'template', 'channel', 'recipient',
        'payload', 'status', 'attempts', 'last_error', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
