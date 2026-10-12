import { Head, router } from '@inertiajs/react';
import { money } from '@/components/operations/status-badge';

export default function ReportsIndex({
    report,
}: {
    report: {
        from: string;
        to: string;
        timezone: string;
        shipments_by_status: Record<string, number>;
        on_time_delivery_rate: number | null;
        delivered_count: number;
        failed_delivery_rate: number | null;
        average_transit_minutes: number | null;
        exceptions_by_category: Record<string, number>;
        charges: { net_cents: number; paid_cents: number; currency: string };
        by_customer: Array<{ name: string; total: number }>;
        definitions: Record<string, string>;
    };
}) {
    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title="Reports" />
            <h1 className="text-2xl font-semibold">Operational report</h1>
            <p className="text-sm text-muted-foreground">
                {report.from} to {report.to} in {report.timezone}. Cancelled
                shipments are left out of the rates.
            </p>
            <form
                className="flex gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get(
                        '/reports',
                        Object.fromEntries(
                            new FormData(event.currentTarget).entries(),
                        ) as Record<string, string>,
                    );
                }}
            >
                <input
                    type="date"
                    name="from"
                    defaultValue={report.from}
                    aria-label="From"
                    className="h-9 rounded-md border px-2"
                />
                <input
                    type="date"
                    name="to"
                    defaultValue={report.to}
                    aria-label="To"
                    className="h-9 rounded-md border px-2"
                />
                <button
                    className="h-9 rounded-md bg-primary px-3 text-sm text-primary-foreground"
                    type="submit"
                >
                    Run
                </button>
            </form>
            <div className="grid gap-3 md:grid-cols-3">
                <article
                    className="rounded-lg border bg-card p-3"
                    title={report.definitions.on_time_delivery}
                >
                    <p className="text-xs text-muted-foreground uppercase">
                        On-time delivery
                    </p>
                    <p className="text-2xl font-semibold">
                        {report.on_time_delivery_rate === null
                            ? '—'
                            : `${Math.round(report.on_time_delivery_rate * 100)}%`}
                    </p>
                    <p className="text-xs">
                        {report.delivered_count} delivered
                    </p>
                </article>
                <article
                    className="rounded-lg border bg-card p-3"
                    title={report.definitions.failed_delivery}
                >
                    <p className="text-xs text-muted-foreground uppercase">
                        Failed delivery rate
                    </p>
                    <p className="text-2xl font-semibold">
                        {report.failed_delivery_rate === null
                            ? '—'
                            : `${Math.round(report.failed_delivery_rate * 100)}%`}
                    </p>
                </article>
                <article
                    className="rounded-lg border bg-card p-3"
                    title={report.definitions.revenue}
                >
                    <p className="text-xs text-muted-foreground uppercase">
                        Net charges
                    </p>
                    <p className="text-2xl font-semibold">
                        {money(
                            report.charges.net_cents,
                            report.charges.currency,
                        )}
                    </p>
                    <p className="text-xs">
                        Paid{' '}
                        {money(
                            report.charges.paid_cents,
                            report.charges.currency,
                        )}
                    </p>
                </article>
            </div>
            <p className="text-sm">
                Average transit {report.average_transit_minutes ?? '—'} minutes.
            </p>
            <section>
                <h2 className="text-sm font-semibold">
                    Created in period, by current status
                </h2>
                <ul className="text-sm">
                    {Object.entries(report.shipments_by_status).map(
                        ([status, total]) => (
                            <li key={status}>
                                {status}: {total}
                            </li>
                        ),
                    )}
                </ul>
            </section>
            <section>
                <h2 className="text-sm font-semibold">Customers</h2>
                <ul className="text-sm">
                    {report.by_customer.map((row) => (
                        <li key={row.name}>
                            {row.name}: {row.total}
                        </li>
                    ))}
                </ul>
            </section>
        </div>
    );
}
