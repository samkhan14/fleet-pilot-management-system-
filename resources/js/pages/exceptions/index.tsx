import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { StatusBadge } from '@/components/operations/status-badge';
import { Button } from '@/components/ui/button';

export default function ExceptionsIndex({
    exceptions,
}: {
    exceptions: {
        data: Array<{
            id: number;
            category: string;
            severity: string;
            status: string;
            description: string;
            shipment?: { id: number; shipment_number: string };
        }>;
    };
}) {
    const [notes, setNotes] = useState<Record<number, string>>({});

    return (
        <div className="flex flex-col gap-3 p-4">
            <Head title="Exceptions" />
            <h1 className="text-2xl font-semibold">Exceptions</h1>
            {exceptions.data.map((item) => (
                <article
                    key={item.id}
                    className="rounded-lg border bg-card p-3 text-sm"
                >
                    <div className="flex gap-2">
                        <StatusBadge value={item.status} />
                        <StatusBadge value={item.severity} />
                    </div>
                    <p className="mt-2 font-medium">
                        {item.shipment && (
                            <Link
                                className="text-primary"
                                href={`/shipments/${item.shipment.id}`}
                            >
                                {item.shipment.shipment_number}
                            </Link>
                        )}{' '}
                        · {item.category.replaceAll('_', ' ')}
                    </p>
                    <p>{item.description}</p>
                    {item.status !== 'resolved' && (
                        <form
                            className="mt-2 flex gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.post(`/exceptions/${item.id}/resolve`, {
                                    resolution_notes: notes[item.id] || '',
                                });
                            }}
                        >
                            <input
                                required
                                aria-label="Resolution notes"
                                className="h-9 flex-1 rounded-md border px-2"
                                value={notes[item.id] || ''}
                                onChange={(event) =>
                                    setNotes({
                                        ...notes,
                                        [item.id]: event.target.value,
                                    })
                                }
                            />
                            <Button type="submit">Resolve</Button>
                        </form>
                    )}
                </article>
            ))}
        </div>
    );
}
