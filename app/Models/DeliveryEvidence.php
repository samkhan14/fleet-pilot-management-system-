<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryEvidence extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'delivery_evidences';

    protected $fillable = [
        'organization_id', 'shipment_id', 'type', 'recipient_name', 'notes',
        'captured_at', 'actor_id', 'amends_id',
    ];

    protected function casts(): array
    {
        return ['captured_at' => 'datetime'];
    }

    /** @return HasMany<StoredFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(StoredFile::class, 'evidence_id');
    }
}
