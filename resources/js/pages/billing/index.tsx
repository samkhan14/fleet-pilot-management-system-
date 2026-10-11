import { Head, useForm } from '@inertiajs/react';
import { money } from '@/components/operations/status-badge';
import { Button } from '@/components/ui/button';

type Charge = {
    id: number;
    shipment_number?: string;
    customer?: string;
    description: string;
    kind: string;
    amount_cents: number;
    currency: string;
    billing_status: string;
    payment_status: string;
    invoice_reference?: string | null;
};

export default function BillingIndex({
    charges,
    net_cents,
    currency,
    tax_rate_bps,
}: {
    charges: { data: Charge[] };
    net_cents: number;
    currency: string;
    tax_rate_bps: number;
}) {
    const form = useForm({
        shipment_id: '',
        description: 'Linehaul',
        kind: 'base',
        amount_cents: 10000,
        invoice_reference: '',
    });

    return (
        <div className="flex flex-col gap-4 p-4">
            <Head title="Billing" />
            <h1 className="text-2xl font-semibold">Charges</h1>
            <p className="text-sm text-muted-foreground">
                Net open book {money(net_cents, currency)}. Tax estimate uses{' '}
                {tax_rate_bps} basis points and is not a filed return. Delivery
                never marks a charge paid.
            </p>
            <div className="overflow-x-auto rounded-lg border bg-card">
                <table className="w-full text-left text-sm">
                    <thead className="text-xs text-muted-foreground uppercase">
                        <tr>
                            <th className="px-3 py-2">Shipment</th>
                            <th className="px-3 py-2">Charge</th>
                            <th className="px-3 py-2">Amount</th>
                            <th className="px-3 py-2">Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        {charges.data.map((charge) => (
                            <tr key={charge.id} className="border-t">
                                <td className="px-3 py-2">
                                    {charge.shipment_number}
                                    <div className="text-xs text-muted-foreground">
                                        {charge.customer}
                                    </div>
                                </td>
                                <td className="px-3 py-2">
                                    {charge.description} · {charge.kind}
                                </td>
                                <td className="px-3 py-2 tabular-nums">
                                    {money(
                                        charge.kind === 'discount'
                                            ? -charge.amount_cents
                                            : charge.amount_cents,
                                        charge.currency,
                                    )}
                                </td>
                                <td className="px-3 py-2">
                                    {charge.billing_status} /{' '}
                                    {charge.payment_status}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <form
                className="grid gap-2 md:grid-cols-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/billing/charges');
                }}
            >
                <input
                    required
                    aria-label="Shipment id"
                    placeholder="Shipment id"
                    className="h-9 rounded-md border px-2"
                    value={form.data.shipment_id}
                    onChange={(event) =>
                        form.setData('shipment_id', event.target.value)
                    }
                />
                <input
                    aria-label="Description"
                    className="h-9 rounded-md border px-2"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
                <select
                    aria-label="Kind"
                    className="h-9 rounded-md border px-2"
                    value={form.data.kind}
                    onChange={(event) =>
                        form.setData('kind', event.target.value)
                    }
                >
                    <option value="base">Base</option>
                    <option value="accessorial">Accessorial</option>
                    <option value="discount">Discount</option>
                </select>
                <input
                    aria-label="Amount cents"
                    type="number"
                    className="h-9 rounded-md border px-2"
                    value={form.data.amount_cents}
                    onChange={(event) =>
                        form.setData('amount_cents', Number(event.target.value))
                    }
                />
                <Button type="submit">Add charge</Button>
            </form>
        </div>
    );
}
