import { Head, router, useForm, usePage } from '@inertiajs/react';
import { StatusBadge } from '@/components/operations/status-badge';
import { Button } from '@/components/ui/button';

type Driver = {
    id: number;
    name: string;
    kind: string;
    availability: string;
    status: string;
    phone?: string | null;
    email?: string | null;
    license_number?: string;
    license_expires_on?: string | null;
};

export default function DriversIndex({ drivers }: { drivers: Driver[] }) {
    const { auth } = usePage().props;
    const canWrite =
        auth.role === 'owner' || auth.permissions?.includes('drivers.write');
    const form = useForm({
        name: '',
        phone: '',
        email: '',
        kind: 'employee',
        availability: 'available',
        license_number: '',
    });

    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title="Drivers" />
            <h1 className="text-2xl font-semibold">Drivers</h1>
            <p className="text-sm text-muted-foreground">
                License numbers are shown only to roles allowed to read
                sensitive driver records.
            </p>
            <div className="overflow-hidden rounded-lg border bg-card">
                {drivers.map((driver) => (
                    <div
                        key={driver.id}
                        className="flex flex-wrap items-center justify-between gap-2 border-b px-3 py-2 text-sm last:border-b-0"
                    >
                        <div>
                            <strong>{driver.name}</strong>
                            <span className="ml-2 text-muted-foreground">
                                {driver.kind} · {driver.phone}
                            </span>
                            {driver.license_number && (
                                <span className="ml-2">
                                    License {driver.license_number}
                                </span>
                            )}
                        </div>
                        <span className="flex flex-wrap items-center gap-2">
                            <StatusBadge value={driver.availability} />
                            <StatusBadge value={driver.status} />
                            {canWrite && (
                                <>
                                    <select
                                        aria-label={`${driver.name} availability`}
                                        className="h-8 rounded-md border px-2"
                                        value={driver.availability}
                                        onChange={(event) =>
                                            router.patch(
                                                `/drivers/${driver.id}`,
                                                {
                                                    availability:
                                                        event.target.value,
                                                    status: driver.status,
                                                },
                                            )
                                        }
                                    >
                                        <option value="available">
                                            available
                                        </option>
                                        <option value="unavailable">
                                            unavailable
                                        </option>
                                        <option value="off_duty">
                                            off duty
                                        </option>
                                    </select>
                                    <select
                                        aria-label={`${driver.name} status`}
                                        className="h-8 rounded-md border px-2"
                                        value={driver.status}
                                        onChange={(event) =>
                                            router.patch(
                                                `/drivers/${driver.id}`,
                                                {
                                                    availability:
                                                        driver.availability,
                                                    status: event.target.value,
                                                },
                                            )
                                        }
                                    >
                                        <option value="active">active</option>
                                        <option value="inactive">
                                            inactive
                                        </option>
                                    </select>
                                </>
                            )}
                        </span>
                    </div>
                ))}
            </div>
            <form
                className="grid gap-2 rounded-lg border bg-card p-3 md:grid-cols-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/drivers');
                }}
            >
                <input
                    required
                    aria-label="Driver name"
                    placeholder="Name"
                    className="h-9 rounded-md border px-2"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
                <input
                    aria-label="Driver phone"
                    placeholder="Phone"
                    className="h-9 rounded-md border px-2"
                    value={form.data.phone}
                    onChange={(event) =>
                        form.setData('phone', event.target.value)
                    }
                />
                <select
                    aria-label="Employment"
                    className="h-9 rounded-md border px-2"
                    value={form.data.kind}
                    onChange={(event) =>
                        form.setData('kind', event.target.value)
                    }
                >
                    <option value="employee">Employee</option>
                    <option value="contractor">Contractor</option>
                </select>
                <Button type="submit">Add driver</Button>
            </form>
        </div>
    );
}
