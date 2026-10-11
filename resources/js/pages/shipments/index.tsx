import { Head, Link, router, usePage } from '@inertiajs/react';
import { StatusBadge, formatWhen } from '@/components/operations/status-badge';
import { Button } from '@/components/ui/button';

type Row = {
    id: number;
    shipment_number: string;
    customer_reference?: string | null;
    status: string;
    priority: string;
    service_type: string;
    customer?: { name: string } | null;
    pickup_city?: string;
    delivery_city?: string;
    planned_delivery_at?: string | null;
};

export default function ShipmentsIndex({
    shipments,
    filters,
}: {
    shipments: {
        data: Row[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: Record<string, string>;
}) {
    const timezone = (
        usePage().props as {
            auth?: { organization?: { timezone?: string } | null };
        }
    ).auth?.organization?.timezone;

    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title="Shipments" />
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Shipments</h1>
                <Button asChild>
                    <Link href="/shipments/create">New shipment</Link>
                </Button>
            </div>
            <form
                className="flex flex-wrap gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get(
                        '/shipments',
                        Object.fromEntries(
                            new FormData(event.currentTarget).entries(),
                        ) as Record<string, string>,
                        { preserveState: true },
                    );
                }}
            >
                <input
                    name="q"
                    defaultValue={filters.q || ''}
                    placeholder="Number or reference"
                    aria-label="Search shipments"
                    className="h-9 rounded-md border bg-card px-2 text-sm"
                />
                <select
                    name="status"
                    defaultValue={filters.status || ''}
                    aria-label="Status"
                    className="h-9 rounded-md border bg-card px-2 text-sm"
                >
                    <option value="">Any status</option>
                    {[
                        'draft',
                        'ready_for_dispatch',
                        'assigned',
                        'picked_up',
                        'in_transit',
                        'delivered',
                        'failed_delivery',
                        'on_hold',
                        'cancelled',
                    ].map((status) => (
                        <option key={status} value={status}>
                            {status}
                        </option>
                    ))}
                </select>
                <button
                    className="h-9 rounded-md bg-primary px-3 text-sm text-primary-foreground"
                    type="submit"
                >
                    Filter
                </button>
            </form>
            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full min-w-[720px] text-left text-sm">
                    <thead className="text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-3 py-2">Number</th>
                            <th className="px-3 py-2">Customer</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Lane</th>
                            <th className="px-3 py-2">Planned delivery</th>
                        </tr>
                    </thead>
                    <tbody>
                        {shipments.data.map((row) => (
                            <tr key={row.id} className="border-t">
                                <td className="px-3 py-2">
                                    <Link
                                        className="font-medium text-primary"
                                        href={`/shipments/${row.id}`}
                                    >
                                        {row.shipment_number}
                                    </Link>
                                    <div className="text-xs text-muted-foreground">
                                        {row.customer_reference}
                                    </div>
                                </td>
                                <td className="px-3 py-2">
                                    {row.customer?.name}
                                </td>
                                <td className="px-3 py-2">
                                    <StatusBadge value={row.status} />
                                </td>
                                <td className="px-3 py-2">
                                    {row.pickup_city} → {row.delivery_city}
                                </td>
                                <td className="px-3 py-2">
                                    {formatWhen(
                                        row.planned_delivery_at,
                                        timezone,
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
