<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentAddress extends Model
{
    protected $fillable = [
        'shipment_id', 'kind', 'line1', 'line2', 'city', 'region', 'postal_code',
        'country', 'contact_name', 'contact_phone', 'instructions',
    ];
}
