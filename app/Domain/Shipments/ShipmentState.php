<?php

declare(strict_types=1);

namespace App\Domain\Shipments;

use Illuminate\Validation\ValidationException;

final class ShipmentState
{
    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        'draft' => ['ready_for_dispatch', 'cancelled'],
        'ready_for_dispatch' => ['on_hold', 'cancelled'],
        'assigned' => ['picked_up', 'failed_pickup', 'on_hold', 'cancelled'],
        'picked_up' => ['in_transit', 'failed_delivery', 'on_hold', 'damaged_or_missing'],
        'in_transit' => ['delivered', 'failed_delivery', 'on_hold', 'damaged_or_missing', 'returned_to_sender'],
        'failed_pickup' => ['picked_up', 'ready_for_dispatch', 'on_hold', 'cancelled'],
        'failed_delivery' => ['in_transit', 'returned_to_sender', 'on_hold', 'cancelled'],
        'on_hold' => ['ready_for_dispatch', 'cancelled'],
        'damaged_or_missing' => ['in_transit', 'delivered', 'returned_to_sender', 'on_hold', 'cancelled'],
        'delivered' => [],
        'returned_to_sender' => [],
        'cancelled' => [],
    ];

    /** @var list<string> */
    public const REASON_REQUIRED = [
        'failed_pickup',
        'failed_delivery',
        'on_hold',
        'damaged_or_missing',
        'returned_to_sender',
        'cancelled',
    ];

    /** @var list<string> */
    public const TERMINAL = ['delivered', 'returned_to_sender', 'cancelled'];

    public static function assert(string $from, string $to): void
    {
        $allowed = self::TRANSITIONS[$from] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot move a shipment from {$from} to {$to}.",
            ]);
        }
    }
}
