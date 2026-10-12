<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSetting extends Model
{
    protected $fillable = [
        'organization_id',
        'allow_overlapping_assignments',
        'require_pod_photo',
        'require_pod_signature',
        'require_recipient_name',
        'tracking_token_ttl_hours',
        'public_tracking_enabled',
        'notify_on_assign',
        'notify_on_delay',
        'notify_on_failed',
        'notify_on_delivered',
        'document_types',
        'tax_rate_bps',
    ];

    protected function casts(): array
    {
        return [
            'allow_overlapping_assignments' => 'boolean',
            'require_pod_photo' => 'boolean',
            'require_pod_signature' => 'boolean',
            'require_recipient_name' => 'boolean',
            'public_tracking_enabled' => 'boolean',
            'notify_on_assign' => 'boolean',
            'notify_on_delay' => 'boolean',
            'notify_on_failed' => 'boolean',
            'notify_on_delivered' => 'boolean',
            'document_types' => 'array',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
