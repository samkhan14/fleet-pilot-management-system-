<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'organization_id', 'shipment_id', 'driver_id', 'vehicle_id', 'doc_type',
        'visibility', 'title', 'created_by',
    ];

    /** @return HasMany<StoredFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(StoredFile::class);
    }
}
