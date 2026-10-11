<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Domain\Identity\OrganizationContext;
use App\Models\Shipment;
use App\Models\TrackingEvent;

final class RecordEvent
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function write(
        OrganizationContext $ctx,
        Shipment $shipment,
        string $type,
        string $message,
        ?string $status = null,
        ?string $reason = null,
        ?array $metadata = null,
    ): TrackingEvent {
        return TrackingEvent::query()->create([
            'organization_id' => $ctx->organization->id,
            'shipment_id' => $shipment->id,
            'type' => $type,
            'status' => $status,
            'message' => $message,
            'reason' => $reason,
            'actor_id' => $ctx->user->id,
            'metadata' => $metadata,
            'occurred_at' => now(),
        ]);
    }
}
