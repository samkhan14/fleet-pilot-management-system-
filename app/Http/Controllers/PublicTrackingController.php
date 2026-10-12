<?php

namespace App\Http\Controllers;

use App\Domain\Tracking\IssueTrackingToken;
use App\Models\Organization;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Models\TrackingToken;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicTrackingController extends Controller
{
    public function show(Request $request, string $token, IssueTrackingToken $tokens): Response
    {
        $record = $tokens->findPublic($token);
        abort_unless($record instanceof TrackingToken, 404);
        abort_if($record->revoked_at !== null || $record->expires_at->isPast(), 410, 'This tracking link is no longer active.');

        $organization = Organization::query()->with('settings')->findOrFail($record->organization_id);
        abort_unless($organization->settings?->public_tracking_enabled, 404);

        $shipment = Shipment::query()->with(['pickupAddress', 'deliveryAddress'])->findOrFail($record->shipment_id);
        $events = $shipment->events()
            ->whereIn('type', [
                'created', 'ready_for_dispatch', 'dispatch_assigned', 'reassigned', 'pickup_completed',
                'in_transit', 'rescheduled', 'delivery_failed', 'delivered', 'cancelled', 'returned', 'exception_recorded',
            ])
            ->orderBy('occurred_at')
            ->get(['type', 'message', 'occurred_at', 'status']);

        return Inertia::render('track', [
            'shipment' => [
                'shipment_number' => $shipment->shipment_number,
                'status' => $shipment->status,
                'service_type' => $shipment->service_type,
                'pickup_city' => $shipment->pickupAddress?->city,
                'delivery_city' => $shipment->deliveryAddress?->city,
                'planned_pickup_at' => $shipment->planned_pickup_at?->toIso8601String(),
                'planned_delivery_at' => $shipment->planned_delivery_at?->toIso8601String(),
                'actual_pickup_at' => $shipment->actual_pickup_at?->toIso8601String(),
                'actual_delivery_at' => $shipment->actual_delivery_at?->toIso8601String(),
            ],
            'events' => $events->map(fn (TrackingEvent $event) => [
                'type' => $event->type,
                'message' => $event->message,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ]),
            'organization' => $organization->name,
            'timezone' => $organization->timezone,
        ]);
    }
}
