import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export default function CustomersIndex({
    customers,
    filters,
}: {
    customers: {
        data: Array<{
            id: number;
            name: string;
            company_name?: string;
            email?: string;
            phone?: string;
            shipments_count: number;
        }>;
    };
    filters: { q?: string };
}) {
    const form = useForm({
        name: '',
        company_name: '',
        email: '',
        phone: '',
        billing_city: '',
        billing_country: 'US',
    });

    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title="Customers" />
            <h1 className="text-2xl font-semibold">Customers</h1>
            <form
                className="flex gap-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    router.get(
                        '/customers',
                        Object.fromEntries(
                            new FormData(event.currentTarget).entries(),
                        ) as Record<string, string>,
                        { preserveState: true },
                    );
                }}
            >
                <input
                    name="q"
                    defaultValue={filters.q || ''}
                    aria-label="Search customers"
                    placeholder="Name or company"
                    className="h-9 rounded-md border bg-card px-2 text-sm"
                />
                <button
                    className="h-9 rounded-md bg-primary px-3 text-sm text-primary-foreground"
                    type="submit"
                >
                    Search
                </button>
            </form>
            <div className="overflow-hidden rounded-lg border bg-card">
                {customers.data.map((customer) => (
                    <Link
                        key={customer.id}
                        href={`/customers/${customer.id}`}
                        className="flex items-center justify-between border-b px-3 py-2 text-sm last:border-b-0"
                    >
                        <span>
                            <strong>{customer.name}</strong>
                            <span className="ml-2 text-muted-foreground">
                                {customer.company_name}
                            </span>
                        </span>
                        <span className="tabular-nums">
                            {customer.shipments_count}
                        </span>
                    </Link>
                ))}
            </div>
            <form
                className="grid gap-2 rounded-lg border bg-card p-3 md:grid-cols-2"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/customers');
                }}
            >
                <h2 className="text-sm font-semibold md:col-span-2">
                    Add customer
                </h2>
                <input
                    required
                    placeholder="Name"
                    aria-label="Customer name"
                    className="h-9 rounded-md border px-2"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
                <input
                    placeholder="Company"
                    aria-label="Company"
                    className="h-9 rounded-md border px-2"
                    value={form.data.company_name}
                    onChange={(event) =>
                        form.setData('company_name', event.target.value)
                    }
                />
                <input
                    placeholder="Email"
                    aria-label="Email"
                    className="h-9 rounded-md border px-2"
                    value={form.data.email}
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                />
                <input
                    placeholder="Phone"
                    aria-label="Phone"
                    className="h-9 rounded-md border px-2"
                    value={form.data.phone}
                    onChange={(event) =>
                        form.setData('phone', event.target.value)
                    }
                />
                <Button disabled={form.processing} type="submit">
                    Save customer
                </Button>
            </form>
        </div>
    );
}
