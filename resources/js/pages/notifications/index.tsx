import { Head } from '@inertiajs/react';
import { StatusBadge, formatWhen } from '@/components/operations/status-badge';

export default function NotificationsIndex({
    jobs,
    provider,
}: {
    jobs: {
        data: Array<{
            id: number;
            shipment_id?: number | null;
            template: string;
            channel: string;
            recipient: string;
            status: string;
            attempts: number;
            last_error?: string | null;
            sent_at?: string | null;
        }>;
    };
    provider: string;
}) {
    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title="Notifications" />
            <h1 className="text-2xl font-semibold">Notifications</h1>
            <p className="text-sm text-muted-foreground">
                Provider: {provider}. A failed send does not roll back the
                shipment. SMS stays skipped until a webhook URL is configured.
            </p>
            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-left text-sm">
                    <thead className="text-xs text-muted-foreground uppercase">
                        <tr>
                            <th className="px-3 py-2">Notice</th>
                            <th className="px-3 py-2">Recipient</th>
                            <th className="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {jobs.data.length === 0 && (
                            <tr>
                                <td
                                    className="px-3 py-4 text-muted-foreground"
                                    colSpan={3}
                                >
                                    No notices queued.
                                </td>
                            </tr>
                        )}
                        {jobs.data.map((job) => (
                            <tr key={job.id} className="border-t">
                                <td className="px-3 py-2">
                                    {job.template} · {job.channel}
                                    <div className="text-xs text-muted-foreground">
                                        Shipment {job.shipment_id ?? '—'} ·{' '}
                                        {formatWhen(job.sent_at)}
                                    </div>
                                </td>
                                <td className="px-3 py-2">{job.recipient}</td>
                                <td className="px-3 py-2">
                                    <StatusBadge value={job.status} />
                                    <div className="text-xs text-muted-foreground">
                                        {job.attempts} attempts
                                        {job.last_error
                                            ? ` · ${job.last_error}`
                                            : ''}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
