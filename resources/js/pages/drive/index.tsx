import { Head, useForm, usePage } from '@inertiajs/react';
import {
    useEffect,
    useRef,
    useState,
    type PointerEvent as ReactPointerEvent,
} from 'react';
import { StatusBadge, formatWhen } from '@/components/operations/status-badge';
import { Button } from '@/components/ui/button';

type Address = {
    line1: string;
    city: string;
    region?: string | null;
    postal_code?: string | null;
    country: string;
    contact_name?: string | null;
    contact_phone?: string | null;
    instructions?: string | null;
};
type Stop = {
    id: number;
    shipment_number: string;
    status: string;
    description: string;
    handling_instructions?: string | null;
    pickup: Address | null;
    delivery: Address | null;
    pickup_window_start?: string | null;
    delivery_window_end?: string | null;
};

function SignaturePad({ onChange }: { onChange: (value: string) => void }) {
    const canvasRef = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        const context = canvas?.getContext('2d');
        if (!canvas || !context) {
            return;
        }
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.strokeStyle = '#172033';
        context.lineWidth = 2.5;
        context.lineCap = 'round';
    }, []);

    const position = (event: ReactPointerEvent<HTMLCanvasElement>) => {
        const canvas = canvasRef.current!;
        const rect = canvas.getBoundingClientRect();

        return {
            x: ((event.clientX - rect.left) * canvas.width) / rect.width,
            y: ((event.clientY - rect.top) * canvas.height) / rect.height,
        };
    };

    return (
        <div>
            <div className="mb-1 flex items-center justify-between text-sm">
                <span>Signature image</span>
                <button
                    type="button"
                    className="text-primary"
                    onClick={() => {
                        const canvas = canvasRef.current;
                        const context = canvas?.getContext('2d');
                        if (!canvas || !context) {
                            return;
                        }
                        context.fillStyle = '#ffffff';
                        context.fillRect(0, 0, canvas.width, canvas.height);
                        onChange('');
                    }}
                >
                    Clear
                </button>
            </div>
            <canvas
                ref={canvasRef}
                width={640}
                height={200}
                className="h-28 w-full touch-none rounded-md border bg-white"
                aria-label="Recipient signature"
                onPointerDown={(event) => {
                    const context = canvasRef.current?.getContext('2d');
                    if (!context) {
                        return;
                    }
                    canvasRef.current?.setPointerCapture(event.pointerId);
                    const point = position(event);
                    context.beginPath();
                    context.moveTo(point.x, point.y);
                }}
                onPointerMove={(event) => {
                    if (event.buttons === 0 && event.pointerType === 'mouse') {
                        return;
                    }
                    const context = canvasRef.current?.getContext('2d');
                    if (
                        !context ||
                        !canvasRef.current?.hasPointerCapture(event.pointerId)
                    ) {
                        return;
                    }
                    const point = position(event);
                    context.lineTo(point.x, point.y);
                    context.stroke();
                }}
                onPointerUp={(event) => {
                    canvasRef.current?.releasePointerCapture(event.pointerId);
                    const canvas = canvasRef.current;
                    if (canvas) {
                        onChange(canvas.toDataURL('image/png'));
                    }
                }}
            />
            <p className="mt-1 text-xs text-muted-foreground">
                Captured image only. This is not a certified electronic
                signature.
            </p>
        </div>
    );
}

