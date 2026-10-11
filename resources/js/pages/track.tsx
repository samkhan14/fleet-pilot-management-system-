import { Head } from '@inertiajs/react';
import { StatusBadge, formatWhen } from '@/components/operations/status-badge';

export default function Track({
    shipment,
    events,
    organization,
    timezone,
}: {
    shipment: {
        shipment_number: string;
        status: string;
        service_type: string;
        pickup_city?: string;
        delivery_city?: string;
        planned_delivery_at?: string | null;
        actual_delivery_at?: string | null;
    };
    events: Array<{
        type: string;
        message: string;
        occurred_at?: string | null;
    }>;
    organization: string;
    timezone?: string;
}) {
    return (
        <main className="mx-auto min-h-dvh max-w-lg bg-background px-4 py-8 text-foreground">
            <Head title={shipment.shipment_number} />
            <p className="text-xs tracking-[0.16em] text-primary uppercase">
                FleetPilot · {organization}
            </p>
            <h1 className="mt-2 text-3xl font-semibold">
                {shipment.shipment_number}
            </h1>
            <div className="mt-3">
                <StatusBadge value={shipment.status} />
            </div>
            <p className="mt-4 text-sm">
                {shipment.pickup_city} to {shipment.delivery_city} ·{' '}
                {shipment.service_type}
            </p>
            <p className="text-sm text-muted-foreground">
                Planned delivery{' '}
                {formatWhen(shipment.planned_delivery_at, timezone)} · Actual{' '}
                {formatWhen(shipment.actual_delivery_at, timezone)}
            </p>
            <ol className="mt-6 space-y-3 border-l pl-4">
                {events.map((event, index) => (
                    <li key={`${event.type}-${index}`}>
                        <p className="text-sm">{event.message}</p>
                        <p className="text-xs text-muted-foreground">
                            {formatWhen(event.occurred_at, timezone)}
                        </p>
                    </li>
                ))}
            </ol>
            <p className="mt-8 text-xs text-muted-foreground">
                This link shows city, status, and customer-safe updates. It does
                not include internal notes, driver contact details, or other
                customers.
            </p>
        </main>
    );
}
