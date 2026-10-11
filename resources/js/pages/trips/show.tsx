import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

type Stop = {
    id: number;
    sequence: number;
    stop_type: string;
    status: string;
    shipment?: { shipment_number: string };
};

export default function TripShow({
    trip,
}: {
    trip: {
        id: number;
        trip_number: string;
        status: string;
        notes?: string;
        stops: Stop[];
    };
}) {
    const [order, setOrder] = useState(trip.stops.map((stop) => stop.id));
    const move = (index: number, direction: number) => {
        const next = [...order];
        const target = index + direction;
        if (target < 0 || target >= next.length) {
            return;
        }
        [next[index], next[target]] = [next[target], next[index]];
        setOrder(next);
    };

    return (
        <div className="flex flex-col gap-3 p-4">
            <Head title={trip.trip_number} />
            <h1 className="text-2xl font-semibold">{trip.trip_number}</h1>
            <p className="text-sm text-muted-foreground">
                {trip.status}. Shipment status stays authoritative; stop status
                is updated in the same save.
            </p>
            <ol className="space-y-2">
                {order.map((id, index) => {
                    const stop = trip.stops.find((item) => item.id === id);
                    if (!stop) {
                        return null;
                    }
                    return (
                        <li
                            key={id}
                            className="flex items-center justify-between rounded-md border bg-card px-3 py-2 text-sm"
                        >
                            <span>
                                {index + 1}. {stop.stop_type} ·{' '}
                                {stop.shipment?.shipment_number} · {stop.status}
                            </span>
                            <span className="flex gap-2">
                                <button
                                    type="button"
                                    onClick={() => move(index, -1)}
                                >
                                    Up
                                </button>
                                <button
                                    type="button"
                                    onClick={() => move(index, 1)}
                                >
                                    Down
                                </button>
                            </span>
                        </li>
                    );
                })}
            </ol>
            <Button
                type="button"
                onClick={() =>
                    router.post(`/trips/${trip.id}/reorder`, { stops: order })
                }
            >
                Save stop order
            </Button>
        </div>
    );
}
