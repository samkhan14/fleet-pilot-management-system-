import { Head, Link, router, usePage } from '@inertiajs/react';
import { StatusBadge, formatWhen } from '@/components/operations/status-badge';
import { dashboard } from '@/routes';

type Kpis = Record<string, number>;
type ShipmentRow = {
    id: number;
    shipment_number: string;
    status: string;
    priority: string;
    customer?: { name: string } | null;
    pickup_city?: string;
    delivery_city?: string;
    pickup_window_start?: string | null;
    delivery_window_end?: string | null;
};

export default function Dashboard({
    board,
    queue,
    filters = {},
    drivers = [],
    customers = [],
}: {
    board: null | {
        date: string;
        timezone: string;
        definitions: Record<string, string>;
        kpis: Kpis;
        workload: Array<{
            id: number;
            name: string;
            availability: string;
            shipments: number;
        }>;
    };
    queue: null | {
        data: ShipmentRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters?: Record<string, string>;
    drivers?: Array<{ id: number; name: string }>;
    customers?: Array<{ id: number; name: string }>;
}) {
    const { auth } = usePage().props;

    if (!board || !queue) {
        return (
            <div className="p-6">
                <Head title="Dashboard" />
                <h1 className="text-2xl font-semibold">No organization yet</h1>
                <p className="mt-2 max-w-lg text-sm text-muted-foreground">
                    This account is not attached to a carrier. Ask an owner to
                    add you, or sign in with a Harbor & Pine demo account.
                </p>
            </div>
        );
    }

    const cards: Array<[string, number | string, string]> = [
        [
            'Awaiting dispatch',
            board.kpis.awaiting_dispatch,
            'awaiting_dispatch',
        ],
        ['Pickups today', board.kpis.scheduled_pickups, 'scheduled_pickups'],
        ['Active', board.kpis.active, 'active'],
        ['Due today', board.kpis.deliveries_due, 'deliveries_due'],
        ['Delayed', board.kpis.delayed, 'delayed'],
        ['Failed attempts', board.kpis.failed_attempts, 'failed_attempts'],
        ['Unassigned', board.kpis.unassigned, 'unassigned'],
        ['Delivered', board.kpis.completed, 'completed'],
        ['Open exceptions', board.kpis.open_exceptions, 'open_exceptions'],
        [
            'Drivers available',
            `${board.kpis.drivers_available}/${board.kpis.drivers_total}`,
            'drivers_available',
        ],
        [
            'Vehicle use',
            `${Math.round(board.kpis.vehicle_utilization * 100)}%`,
            'vehicle_utilization',
        ],
    ];

    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title="Board" />
            <div className="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p className="text-xs tracking-[0.16em] text-primary uppercase">
                        {auth.organization?.name}
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Operations board
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {board.date} · {board.timezone}. Counts come from the
                        database, not the rows below.
                    </p>
                </div>
                <form
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        const data = new FormData(event.currentTarget);
                        router.get(
                            '/dashboard',
                            Object.fromEntries(data.entries()) as Record<
                                string,
                                string
                            >,
                            { preserveState: true },
                        );
                    }}
                >
                    <input
                        className="h-9 rounded-md border bg-card px-2 text-sm"
                        type="date"
                        name="date"
                        defaultValue={filters.date || board.date}
                        aria-label="Date"
                    />
                    <input
                        className="h-9 rounded-md border bg-card px-2 text-sm"
                        name="q"
                        placeholder="Shipment or reference"
                        defaultValue={filters.q || ''}
                        aria-label="Search"
                    />
                    <select
                        className="h-9 rounded-md border bg-card px-2 text-sm"
                        name="driver_id"
                        defaultValue={filters.driver_id || ''}
                        aria-label="Driver"
                    >
                        <option value="">All drivers</option>
                        {drivers.map((driver) => (
                            <option key={driver.id} value={driver.id}>
                                {driver.name}
                            </option>
                        ))}
                    </select>
                    <select
                        className="h-9 rounded-md border bg-card px-2 text-sm"
                        name="customer_id"
                        defaultValue={filters.customer_id || ''}
                        aria-label="Customer"
                    >
                        <option value="">All customers</option>
                        {customers.map((customer) => (
                            <option key={customer.id} value={customer.id}>
                                {customer.name}
                            </option>
                        ))}
                    </select>
                    <input
                        className="h-9 w-32 rounded-md border bg-card px-2 text-sm"
                        name="service_area"
                        placeholder="Area"
                        defaultValue={filters.service_area || ''}
                        aria-label="Service area"
                    />
                    <button
                        className="h-9 rounded-md bg-primary px-3 text-sm text-primary-foreground"
                        type="submit"
                    >
                        Filter
                    </button>
                </form>
            </div>
            <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map(([label, value, key]) => (
                    <article
                        key={key}
                        title={board.definitions[key]}
                        className="rounded-lg border bg-card px-3 py-2 shadow-card"
                    >
                        <p className="text-[11px] tracking-wide text-muted-foreground uppercase">
                            {label}
                        </p>
                        <p className="text-2xl font-semibold tabular-nums">
                            {value}
                        </p>
                    </article>
                ))}
            </div>
            <div className="grid gap-4 xl:grid-cols-[2fr_1fr]">
                <div className="overflow-hidden rounded-lg border bg-card">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-muted/70 text-xs tracking-wide text-muted-foreground uppercase">
                            <tr>
                                <th className="px-3 py-2">Shipment</th>
                                <th className="px-3 py-2">Status</th>
                                <th className="px-3 py-2">Lane</th>
                                <th className="px-3 py-2">Pickup</th>
                            </tr>
                        </thead>
                        <tbody>
                            {queue.data.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2">
                                        <Link
                                            href={`/shipments/${row.id}`}
                                            className="font-medium text-primary"
                                        >
                                            {row.shipment_number}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {row.customer?.name}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        <StatusBadge value={row.status} />
                                    </td>
                                    <td className="px-3 py-2">
                                        {row.pickup_city} → {row.delivery_city}
                                    </td>
                                    <td className="px-3 py-2 tabular-nums">
                                        {formatWhen(
                                            row.pickup_window_start,
                                            board.timezone,
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {queue.data.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-8 text-muted-foreground"
                                        colSpan={4}
                                    >
                                        No shipments match these filters.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <aside className="rounded-lg border bg-card p-3">
                    <h2 className="text-sm font-semibold">Driver workload</h2>
                    <p className="mb-3 text-xs text-muted-foreground">
                        Assigned, picked up, or in transit.
                    </p>
                    <ul className="space-y-2">
                        {board.workload.map((driver) => (
                            <li
                                key={driver.id}
                                className="flex items-center justify-between text-sm"
                            >
                                <span>
                                    {driver.name}
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        {driver.availability}
                                    </span>
                                </span>
                                <span className="tabular-nums">
                                    {driver.shipments}
                                </span>
                            </li>
                        ))}
                    </ul>
                </aside>
            </div>
        </div>
    );
}

Dashboard.layout = { breadcrumbs: [{ title: 'Board', href: dashboard() }] };
