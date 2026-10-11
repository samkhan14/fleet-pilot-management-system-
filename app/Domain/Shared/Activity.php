<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Identity\OrganizationContext;
use App\Models\ActivityLog;

final class Activity
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(OrganizationContext $ctx, string $action, string $entityType, int $entityId, ?array $metadata = null): void
    {
        ActivityLog::query()->create([
            'organization_id' => $ctx->organization->id,
            'actor_id' => $ctx->user->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
