import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBadge, formatWhen } from '@/components/operations/status-badge';
import { Button } from '@/components/ui/button';

type Row = {
    id: number;
    shipment_number: string;
    status: string;
    priority: string;
    customer?: { name: string } | null;
    pickup_city?: string;
    delivery_city?: string;
    pickup_window_start?: string | null;
};

export default function DispatchIndex({
    shipments,
    drivers,
    vehicles,
    filters,
}: {
    shipments: { data: Row[] };
    drivers: Array<{ id: number; name: string; availability: string }>;
    vehicles: Array<{
        id: number;
        fleet_number: string;
        availability: string;
        maintenance_status: string;
    }>;
    filters: Record<string, string>;
}) {
    const timezone = (
        usePage().props as {
            auth?: { organization?: { timezone?: string } | null };
        }
    ).auth?.organization?.timezone;
    const [open, setOpen] = useState<number | null>(null);
    const form = useForm({
        driver_id:
            drivers.find((driver) => driver.availability === 'available')?.id ??
            '',
        vehicle_id:
            vehicles.find(
                (vehicle) =>
                    vehicle.availability === 'available' &&
                    vehicle.maintenance_status !== 'out_of_service',
            )?.id ?? '',
        notes: '',
    });

    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title="Dispatch" />
            <div>
                <h1 className="text-2xl font-semibold">Dispatch</h1>
                <p className="text-sm text-muted-foreground">
                    Manual assignment only. FleetPilot does not optimize routes
                    or show live vehicle positions.
                </p>
            </div>
            <form
                className="flex flex-wrap gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get(
                        '/dispatch',
                        Object.fromEntries(
                            new FormData(event.currentTarget).entries(),
                        ) as Record<string, string>,
                        { preserveState: true },
                    );
                }}
            >
                <input
                    type="date"
                    name="date"
                    defaultValue={filters.date || ''}
                    aria-label="Pickup date"
                    className="h-9 rounded-md border bg-card px-2 text-sm"
                />
                <input
                    name="service_area"
                    defaultValue={filters.service_area || ''}
                    placeholder="Service area"
                    aria-label="Service area"
                    className="h-9 rounded-md border bg-card px-2 text-sm"
                />
                <select
                    name="priority"
                    defaultValue={filters.priority || ''}
                    aria-label="Priority"
                    className="h-9 rounded-md border bg-card px-2 text-sm"
                >
                    <option value="">Any priority</option>
                    {['low', 'normal', 'high', 'urgent'].map((priority) => (
                        <option key={priority}>{priority}</option>
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
                <table className="w-full min-w-[760px] text-left text-sm">
                    <thead className="text-xs tracking-wide text-muted-foreground uppercase">
                        <tr>
                            <th className="px-3 py-2">Shipment</th>
                            <th className="px-3 py-2">Status</th>
                            <th className="px-3 py-2">Lane</th>
                            <th className="px-3 py-2">Pickup</th>
                            <th className="px-3 py-2"></th>
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
                                        {row.customer?.name}
                                    </div>
                                </td>
                                <td className="px-3 py-2">
                                    <StatusBadge value={row.status} />{' '}
                                    <StatusBadge value={row.priority} />
                                </td>
                                <td className="px-3 py-2">
                                    {row.pickup_city} → {row.delivery_city}
                                </td>
                                <td className="px-3 py-2">
                                    {formatWhen(
                                        row.pickup_window_start,
                                        timezone,
                                    )}
                                </td>
                                <td className="px-3 py-2 text-right">
                                    <Button
                                        type="button"
                                        size="sm"
                                        onClick={() => setOpen(row.id)}
                                    >
                                        Assign
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {open && (
                <div
                    className="fixed inset-0 z-40 grid place-items-center bg-black/40 p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="assign-title"
                >
                    <form
                        className="w-full max-w-md rounded-lg bg-card p-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(`/shipments/${open}/assign`, {
                                onSuccess: () => setOpen(null),
                            });
                        }}
                    >
                        <h2 id="assign-title" className="text-lg font-semibold">
                            Assign shipment
                        </h2>
                        <label className="mt-3 block text-sm">
                            Driver
                            <select
                                className="mt-1 h-9 w-full rounded-md border px-2"
                                value={form.data.driver_id}
                                onChange={(event) =>
                                    form.setData(
                                        'driver_id',
                                        Number(event.target.value),
                                    )
                                }
                            >
                                {drivers.map((driver) => (
                                    <option key={driver.id} value={driver.id}>
                                        {driver.name} · {driver.availability}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="mt-3 block text-sm">
                            Vehicle
                            <select
                                className="mt-1 h-9 w-full rounded-md border px-2"
                                value={form.data.vehicle_id}
                                onChange={(event) =>
                                    form.setData(
                                        'vehicle_id',
                                        Number(event.target.value),
                                    )
                                }
                            >
                                {vehicles.map((vehicle) => (
                                    <option key={vehicle.id} value={vehicle.id}>
                                        {vehicle.fleet_number} ·{' '}
                                        {vehicle.maintenance_status}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="mt-3 block text-sm">
                            Notes
                            <textarea
                                className="mt-1 w-full rounded-md border px-2 py-1"
                                value={form.data.notes}
                                onChange={(event) =>
                                    form.setData('notes', event.target.value)
                                }
                            />
                        </label>
                        <p className="mt-2 text-sm text-destructive">
                            {form.errors.driver_id || form.errors.vehicle_id}
                        </p>
                        <div className="mt-4 flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(null)}
                            >
                                Close
                            </Button>
                            <Button disabled={form.processing} type="submit">
                                Save assignment
                            </Button>
                        </div>
                    </form>
                </div>
            )}
        </div>
    );
}
