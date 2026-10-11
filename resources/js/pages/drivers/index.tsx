import { Head, useForm } from '@inertiajs/react';
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
                        <span className="flex gap-2">
                            <StatusBadge value={driver.availability} />
                            <StatusBadge value={driver.status} />
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
