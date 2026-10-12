<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Driver extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'phone', 'email', 'kind', 'availability', 'status',
        'license_number', 'license_expires_on', 'notes',
    ];

    protected function casts(): array
    {
        return ['license_expires_on' => 'date'];
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