function maps(address: Address | null): string {
    const query = [
        address?.line1,
        address?.city,
        address?.region,
        address?.postal_code,
        address?.country,
    ]
        .filter(Boolean)
        .join(', ');
    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(query)}`;
}

export default function DriveIndex({ shipments }: { shipments: Stop[] }) {
    const timezone = (
        usePage().props as {
            auth?: { organization?: { timezone?: string } | null };
        }
    ).auth?.organization?.timezone;
    const [active, setActive] = useState<Stop | null>(shipments[0] ?? null);
    const key = useRef(crypto.randomUUID());
    const form = useForm<{
        to: string;
        recipient_name: string;
        notes: string;
        reason: string;
        customer_message: string;
        signature_data: string;
        photo: File | null;
    }>({
        to: 'delivered',
        recipient_name: '',
        notes: '',
        reason: '',
        customer_message: '',
        signature_data: '',
        photo: null,
    });

    const send = (to: string) => {
        form.transform((data) => ({ ...data, to }));
        form.post(`/drive/shipments/${active?.id}`, {
            forceFormData: true,
            headers: { 'Idempotency-Key': key.current },
            onSuccess: () => {
                key.current = crypto.randomUUID();
            },
        });
    };

    return (
        <div>
            <Head title="Today" />
            <h1 className="text-xl font-semibold">Today&apos;s stops</h1>
            <p className="mb-3 text-sm text-muted-foreground">
                Needs a connection. There is no offline queue and no live GPS
                trail.
            </p>
            <div className="flex gap-2 overflow-x-auto pb-3">
                {shipments.map((stop) => (
                    <button
                        key={stop.id}
                        type="button"
                        onClick={() => setActive(stop)}
                        className={`min-w-40 rounded-lg border p-2 text-left ${active?.id === stop.id ? 'border-primary bg-accent' : 'bg-card'}`}
                    >
                        <div className="text-sm font-medium">
                            {stop.shipment_number}
                        </div>
                        <StatusBadge value={stop.status} />
                    </button>
                ))}
                {shipments.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Nothing is assigned to you.
                    </p>
                )}
            </div>
            {active && (
                <article className="rounded-xl border bg-card p-4">
                    <h2 className="text-lg font-semibold">
                        {active.shipment_number}
                    </h2>
                    <p className="text-sm">{active.description}</p>
                    {active.handling_instructions && (
                        <p className="mt-1 text-sm">
                            Handling: {active.handling_instructions}
                        </p>
                    )}
                    <div className="mt-3 space-y-2 text-sm">
                        <p>
                            Pickup {active.pickup?.line1}, {active.pickup?.city}{' '}
                            · {formatWhen(active.pickup_window_start, timezone)}
                        </p>
                        <a
                            className="block text-primary"
                            href={maps(active.pickup)}
                        >
                            Navigate to pickup
                        </a>
                        <p>
                            Deliver {active.delivery?.line1},{' '}
                            {active.delivery?.city} ·{' '}
                            {formatWhen(active.delivery_window_end, timezone)}
                        </p>
                        <a
                            className="block text-primary"
                            href={maps(active.delivery)}
                        >
                            Navigate to delivery
                        </a>
                        {active.delivery?.contact_phone && (
                            <a
                                className="block"
                                href={`tel:${active.delivery.contact_phone}`}
                            >
                                Call {active.delivery.contact_name}{' '}
                                {active.delivery.contact_phone}
                            </a>
                        )}
                    </div>
                    <div className="mt-4 grid gap-2">
                        <Button type="button" onClick={() => send('picked_up')}>
                            Confirm pickup
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => send('in_transit')}
                        >
                            Mark in transit
                        </Button>
                        <label className="text-sm">
                            Recipient
                            <input
                                className="mt-1 h-11 w-full rounded-md border px-2"
                                value={form.data.recipient_name}
                                onChange={(event) =>
                                    form.setData(
                                        'recipient_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                        <label className="text-sm">
                            Photo
                            <input
                                className="mt-1 block w-full"
                                type="file"
                                accept="image/*,.pdf"
                                onChange={(event) =>
                                    form.setData(
                                        'photo',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </label>
                        <SignaturePad
                            onChange={(value) =>
                                form.setData('signature_data', value)
                            }
                        />
                        <label className="text-sm">
                            Note for the customer
                            <input
                                className="mt-1 h-11 w-full rounded-md border px-2"
                                value={form.data.customer_message}
                                onChange={(event) =>
                                    form.setData(
                                        'customer_message',
                                        event.target.value,
                                    )
                                }
                            />
                        </label>
                        <label className="text-sm">
                            Internal reason
                            <input
                                className="mt-1 h-11 w-full rounded-md border px-2"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                            />
                        </label>
                        <Button type="button" onClick={() => send('delivered')}>
                            Complete delivery
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => send('failed_pickup')}
                        >
                            Pickup failed
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => send('failed_delivery')}
                        >
                            Delivery failed
                        </Button>
                        <p className="text-sm text-destructive">
                            {form.errors.reason ||
                                form.errors.recipient_name ||
                                form.errors.photo ||
                                (
                                    form.errors as Record<
                                        string,
                                        string | undefined
                                    >
                                ).status}
                        </p>
                    </div>
                </article>
            )}
        </div>
    );
}
