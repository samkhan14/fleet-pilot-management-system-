<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoredFile extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes',
        'checksum_sha256', 'kind', 'evidence_id', 'document_id', 'created_by',
    ];
}
