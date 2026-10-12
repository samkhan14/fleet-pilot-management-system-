<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAddress extends Model
{
    protected $fillable = [
        'customer_id', 'label', 'line1', 'line2', 'city', 'region', 'postal_code',
        'country', 'contact_name', 'contact_phone', 'instructions',
    ];
}
