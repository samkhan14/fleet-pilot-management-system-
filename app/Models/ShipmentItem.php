<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['shipment_id', 'description', 'quantity', 'weight_kg'];

    protected function casts(): array
    {
        return ['weight_kg' => 'decimal:3'];
    }
}
