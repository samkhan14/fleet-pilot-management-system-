import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    StatusBadge,
    formatWhen,
    money,
} from '@/components/operations/status-badge';
import { Button } from '@/components/ui/button';

type Address = {
    line1: string;
    city: string;
    region?: string | null;
    postal_code?: string | null;
    country: string;
    contact_name?: string | null;
    contact_phone?: string | null;
    instructions?: string | null;
};
type Shipment = {
    id: number;
    shipment_number: string;
    status: string;
    description: string;
    handling_instructions?: string | null;
    internal_notes?: string | null;
    customer?: { name: string } | null;
    pickup: Address | null;
    delivery: Address | null;
    pickup_window_start?: string | null;
    pickup_window_end?: string | null;
    delivery_window_start?: string | null;
    delivery_window_end?: string | null;
    actual_pickup_at?: string | null;
    actual_delivery_at?: string | null;
    driver?: { name: string } | null;
    vehicle?: { fleet_number: string } | null;
    currency?: string;
    declared_value_cents?: number | null;
};
type EventRow = {
    id: number;
    type: string;
    message: string;
    reason?: string | null;
    occurred_at?: string | null;
};

function mapsHref(address: Address | null): string | null {
    if (!address) {
        return null;
    }
    const query = [
        address.line1,
        address.city,
        address.region,
        address.postal_code,
        address.country,
    ]
        .filter(Boolean)
        .join(', ');
    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(query)}`;
}

export default function ShipmentShow({
    shipment,
    events,
    evidence,
    exceptions,
    documents,
    charges,
    drivers,
    vehicles,
    older_cursor,
}: {
    shipment: Shipment;
    events: EventRow[];
    evidence: Array<{
        id: number;
        type: string;
        recipient_name?: string | null;
        notes?: string | null;
        captured_at?: string | null;
        amends_id?: number | null;
        files: Array<{ id: number; kind: string; original_name: string }>;
    }>;
    exceptions: Array<{
        id: number;
        category: string;
        status: string;
        description: string;
    }>;
    documents: Array<{
        id: number;
        title: string;
        doc_type: string;
        visibility: string;
        files: Array<{ id: number; original_name: string }>;
    }>;
    charges: Array<{
        id: number;
        description: string;
        kind: string;
        amount_cents: number;
        currency: string;
        billing_status: string;
        payment_status: string;
    }>;
    drivers: Array<{ id: number; name: string; availability: string }>;
    vehicles: Array<{
        id: number;
        fleet_number: string;
        availability: string;
        maintenance_status: string;
    }>;
    older_cursor?: number;
}) {
    const { flash, auth } = usePage().props as {
        flash?: { success?: string; tracking_token?: string };
        auth: {
            role?: string;
            permissions?: string[];
            organization?: { timezone?: string };
        };
    };
    const [reason, setReason] = useState('');
    const tz = auth.organization?.timezone;
    const canDispatch =
        auth.role === 'owner' || auth.permissions?.includes('dispatch.manage');
    const assign = useForm({
        driver_id: drivers[0]?.id ?? '',
        vehicle_id: vehicles[0]?.id ?? '',
        notes: '',
    });

    const transition = (to: string) => {
        router.post(
            `/shipments/${shipment.id}/transition`,
            { to, reason },
            { preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title={shipment.shipment_number} />
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-xs tracking-[0.16em] text-primary uppercase">
                        Shipment
                    </p>
                    <h1 className="text-2xl font-semibold">
                        {shipment.shipment_number}
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {shipment.customer?.name}
                    </p>
                </div>
                <StatusBadge value={shipment.status} />
            </div>
            {flash?.success && (
                <p className="rounded-md bg-emerald-100 px-3 py-2 text-sm text-emerald-950">
                    {flash.success}
                </p>
            )}
            {flash?.tracking_token && (
                <p className="rounded-md border bg-card px-3 py-2 text-sm">
                    Tracking link, shown once:{' '}
                    <a
                        className="text-primary"
                        href={`/track/${flash.tracking_token}`}
                    >
                        /track/{flash.tracking_token}
                    </a>
                </p>
            )}
            <div className="grid gap-3 lg:grid-cols-2">
                {[
                    {
                        label: 'Pickup',
                        address: shipment.pickup,
                        planned: shipment.pickup_window_start,
                        actual: shipment.actual_pickup_at,
                    },
                    {
                        label: 'Delivery',
                        address: shipment.delivery,
                        planned: shipment.delivery_window_end,
                        actual: shipment.actual_delivery_at,
                    },
                ].map((stop) => (
                    <section
                        key={stop.label}
                        className="rounded-lg border bg-card p-3"
                    >
                        <h2 className="text-sm font-semibold">{stop.label}</h2>
                        <p className="mt-1 text-sm">
                            {stop.address?.line1}, {stop.address?.city}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Planned {formatWhen(stop.planned, tz)} · Actual{' '}
                            {formatWhen(stop.actual, tz)}
                        </p>
                        {stop.address?.contact_phone && (
                            <p className="text-sm">
                                Contact {stop.address.contact_name}{' '}
                                {stop.address.contact_phone}
                            </p>
                        )}
                        {mapsHref(stop.address) && (
                            <a
                                className="text-sm text-primary"
                                href={mapsHref(stop.address) ?? '#'}
                                target="_blank"
                                rel="noreferrer"
                            >
                                Open in maps
                            </a>
                        )}
                    </section>
                ))}
            </div>
            <section className="rounded-lg border bg-card p-3 text-sm">
                <p>{shipment.description}</p>
                {shipment.handling_instructions && (
                    <p className="mt-2">
                        Handling: {shipment.handling_instructions}
                    </p>
                )}
                {shipment.internal_notes && (
                    <p className="mt-2 text-muted-foreground">
                        Internal: {shipment.internal_notes}
                    </p>
                )}
                <p className="mt-2">
                    Driver {shipment.driver?.name ?? 'unassigned'} · Vehicle{' '}
                    {shipment.vehicle?.fleet_number ?? '—'}
                </p>
                {shipment.declared_value_cents != null && (
                    <p>
                        Declared{' '}
                        {money(
                            shipment.declared_value_cents,
                            shipment.currency,
                        )}
                    </p>
                )}
            </section>
            {canDispatch && (
                <form
                    className="flex flex-wrap items-end gap-2 rounded-lg border bg-card p-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        assign.post(`/shipments/${shipment.id}/assign`);
                    }}
                >
                    <label className="text-sm">
                        Driver
                        <select
                            className="mt-1 block h-9 rounded-md border px-2"
                            value={assign.data.driver_id}
                            onChange={(event) =>
                                assign.setData(
                                    'driver_id',
                                    Number(event.target.value),
                                )
                            }
                        >
                            {drivers.map((driver) => (
                                <option key={driver.id} value={driver.id}>
                                    {driver.name} ({driver.availability})
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm">
                        Vehicle
                        <select
                            className="mt-1 block h-9 rounded-md border px-2"
                            value={assign.data.vehicle_id}
                            onChange={(event) =>
                                assign.setData(
                                    'vehicle_id',
                                    Number(event.target.value),
                                )
                            }
                        >
                            {vehicles.map((vehicle) => (
                                <option key={vehicle.id} value={vehicle.id}>
                                    {vehicle.fleet_number} (
                                    {vehicle.maintenance_status})
                                </option>
                            ))}
                        </select>
                    </label>
                    <Button disabled={assign.processing} type="submit">
                        Assign
                    </Button>
                    <p className="text-xs text-destructive">
                        {assign.errors.driver_id ||
                            assign.errors.vehicle_id ||
                            (
                                assign.errors as Record<
                                    string,
                                    string | undefined
                                >
                            ).status}
                    </p>
                </form>
            )}
            <div className="flex flex-wrap gap-2">
                <input
                    className="h-9 min-w-56 rounded-md border bg-card px-2 text-sm"
                    placeholder="Reason for exception or cancel"
                    value={reason}
                    onChange={(event) => setReason(event.target.value)}
                    aria-label="Reason"
                />
                {shipment.status === 'draft' && (
                    <Button
                        type="button"
                        onClick={() => transition('ready_for_dispatch')}
                    >
                        Ready for dispatch
                    </Button>
                )}
                {['assigned', 'failed_pickup'].includes(shipment.status) && (
                    <Button
                        type="button"
                        onClick={() => transition('picked_up')}
                    >
                        Picked up
                    </Button>
                )}
                {shipment.status === 'picked_up' && (
                    <Button
                        type="button"
                        onClick={() => transition('in_transit')}
                    >
                        In transit
                    </Button>
                )}
                {['in_transit', 'damaged_or_missing'].includes(
                    shipment.status,
                ) && (
                    <Button
                        type="button"
                        onClick={() => transition('delivered')}
                    >
                        Delivered
                    </Button>
                )}
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => transition('on_hold')}
                >
                    Hold
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => transition('failed_delivery')}
                >
                    Failed delivery
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    onClick={() => transition('cancelled')}
                >
                    Cancel
                </Button>
            </div>
            <section>
                <h2 className="mb-2 text-sm font-semibold">Tracking history</h2>
                <ol className="space-y-2 border-l pl-4">
                    {events.map((event) => (
                        <li key={event.id}>
                            <p className="text-sm">{event.message}</p>
                            <p className="text-xs text-muted-foreground">
                                {formatWhen(event.occurred_at, tz)} ·{' '}
                                {event.type}
                                {event.reason ? ` · ${event.reason}` : ''}
                            </p>
                        </li>
                    ))}
                </ol>
                {older_cursor && (
                    <button
                        className="mt-2 text-sm text-primary"
                        type="button"
                        onClick={() =>
                            router.get(
                                `/shipments/${shipment.id}`,
                                { before: older_cursor },
                                { preserveState: true },
                            )
                        }
                    >
                        Older events
                    </button>
                )}
            </section>
            <section className="grid gap-3 md:grid-cols-2">
                <div>
                    <h2 className="text-sm font-semibold">Evidence</h2>
                    {evidence.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No proof captured yet. Original records are not
                            overwritten; corrections are new amendments.
                        </p>
                    )}
                    {evidence.map((item) => (
                        <article
                            key={item.id}
                            className="mt-2 rounded-md border p-2 text-sm"
                        >
                            <p>
                                {item.type}
                                {item.amends_id
                                    ? ` amends #${item.amends_id}`
                                    : ''}{' '}
                                · {item.recipient_name}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {formatWhen(item.captured_at, tz)} ·{' '}
                                {item.notes}
                            </p>
                            {item.files.map((file) => (
                                <a
                                    key={file.id}
                                    className="block text-primary"
                                    href={`/files/${file.id}`}
                                >
                                    {file.original_name}
                                </a>
                            ))}
                        </article>
                    ))}
                </div>
                <div>
                    <h2 className="text-sm font-semibold">Exceptions</h2>
                    {exceptions.map((item) => (
                        <p key={item.id} className="mt-2 text-sm">
                            <StatusBadge value={item.status} /> {item.category}:{' '}
                            {item.description}
                        </p>
                    ))}
                    <h2 className="mt-4 text-sm font-semibold">Documents</h2>
                    {documents.map((document) => (
                        <p key={document.id} className="text-sm">
                            {document.title} · {document.visibility}{' '}
                            {document.files.map((file) => (
                                <a
                                    key={file.id}
                                    className="ml-2 text-primary"
                                    href={`/files/${file.id}`}
                                >
                                    {file.original_name}
                                </a>
                            ))}
                        </p>
                    ))}
                    {charges.length > 0 && (
                        <h2 className="mt-4 text-sm font-semibold">Charges</h2>
                    )}
                    {charges.map((charge) => (
                        <p key={charge.id} className="text-sm">
                            {charge.description}:{' '}
                            {money(
                                charge.kind === 'discount'
                                    ? -charge.amount_cents
                                    : charge.amount_cents,
                                charge.currency,
                            )}{' '}
                            · {charge.payment_status}
                        </p>
                    ))}
                </div>
            </section>
        </div>
    );
}
