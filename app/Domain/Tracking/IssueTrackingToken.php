<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Domain\Identity\OrganizationContext;
use App\Models\Shipment;
use App\Models\TrackingToken;
use Illuminate\Support\Facades\Validator;

final class IssueTrackingToken
{
    /**
     * @return array{token: string, expires_at: string}
     */
    public function issue(OrganizationContext $ctx, Shipment $shipment): array
    {
        abort_unless($ctx->allows('tracking.manage'), 403);
        $settings = $ctx->organization->settings()->firstOrFail();
        $raw = bin2hex(random_bytes(32));
        $expires = now()->addHours((int) $settings->tracking_token_ttl_hours);
        TrackingToken::query()->create([
            'organization_id' => $ctx->organization->id,
            'shipment_id' => $shipment->id,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => $expires,
            'created_by' => $ctx->user->id,
        ]);

        return [
            'token' => $raw,
            'expires_at' => $expires->toIso8601String(),
        ];
    }

    public function revoke(OrganizationContext $ctx, TrackingToken $token): void
    {
        abort_unless($ctx->allows('tracking.manage'), 403);
        abort_unless($token->organization_id === $ctx->organization->id, 404);
        $token->forceFill(['revoked_at' => now()])->save();
    }

    public function findPublic(string $raw): ?TrackingToken
    {
        Validator::make(['token' => $raw], ['token' => ['required', 'string', 'min:20', 'max:200']])->validate();

        return TrackingToken::query()->where('token_hash', hash('sha256', $raw))->first();
    }
}
