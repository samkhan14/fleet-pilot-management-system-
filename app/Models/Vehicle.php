<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'organization_id', 'fleet_number', 'registration', 'vehicle_type', 'capacity_kg',
        'availability', 'status', 'maintenance_status', 'notes',
    ];

    protected function casts(): array
    {
        return ['capacity_kg' => 'decimal:2'];
    }
}
