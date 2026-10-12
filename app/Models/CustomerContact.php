<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerContact extends Model
{
    protected $fillable = [
        'customer_id', 'name', 'email', 'phone', 'role_title', 'authorized',
    ];

    protected function casts(): array
    {
        return ['authorized' => 'boolean'];
    }
}
