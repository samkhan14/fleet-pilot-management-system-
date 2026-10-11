<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\NotificationJob;
use App\Models\OrganizationSetting;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\QueryException;

final class QueueShipmentNotification
{
    public function enqueue(Shipment $shipment, OrganizationSetting $settings, string $template, int $eventId): void
    {
        $shipment->loadMissing(['customer.contacts', 'currentDriver']);
        $enabled = match ($template) {
            'dispatch_confirmation', 'driver_assignment' => $settings->notify_on_assign,
            'delay' => $settings->notify_on_delay,
            'failed_delivery' => $settings->notify_on_failed,
            'delivered' => $settings->notify_on_delivered,
            'exception_opened' => true,
            default => false,
        };

        if (! $enabled) {
            return;
        }

        $recipients = $this->recipients($shipment, $template);
        foreach ($recipients as $recipient) {
            $dedupe = $shipment->id.':'.$template.':'.$eventId.':'.$recipient['channel'].':'.$recipient['to'];
            try {
                NotificationJob::query()->create([
                    'organization_id' => $shipment->organization_id,
                    'shipment_id' => $shipment->id,
                    'dedupe_key' => $dedupe,
                    'template' => $template,
                    'channel' => $recipient['channel'],
                    'recipient' => $recipient['to'],
                    'payload' => [
                        'shipment_number' => $shipment->shipment_number,
                        'subject' => $this->subject($template, $shipment->shipment_number),
                        'body' => $this->body($template, $shipment->shipment_number),
                    ],
                    'status' => 'pending',
                ]);
            } catch (QueryException $exception) {
                if (! str_contains($exception->getMessage(), 'notification_jobs_organization_id_dedupe_key_unique')) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * @return list<array{channel: string, to: string}>
     */
    private function recipients(Shipment $shipment, string $template): array
    {
        $list = [];
        $customerEmail = $shipment->customer?->email;
        if ($customerEmail && in_array($template, ['dispatch_confirmation', 'delay', 'failed_delivery', 'delivered'], true)) {
            $list[] = ['channel' => 'email', 'to' => $customerEmail];
        }

        if ($template === 'driver_assignment' && $shipment->currentDriver?->email) {
            $list[] = ['channel' => 'email', 'to' => $shipment->currentDriver->email];
        }

        if (in_array($template, ['failed_delivery', 'exception_opened'], true)) {
            $dispatchers = User::query()
                ->whereHas('memberships', function ($query) use ($shipment): void {
                    $query->where('organization_id', $shipment->organization_id)
                        ->whereIn('role', ['dispatcher', 'operations_manager', 'owner', 'admin']);
                })
                ->pluck('email');
            foreach ($dispatchers as $email) {
                $list[] = ['channel' => 'email', 'to' => $email];
            }
        }

        return $list;
    }

    private function subject(string $template, string $number): string
    {
        return match ($template) {
            'dispatch_confirmation' => "Shipment {$number} is scheduled",
            'driver_assignment' => "You are assigned to {$number}",
            'delay' => "Update for shipment {$number}",
            'failed_delivery' => "Delivery attempt for {$number}",
            'delivered' => "Shipment {$number} was delivered",
            'exception_opened' => "Exception on shipment {$number}",
            default => "Shipment {$number}",
        };
    }

    private function body(string $template, string $number): string
    {
        return match ($template) {
            'dispatch_confirmation' => "Shipment {$number} has been dispatched. This message does not include internal notes.",
            'driver_assignment' => "Shipment {$number} is on your board. Open FleetPilot for the stop details.",
            'delay' => "Shipment {$number} has a schedule change.",
            'failed_delivery' => "A delivery attempt for {$number} did not succeed.",
            'delivered' => "Shipment {$number} is marked delivered.",
            'exception_opened' => "An exception needs attention on shipment {$number}.",
            default => "Shipment {$number} was updated.",
        };
    }
}
