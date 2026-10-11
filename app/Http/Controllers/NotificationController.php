<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesOrganization;
use App\Models\NotificationJob;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    use UsesOrganization;

    public function index(): Response
    {
        $ctx = $this->authorizePermission('notifications.read');
        $jobs = NotificationJob::query()
            ->where('organization_id', $ctx->organization->id)
            ->latest('id')
            ->paginate(30);

        return Inertia::render('notifications/index', [
            'jobs' => $jobs->through(fn (NotificationJob $job): array => [
                'id' => $job->id,
                'shipment_id' => $job->shipment_id,
                'template' => $job->template,
                'channel' => $job->channel,
                'recipient' => $job->recipient,
                'status' => $job->status,
                'attempts' => $job->attempts,
                'last_error' => $job->last_error,
                'sent_at' => $job->sent_at?->toIso8601String(),
            ]),
            'provider' => (string) config('fleetpilot.notification_provider', 'log'),
        ]);
    }
}
