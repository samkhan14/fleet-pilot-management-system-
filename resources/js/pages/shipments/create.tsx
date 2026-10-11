import { Head, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';

type Customer = {
    id: number;
    name: string;
    addresses: Array<{
        id: number;
        label: string;
        line1: string;
        city: string;
        region?: string;
        postal_code?: string;
        country: string;
        contact_name?: string;
        contact_phone?: string;
    }>;
};

const emptyAddress = {
    line1: '',
    line2: '',
    city: '',
    region: '',
    postal_code: '',
    country: 'US',
    contact_name: '',
    contact_phone: '',
    instructions: '',
};

export default function CreateShipment({
    customers,
}: {
    customers: Customer[];
}) {
    const form = useForm({
        customer_id: customers[0]?.id ?? '',
        customer_reference: '',
        service_type: 'standard',
        priority: 'normal',
        description: '',
        handling_instructions: '',
        declared_value_cents: '',
        weight_kg: '',
        service_area: '',
        pickup_window_start: '',
        pickup_window_end: '',
        delivery_window_start: '',
        delivery_window_end: '',
        pickup: { ...emptyAddress },
        delivery: { ...emptyAddress },
        items: [{ description: '', quantity: 1 }],
    });

    const applyAddress = (kind: 'pickup' | 'delivery', addressId: string) => {
        const customer = customers.find(
            (item) => item.id === Number(form.data.customer_id),
        );
        const address = customer?.addresses.find(
            (item) => item.id === Number(addressId),
        );
        if (!address) {
            return;
        }
        form.setData(kind, {
            ...emptyAddress,
            ...address,
            line2: '',
            instructions: '',
        });
    };

    return (
        <form
            className="mx-auto flex max-w-4xl flex-col gap-4 p-4"
            onSubmit={(event) => {
                event.preventDefault();
                form.post('/shipments');
            }}
        >
            <Head title="New shipment" />
            <h1 className="text-2xl font-semibold">New shipment</h1>
            <p className="text-sm text-muted-foreground">
                Addresses are copied onto the shipment. Later changes to the
                customer book do not rewrite this record.
            </p>
            <div className="grid gap-3 md:grid-cols-2">
                <label className="text-sm">
                    Customer
                    <select
                        className="mt-1 h-9 w-full rounded-md border bg-card px-2"
                        value={form.data.customer_id}
                        onChange={(event) =>
                            form.setData(
                                'customer_id',
                                Number(event.target.value),
                            )
                        }
                    >
                        {customers.map((customer) => (
                            <option key={customer.id} value={customer.id}>
                                {customer.name}
                            </option>
                        ))}
                    </select>
                </label>
                <label className="text-sm">
                    Customer reference
                    <input
                        className="mt-1 h-9 w-full rounded-md border bg-card px-2"
                        value={form.data.customer_reference}
                        onChange={(event) =>
                            form.setData(
                                'customer_reference',
                                event.target.value,
                            )
                        }
                    />
                </label>
                <label className="text-sm">
                    Service
                    <select
                        className="mt-1 h-9 w-full rounded-md border bg-card px-2"
                        value={form.data.service_type}
                        onChange={(event) =>
                            form.setData('service_type', event.target.value)
                        }
                    >
                        {[
                            'standard',
                            'express',
                            'same_day',
                            'freight',
                            'courier',
                        ].map((type) => (
                            <option key={type}>{type}</option>
                        ))}
                    </select>
                </label>
                <label className="text-sm">
                    Priority
                    <select
                        className="mt-1 h-9 w-full rounded-md border bg-card px-2"
                        value={form.data.priority}
                        onChange={(event) =>
                            form.setData('priority', event.target.value)
                        }
                    >
                        {['low', 'normal', 'high', 'urgent'].map((priority) => (
                            <option key={priority}>{priority}</option>
                        ))}
                    </select>
                </label>
                <label className="text-sm md:col-span-2">
                    Description
                    <textarea
                        className="mt-1 w-full rounded-md border bg-card px-2 py-2"
                        required
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                </label>
                <label className="text-sm md:col-span-2">
                    Handling
                    <input
                        className="mt-1 h-9 w-full rounded-md border bg-card px-2"
                        value={form.data.handling_instructions}
                        onChange={(event) =>
                            form.setData(
                                'handling_instructions',
                                event.target.value,
                            )
                        }
                    />
                </label>
                {(
                    [
                        'pickup_window_start',
                        'pickup_window_end',
                        'delivery_window_start',
                        'delivery_window_end',
                    ] as const
                ).map((field) => (
                    <label key={field} className="text-sm">
                        {field.replaceAll('_', ' ')}
                        <input
                            type="datetime-local"
                            required
                            className="mt-1 h-9 w-full rounded-md border bg-card px-2"
                            value={form.data[field]}
                            onChange={(event) =>
                                form.setData(field, event.target.value)
                            }
                        />
                    </label>
                ))}
            </div>
            {(['pickup', 'delivery'] as const).map((kind) => (
                <fieldset
                    key={kind}
                    className="grid gap-2 rounded-lg border bg-card p-3 md:grid-cols-2"
                >
                    <legend className="px-1 text-sm font-medium capitalize">
                        {kind} snapshot
                    </legend>
                    <select
                        className="h-9 rounded-md border px-2 text-sm md:col-span-2"
                        aria-label={`Copy ${kind} address`}
                        defaultValue=""
                        onChange={(event) =>
                            applyAddress(kind, event.target.value)
                        }
                    >
                        <option value="">Copy from customer book</option>
                        {customers
                            .find(
                                (item) =>
                                    item.id === Number(form.data.customer_id),
                            )
                            ?.addresses.map((address) => (
                                <option key={address.id} value={address.id}>
                                    {address.label}: {address.line1},{' '}
                                    {address.city}
                                </option>
                            ))}
                    </select>
                    <input
                        required
                        placeholder="Street"
                        aria-label={`${kind} street`}
                        className="h-9 rounded-md border px-2"
                        value={form.data[kind].line1}
                        onChange={(event) =>
                            form.setData(kind, {
                                ...form.data[kind],
                                line1: event.target.value,
                            })
                        }
                    />
                    <input
                        required
                        placeholder="City"
                        aria-label={`${kind} city`}
                        className="h-9 rounded-md border px-2"
                        value={form.data[kind].city}
                        onChange={(event) =>
                            form.setData(kind, {
                                ...form.data[kind],
                                city: event.target.value,
                            })
                        }
                    />
                    <input
                        placeholder="Region"
                        aria-label={`${kind} region`}
                        className="h-9 rounded-md border px-2"
                        value={form.data[kind].region}
                        onChange={(event) =>
                            form.setData(kind, {
                                ...form.data[kind],
                                region: event.target.value,
                            })
                        }
                    />
                    <input
                        required
                        placeholder="Country"
                        aria-label={`${kind} country`}
                        className="h-9 rounded-md border px-2"
                        value={form.data[kind].country}
                        onChange={(event) =>
                            form.setData(kind, {
                                ...form.data[kind],
                                country: event.target.value,
                            })
                        }
                    />
                    <input
                        placeholder="Contact"
                        aria-label={`${kind} contact`}
                        className="h-9 rounded-md border px-2"
                        value={form.data[kind].contact_name}
                        onChange={(event) =>
                            form.setData(kind, {
                                ...form.data[kind],
                                contact_name: event.target.value,
                            })
                        }
                    />
                    <input
                        placeholder="Phone"
                        aria-label={`${kind} phone`}
                        className="h-9 rounded-md border px-2"
                        value={form.data[kind].contact_phone}
                        onChange={(event) =>
                            form.setData(kind, {
                                ...form.data[kind],
                                contact_phone: event.target.value,
                            })
                        }
                    />
                </fieldset>
            ))}
            <InputError
                message={form.errors.description || form.errors.customer_id}
            />
            <Button disabled={form.processing} type="submit">
                Create draft
            </Button>
        </form>
    );
}
