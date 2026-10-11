import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

type Customer = {
    id: number;
    name: string;
    company_name?: string;
    email?: string;
    phone?: string;
    billing_name?: string;
    billing_email?: string;
    billing_line1?: string;
    billing_city?: string;
    billing_region?: string;
    billing_postal_code?: string;
    billing_country?: string;
    notes?: string;
    contacts: Array<{
        id: number;
        name: string;
        email?: string;
        phone?: string;
        authorized: boolean;
    }>;
    addresses: Array<{
        id: number;
        label: string;
        line1: string;
        city: string;
        country: string;
    }>;
    shipments: Array<{ id: number; shipment_number: string; status: string }>;
};

export default function CustomerShow({ customer }: { customer: Customer }) {
    const contact = useForm({
        name: '',
        email: '',
        phone: '',
        role_title: '',
        authorized: true,
    });
    const address = useForm({
        label: 'Dock',
        line1: '',
        city: '',
        country: 'US',
    });
    const order = useForm({ customer_reference: '', notes: '' });
    const { auth } = usePage().props;
    const canWrite =
        auth.role === 'owner' || auth.permissions?.includes('customers.write');
    const profile = useForm({
        name: customer.name,
        company_name: customer.company_name ?? '',
        email: customer.email ?? '',
        phone: customer.phone ?? '',
        billing_name: customer.billing_name ?? '',
        billing_email: customer.billing_email ?? '',
        billing_line1: customer.billing_line1 ?? '',
        billing_city: customer.billing_city ?? '',
        billing_region: customer.billing_region ?? '',
        billing_postal_code: customer.billing_postal_code ?? '',
        billing_country: customer.billing_country ?? '',
        notes: customer.notes ?? '',
    });

    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title={customer.name} />
            <h1 className="text-2xl font-semibold">{customer.name}</h1>
            <p className="text-sm text-muted-foreground">
                {customer.company_name} · {customer.email} · {customer.phone}
            </p>
            <p className="text-sm">
                Billing {customer.billing_line1} {customer.billing_city}
            </p>
            {canWrite && (
                <form
                    className="grid gap-2 rounded-lg border bg-card p-3 md:grid-cols-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        profile.patch(`/customers/${customer.id}`);
                    }}
                >
                    <input
                        required
                        aria-label="Customer name"
                        className="h-9 rounded-md border px-2"
                        value={profile.data.name}
                        onChange={(event) =>
                            profile.setData('name', event.target.value)
                        }
                    />
                    <input
                        aria-label="Company"
                        placeholder="Company"
                        className="h-9 rounded-md border px-2"
                        value={profile.data.company_name}
                        onChange={(event) =>
                            profile.setData('company_name', event.target.value)
                        }
                    />
                    <input
                        aria-label="Customer email"
                        placeholder="Email"
                        className="h-9 rounded-md border px-2"
                        value={profile.data.email}
                        onChange={(event) =>
                            profile.setData('email', event.target.value)
                        }
                    />
                    <input
                        aria-label="Customer phone"
                        placeholder="Phone"
                        className="h-9 rounded-md border px-2"
                        value={profile.data.phone}
                        onChange={(event) =>
                            profile.setData('phone', event.target.value)
                        }
                    />
                    <textarea
                        aria-label="Notes"
                        className="min-h-20 rounded-md border px-2 py-1 md:col-span-2"
                        value={profile.data.notes}
                        onChange={(event) =>
                            profile.setData('notes', event.target.value)
                        }
                    />
                    <Button type="submit">Save customer</Button>
                </form>
            )}
            <section>
                <h2 className="text-sm font-semibold">Authorized contacts</h2>
                {customer.contacts.map((item) => (
                    <p key={item.id} className="text-sm">
                        {item.name} · {item.email} ·{' '}
                        {item.authorized ? 'authorized' : 'not authorized'}
                    </p>
                ))}
                <form
                    className="mt-2 flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        contact.post(`/customers/${customer.id}/contacts`);
                    }}
                >
                    <input
                        required
                        aria-label="Contact name"
                        placeholder="Name"
                        className="h-9 rounded-md border px-2"
                        value={contact.data.name}
                        onChange={(event) =>
                            contact.setData('name', event.target.value)
                        }
                    />
                    <input
                        aria-label="Contact email"
                        placeholder="Email"
                        className="h-9 rounded-md border px-2"
                        value={contact.data.email}
                        onChange={(event) =>
                            contact.setData('email', event.target.value)
                        }
                    />
                    <Button type="submit">Add contact</Button>
                </form>
            </section>
            <section>
                <h2 className="text-sm font-semibold">Address book</h2>
                {customer.addresses.map((item) => (
                    <p key={item.id} className="text-sm">
                        {item.label}: {item.line1}, {item.city}
                    </p>
                ))}
                <form
                    className="mt-2 flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        address.post(`/customers/${customer.id}/addresses`);
                    }}
                >
                    <input
                        required
                        aria-label="Street"
                        placeholder="Street"
                        className="h-9 rounded-md border px-2"
                        value={address.data.line1}
                        onChange={(event) =>
                            address.setData('line1', event.target.value)
                        }
                    />
                    <input
                        required
                        aria-label="City"
                        placeholder="City"
                        className="h-9 rounded-md border px-2"
                        value={address.data.city}
                        onChange={(event) =>
                            address.setData('city', event.target.value)
                        }
                    />
                    <Button type="submit">Add address</Button>
                </form>
            </section>
            <section>
                <h2 className="text-sm font-semibold">Orders and shipments</h2>
                {customer.shipments.map((shipment) => (
                    <p key={shipment.id} className="text-sm">
                        <Link
                            className="text-primary"
                            href={`/shipments/${shipment.id}`}
                        >
                            {shipment.shipment_number}
                        </Link>{' '}
                        · {shipment.status}
                    </p>
                ))}
                <form
                    className="mt-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        order.post(`/customers/${customer.id}/orders`);
                    }}
                >
                    <input
                        aria-label="Order reference"
                        placeholder="Customer order reference"
                        className="h-9 rounded-md border px-2"
                        value={order.data.customer_reference}
                        onChange={(event) =>
                            order.setData(
                                'customer_reference',
                                event.target.value,
                            )
                        }
                    />
                    <Button className="ml-2" type="submit">
                        Open order
                    </Button>
                </form>
            </section>
        </div>
    );
}
