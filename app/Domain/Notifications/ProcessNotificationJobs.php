<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\NotificationJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class ProcessNotificationJobs
{
    public function run(int $limit = 50): int
    {
        $jobs = NotificationJob::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('attempts', '<', 5)
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $sent = 0;
        foreach ($jobs as $job) {
            try {
                $outcome = $this->send($job);
                $job->forceFill([
                    'status' => $outcome,
                    'sent_at' => $outcome === 'sent' ? now() : null,
                    'attempts' => $job->attempts + 1,
                    'last_error' => $outcome === 'skipped' ? 'SMS webhook is not configured.' : null,
                ])->save();
                if ($outcome === 'sent') {
                    $sent++;
                }
            } catch (Throwable $exception) {
                $job->forceFill([
                    'status' => 'failed',
                    'attempts' => $job->attempts + 1,
                    'last_error' => mb_substr($exception->getMessage(), 0, 500),
                ])->save();
            }
        }

        return $sent;
    }

    private function send(NotificationJob $job): string
    {
        $provider = (string) config('fleetpilot.notification_provider', 'log');
        $payload = $job->payload;
        $subject = (string) ($payload['subject'] ?? 'FleetPilot notification');
        $body = (string) ($payload['body'] ?? '');

        if ($job->channel === 'sms') {
            $url = config('fleetpilot.sms_webhook_url');
            if (! is_string($url) || $url === '') {
                return 'skipped';
            }
            Http::timeout(5)->post($url, [
                'to' => $job->recipient,
                'body' => $body,
            ])->throw();

            return 'sent';
        }

        if ($provider === 'mail') {
            Mail::raw($body, function ($message) use ($job, $subject): void {
                $message->to($job->recipient)->subject($subject);
            });

            return 'sent';
        }

        Log::info('fleetpilot.notification', [
            'job_id' => $job->id,
            'to' => $job->recipient,
            'subject' => $subject,
            'body' => $body,
        ]);

        return 'sent';
    }
}
