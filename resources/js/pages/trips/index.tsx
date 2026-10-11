import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export default function TripsIndex({
    trips,
    drivers,
    vehicles,
    ready,
}: {
    trips: {
        data: Array<{
            id: number;
            trip_number: string;
            status: string;
            stops_count: number;
            driver?: { name: string };
            vehicle?: { fleet_number: string };
        }>;
    };
    drivers: Array<{ id: number; name: string }>;
    vehicles: Array<{ id: number; fleet_number: string }>;
    ready: Array<{ id: number; shipment_number: string }>;
}) {
    const form = useForm({
        driver_id: drivers[0]?.id ?? '',
        vehicle_id: vehicles[0]?.id ?? '',
        shipment_ids: [] as number[],
        notes: '',
    });

    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title="Trips" />
            <h1 className="text-2xl font-semibold">Trips</h1>
            <p className="text-sm text-muted-foreground">
                Stop order is manual. Each selected shipment gets a pickup stop
                and then a delivery stop.
            </p>
            {trips.data.map((trip) => (
                <Link
                    key={trip.id}
                    href={`/trips/${trip.id}`}
                    className="rounded-lg border bg-card px-3 py-2 text-sm"
                >
                    <strong>{trip.trip_number}</strong> · {trip.status} ·{' '}
                    {trip.driver?.name} · {trip.vehicle?.fleet_number} ·{' '}
                    {trip.stops_count} stops
                </Link>
            ))}
            {drivers.length > 0 && (
                <form
                    className="grid gap-2 rounded-lg border bg-card p-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/trips');
                    }}
                >
                    <h2 className="text-sm font-semibold">Build a trip</h2>
                    <select
                        aria-label="Trip driver"
                        className="h-9 rounded-md border px-2"
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
                                {driver.name}
                            </option>
                        ))}
                    </select>
                    <select
                        aria-label="Trip vehicle"
                        className="h-9 rounded-md border px-2"
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
                                {vehicle.fleet_number}
                            </option>
                        ))}
                    </select>
                    <div className="grid gap-1 text-sm">
                        {ready.map((shipment) => (
                            <label
                                key={shipment.id}
                                className="flex items-center gap-2"
                            >
                                <input
                                    type="checkbox"
                                    checked={form.data.shipment_ids.includes(
                                        shipment.id,
                                    )}
                                    onChange={(event) => {
                                        const ids = event.target.checked
                                            ? [
                                                  ...form.data.shipment_ids,
                                                  shipment.id,
                                              ]
                                            : form.data.shipment_ids.filter(
                                                  (id) => id !== shipment.id,
                                              );
                                        form.setData('shipment_ids', ids);
                                    }}
                                />
                                {shipment.shipment_number}
                            </label>
                        ))}
                    </div>
                    <p className="text-sm text-destructive">
                        {form.errors.shipment_ids || form.errors.driver_id}
                    </p>
                    <Button type="submit">Create trip</Button>
                </form>
            )}
        </div>
    );
}
