<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PermissionGrant extends Model
{
    public $timestamps = false;

    protected $fillable = ['organization_id', 'role', 'permission'];
}
