import { Head, router, useForm } from '@inertiajs/react';
import { StatusBadge } from '@/components/operations/status-badge';
import { Button } from '@/components/ui/button';

type Vehicle = {
    id: number;
    fleet_number: string;
    registration?: string | null;
    vehicle_type: string;
    capacity_kg?: string | null;
    availability: string;
    status: string;
    maintenance_status: string;
};

export default function VehiclesIndex({ vehicles }: { vehicles: Vehicle[] }) {
    const form = useForm({
        fleet_number: '',
        registration: '',
        vehicle_type: 'van',
        capacity_kg: '',
        availability: 'available',
    });

    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title="Vehicles" />
            <h1 className="text-2xl font-semibold">Vehicles</h1>
            <div className="overflow-hidden rounded-lg border bg-card">
                {vehicles.map((vehicle) => (
                    <div
                        key={vehicle.id}
                        className="flex flex-wrap items-center justify-between gap-2 border-b px-3 py-2 text-sm"
                    >
                        <span>
                            <strong>{vehicle.fleet_number}</strong> ·{' '}
                            {vehicle.vehicle_type} ·{' '}
                            {vehicle.capacity_kg ?? '—'} kg
                        </span>
                        <span className="flex items-center gap-2">
                            <StatusBadge value={vehicle.maintenance_status} />
                            <button
                                className="text-primary"
                                type="button"
                                onClick={() =>
                                    router.patch(`/vehicles/${vehicle.id}`, {
                                        availability:
                                            vehicle.availability === 'available'
                                                ? 'unavailable'
                                                : 'available',
                                        status: vehicle.status,
                                        maintenance_status:
                                            vehicle.maintenance_status,
                                    })
                                }
                            >
                                Toggle availability
                            </button>
                        </span>
                    </div>
                ))}
            </div>
            <form
                className="grid gap-2 md:grid-cols-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/vehicles');
                }}
            >
                <input
                    required
                    aria-label="Fleet number"
                    placeholder="Fleet number"
                    className="h-9 rounded-md border px-2"
                    value={form.data.fleet_number}
                    onChange={(event) =>
                        form.setData('fleet_number', event.target.value)
                    }
                />
                <input
                    aria-label="Registration"
                    placeholder="Registration"
                    className="h-9 rounded-md border px-2"
                    value={form.data.registration}
                    onChange={(event) =>
                        form.setData('registration', event.target.value)
                    }
                />
                <select
                    aria-label="Type"
                    className="h-9 rounded-md border px-2"
                    value={form.data.vehicle_type}
                    onChange={(event) =>
                        form.setData('vehicle_type', event.target.value)
                    }
                >
                    {[
                        'van',
                        'box_truck',
                        'tractor',
                        'pickup',
                        'cargo_bike',
                        'other',
                    ].map((type) => (
                        <option key={type}>{type}</option>
                    ))}
                </select>
                <Button type="submit">Add vehicle</Button>
            </form>
        </div>
    );
}
