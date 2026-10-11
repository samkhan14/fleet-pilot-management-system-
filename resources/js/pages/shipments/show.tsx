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

function localInput(
    value: string | null | undefined,
    timeZone?: string,
): string {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timeZone || 'UTC',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(date);
    const pick = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((part) => part.type === type)?.value ?? '';

    return `${pick('year')}-${pick('month')}-${pick('day')}T${pick('hour')}:${pick('minute')}`;
}

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
    next_statuses,
    can,
    document_types,
    exception_categories,
    tracking_tokens,
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
    next_statuses: Array<{
        value: string;
        label: string;
        reason_required: boolean;
    }>;
    can: {
        dispatch: boolean;
        reschedule: boolean;
        tracking: boolean;
        exceptions: boolean;
        documents: boolean;
    };
    document_types: string[];
    exception_categories: string[];
    tracking_tokens: Array<{
        id: number;
        expires_at: string;
        revoked_at?: string | null;
    }>;
}) {
    const { flash, auth, errors } = usePage().props as {
        flash?: { success?: string; tracking_token?: string };
        errors?: Record<string, string>;
        auth: {
            role?: string;
            permissions?: string[];
            organization?: { timezone?: string };
        };
    };
    const [reason, setReason] = useState('');
    const [customerMessage, setCustomerMessage] = useState('');
    const tz = auth.organization?.timezone;
    const canDispatch =
        auth.role === 'owner' || auth.permissions?.includes('dispatch.manage');
    const assign = useForm({
        driver_id: drivers[0]?.id ?? '',
        vehicle_id: vehicles[0]?.id ?? '',
        notes: '',
    });
    const release = useForm({ reason: '' });
    const schedule = useForm({
        pickup_window_start: localInput(shipment.pickup_window_start, tz),
        pickup_window_end: localInput(shipment.pickup_window_end, tz),
        delivery_window_start: localInput(shipment.delivery_window_start, tz),
        delivery_window_end: localInput(shipment.delivery_window_end, tz),
        reason: '',
    });
    const exception = useForm({
        category: exception_categories[0] ?? 'delayed_delivery',
        severity: 'medium',
        description: '',
    });
    const upload = useForm<{
        shipment_id: number;
        title: string;
        doc_type: string;
        visibility: string;
        file: File | null;
    }>({
        shipment_id: shipment.id,
        title: '',
        doc_type: document_types[0] ?? 'other',
        visibility: 'internal',
        file: null,
    });

    const transition = (to: string) => {
        router.post(
            `/shipments/${shipment.id}/transition`,
            { to, reason, customer_message: customerMessage },
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
            {canDispatch &&
                ['ready_for_dispatch', 'failed_pickup', 'assigned'].includes(
                    shipment.status,
                ) && (
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
            {next_statuses.length > 0 && (
                <div className="flex flex-wrap items-end gap-2">
                    <input
                        className="h-9 min-w-56 rounded-md border bg-card px-2 text-sm"
                        placeholder="Reason, when the change requires one"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        aria-label="Reason"
                    />
                    <input
                        className="h-9 min-w-56 rounded-md border bg-card px-2 text-sm"
                        placeholder="Customer-safe message"
                        value={customerMessage}
                        onChange={(event) =>
                            setCustomerMessage(event.target.value)
                        }
                        aria-label="Customer-safe message"
                    />
                    {next_statuses.map((status) => (
                        <Button
                            key={status.value}
                            type="button"
                            variant={
                                status.value === 'cancelled'
                                    ? 'destructive'
                                    : status.reason_required
                                      ? 'outline'
                                      : 'default'
                            }
                            onClick={() => transition(status.value)}
                        >
                            {status.label}
                        </Button>
                    ))}
                    {errors?.status && (
                        <p className="text-sm text-destructive">
                            {errors.status}
                        </p>
                    )}
                    {errors?.reason && (
                        <p className="text-sm text-destructive">
                            {errors.reason}
                        </p>
                    )}
                    {errors?.evidence && (
                        <p className="text-sm text-destructive">
                            {errors.evidence}
                        </p>
                    )}
                </div>
            )}
            {shipment.status === 'assigned' && can.dispatch && (
                <form
                    className="flex flex-wrap items-end gap-2 rounded-lg border bg-card p-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        release.post(`/shipments/${shipment.id}/unassign`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <label className="text-sm">
                        Unassign reason
                        <input
                            required
                            className="mt-1 block h-9 rounded-md border px-2"
                            value={release.data.reason}
                            onChange={(event) =>
                                release.setData('reason', event.target.value)
                            }
                        />
                    </label>
                    <Button type="submit" variant="outline">
                        Unassign
                    </Button>
                </form>
            )}
            {can.reschedule && (
                <form
                    className="grid gap-2 rounded-lg border bg-card p-3 md:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        schedule.post(`/shipments/${shipment.id}/reschedule`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <h2 className="text-sm font-semibold md:col-span-2">
                        Reschedule
                    </h2>
                    {(
                        [
                            ['pickup_window_start', 'Pickup start'],
                            ['pickup_window_end', 'Pickup end'],
                            ['delivery_window_start', 'Delivery start'],
                            ['delivery_window_end', 'Delivery end'],
                        ] as const
                    ).map(([field, label]) => (
                        <label key={field} className="text-sm">
                            {label}
                            <input
                                required
                                type="datetime-local"
                                className="mt-1 block h-9 w-full rounded-md border px-2"
                                value={schedule.data[field]}
                                onChange={(event) =>
                                    schedule.setData(field, event.target.value)
                                }
                            />
                        </label>
                    ))}
                    <label className="text-sm md:col-span-2">
                        Reason
                        <input
                            required
                            className="mt-1 block h-9 w-full rounded-md border px-2"
                            value={schedule.data.reason}
                            onChange={(event) =>
                                schedule.setData('reason', event.target.value)
                            }
                        />
                    </label>
                    <Button type="submit">Save schedule</Button>
                    <p className="text-sm text-destructive">
                        {schedule.errors.reason ||
                            (
                                schedule.errors as Record<
                                    string,
                                    string | undefined
                                >
                            ).status}
                    </p>
                </form>
            )}
            {can.tracking && (
                <section className="rounded-lg border bg-card p-3 text-sm">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="font-semibold">Public tracking links</h2>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    `/shipments/${shipment.id}/tracking-token`,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Issue link
                        </Button>
                    </div>
                    <p className="mt-1 text-muted-foreground">
                        The raw link is shown once. Revoking it returns 410 to
                        anyone who still has it.
                    </p>
                    {tracking_tokens.map((token) => (
                        <p
                            key={token.id}
                            className="mt-2 flex flex-wrap items-center gap-2"
                        >
                            Expires {formatWhen(token.expires_at, tz)}
                            {token.revoked_at
                                ? ` · revoked ${formatWhen(token.revoked_at, tz)}`
                                : ''}
                            {!token.revoked_at && (
                                <button
                                    className="text-primary"
                                    type="button"
                                    onClick={() =>
                                        router.post(
                                            `/shipments/${shipment.id}/tracking-tokens/${token.id}/revoke`,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    Revoke
                                </button>
                            )}
                        </p>
                    ))}
                </section>
            )}
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
                    {can.exceptions && (
                        <form
                            className="mt-2 grid gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                exception.post(
                                    `/shipments/${shipment.id}/exceptions`,
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <select
                                aria-label="Exception category"
                                className="h-9 rounded-md border px-2"
                                value={exception.data.category}
                                onChange={(event) =>
                                    exception.setData(
                                        'category',
                                        event.target.value,
                                    )
                                }
                            >
                                {exception_categories.map((category) => (
                                    <option key={category} value={category}>
                                        {category.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </select>
                            <select
                                aria-label="Severity"
                                className="h-9 rounded-md border px-2"
                                value={exception.data.severity}
                                onChange={(event) =>
                                    exception.setData(
                                        'severity',
                                        event.target.value,
                                    )
                                }
                            >
                                {['low', 'medium', 'high', 'critical'].map(
                                    (severity) => (
                                        <option key={severity}>
                                            {severity}
                                        </option>
                                    ),
                                )}
                            </select>
                            <textarea
                                required
                                aria-label="Exception description"
                                className="min-h-20 rounded-md border px-2 py-1"
                                value={exception.data.description}
                                onChange={(event) =>
                                    exception.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                            />
                            <Button type="submit">Report exception</Button>
                        </form>
                    )}
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
                    {can.documents && (
                        <form
                            className="mt-2 grid gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                upload.post('/documents', {
                                    forceFormData: true,
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <input
                                required
                                aria-label="Document title"
                                placeholder="Title"
                                className="h-9 rounded-md border px-2"
                                value={upload.data.title}
                                onChange={(event) =>
                                    upload.setData('title', event.target.value)
                                }
                            />
                            <select
                                aria-label="Document type"
                                className="h-9 rounded-md border px-2"
                                value={upload.data.doc_type}
                                onChange={(event) =>
                                    upload.setData(
                                        'doc_type',
                                        event.target.value,
                                    )
                                }
                            >
                                {document_types.map((type) => (
                                    <option key={type} value={type}>
                                        {type.replaceAll('_', ' ')}
                                    </option>
                                ))}
                            </select>
                            <select
                                aria-label="Visibility"
                                className="h-9 rounded-md border px-2"
                                value={upload.data.visibility}
                                onChange={(event) =>
                                    upload.setData(
                                        'visibility',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="internal">Internal</option>
                                <option value="customer_visible">
                                    Customer visible
                                </option>
                                <option value="sensitive">Sensitive</option>
                            </select>
                            <input
                                required
                                aria-label="Document file"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                onChange={(event) =>
                                    upload.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                            <Button type="submit">Upload document</Button>
                            <p className="text-sm text-destructive">
                                {upload.errors.file || upload.errors.title}
                            </p>
                        </form>
                    )}
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
