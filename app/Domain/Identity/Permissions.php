<?php

declare(strict_types=1);

namespace App\Domain\Identity;

final class Permissions
{
    /** @var list<string> */
    public const ALL = [
        'dashboard.read',
        'customers.read',
        'customers.write',
        'shipments.read',
        'shipments.write',
        'shipments.transition',
        'shipments.field',
        'shipments.close',
        'shipments.driver_action',
        'dispatch.manage',
        'drivers.read',
        'drivers.read_sensitive',
        'drivers.write',
        'vehicles.read',
        'vehicles.write',
        'trips.read',
        'trips.write',
        'exceptions.read',
        'exceptions.write',
        'exceptions.resolve',
        'documents.read',
        'documents.write',
        'evidence.read',
        'evidence.write',
        'finance.read',
        'finance.write',
        'reports.read',
        'tracking.manage',
        'notifications.read',
        'settings.manage',
        'members.manage',
    ];

    /** @var array<string, string> */
    public const STATUS_PERMISSION = [
        'ready_for_dispatch' => 'shipments.transition',
        'on_hold' => 'shipments.transition',
        'cancelled' => 'shipments.transition',
        'failed_delivery' => 'shipments.transition',
        'damaged_or_missing' => 'shipments.transition',
        'picked_up' => 'shipments.field',
        'in_transit' => 'shipments.field',
        'failed_pickup' => 'shipments.field',
        'delivered' => 'shipments.close',
        'returned_to_sender' => 'shipments.close',
    ];

    /** @var list<string> */
    public const DRIVER_ACTIONS = [
        'picked_up',
        'in_transit',
        'delivered',
        'failed_pickup',
        'failed_delivery',
    ];

    /** @var list<string> */
    public const DOCUMENT_TYPES = [
        'shipment_paperwork',
        'delivery_receipt',
        'packing_list',
        'bill_of_lading',
        'customer_instructions',
        'driver_document',
        'vehicle_document',
        'other',
    ];

    /**
     * @return array<string, list<string>>
     */
    public static function defaults(): array
    {
        $all = self::ALL;

        return [
            'owner' => $all,
            'admin' => $all,
            'operations_manager' => array_values(array_diff($all, ['members.manage'])),
            'dispatcher' => [
                'dashboard.read', 'customers.read', 'shipments.read', 'shipments.write',
                'shipments.transition', 'shipments.field', 'shipments.close', 'dispatch.manage',
                'drivers.read', 'vehicles.read', 'vehicles.write', 'trips.read', 'trips.write',
                'exceptions.read', 'exceptions.write', 'exceptions.resolve',
                'documents.read', 'documents.write', 'evidence.read', 'evidence.write',
                'reports.read', 'tracking.manage', 'notifications.read',
            ],
            'driver' => [
                'dashboard.read', 'shipments.read', 'shipments.driver_action', 'trips.read',
                'exceptions.read', 'exceptions.write', 'documents.read', 'evidence.read', 'evidence.write',
            ],
            'warehouse' => [
                'dashboard.read', 'customers.read', 'shipments.read', 'shipments.field',
                'documents.read', 'documents.write', 'evidence.read', 'evidence.write',
                'exceptions.read', 'exceptions.write', 'trips.read',
            ],
            'customer_service' => [
                'dashboard.read', 'customers.read', 'customers.write', 'shipments.read', 'shipments.write',
                'shipments.transition', 'exceptions.read', 'exceptions.write', 'exceptions.resolve',
                'documents.read', 'documents.write', 'evidence.read', 'tracking.manage',
                'notifications.read', 'reports.read',
            ],
            'finance' => [
                'dashboard.read', 'customers.read', 'shipments.read', 'finance.read', 'finance.write',
                'reports.read', 'documents.read', 'notifications.read',
            ],
            'customer' => [
                'dashboard.read', 'shipments.read', 'documents.read', 'evidence.read',
            ],
        ];
    }
}
