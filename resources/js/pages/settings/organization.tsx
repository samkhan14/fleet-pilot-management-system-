import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

type Settings = {
    timezone: string;
    require_recipient_name: boolean;
    require_pod_photo: boolean;
    require_pod_signature: boolean;
    allow_overlapping_assignments: boolean;
    tracking_token_ttl_hours: number;
    tax_rate_bps: number;
    document_types: string[];
    public_tracking_enabled: boolean;
    notify_on_assign: boolean;
    notify_on_delay: boolean;
    notify_on_failed: boolean;
    notify_on_delivered: boolean;
};

const checks: Array<[keyof Settings, string]> = [
    ['require_recipient_name', 'Require recipient name'],
    ['require_pod_photo', 'Require delivery photo'],
    ['require_pod_signature', 'Require a signature image'],
    ['allow_overlapping_assignments', 'Allow overlapping assignments'],
    ['public_tracking_enabled', 'Public tracking links'],
    ['notify_on_assign', 'Notify on assignment'],
    ['notify_on_delay', 'Notify on delay'],
    ['notify_on_failed', 'Notify on failed delivery'],
    ['notify_on_delivered', 'Notify on delivery'],
];

export default function OrganizationSettings({
    settings,
    members,
    grants,
    permissions,
    roles,
    drivers,
    customers,
}: {
    settings: Settings;
    members: Array<{
        id: number;
        role: string;
        user?: { name: string; email: string };
    }>;
    grants: Array<{ role: string; permission: string }>;
    permissions: string[];
    roles: string[];
    drivers: Array<{ id: number; name: string }>;
    customers: Array<{ id: number; name: string }>;
}) {
    const editableRoles = roles.filter((role) => role !== 'owner');
    const [role, setRole] = useState(editableRoles[0] ?? 'dispatcher');
    const [selected, setSelected] = useState<string[]>(
        grants
            .filter((grant) => grant.role === role)
            .map((grant) => grant.permission),
    );
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const form = useForm({
        timezone: settings.timezone,
        require_recipient_name: settings.require_recipient_name,
        require_pod_photo: settings.require_pod_photo,
        require_pod_signature: settings.require_pod_signature,
        allow_overlapping_assignments: settings.allow_overlapping_assignments,
        tracking_token_ttl_hours: settings.tracking_token_ttl_hours,
        tax_rate_bps: settings.tax_rate_bps,
        document_types: (settings.document_types ?? []).join('\n'),
        public_tracking_enabled: settings.public_tracking_enabled,
        notify_on_assign: settings.notify_on_assign,
        notify_on_delay: settings.notify_on_delay,
        notify_on_failed: settings.notify_on_failed,
        notify_on_delivered: settings.notify_on_delivered,
    });
    const member = useForm({
        name: '',
        email: '',
        password: '',
        role: 'dispatcher',
        customer_id: customers[0]?.id ?? '',
        driver_id: drivers[0]?.id ?? '',
    });

    const chooseRole = (next: string) => {
        setRole(next);
        setSelected(
            grants
                .filter((grant) => grant.role === next)
                .map((grant) => grant.permission),
        );
    };

    return (
        <div className="flex flex-col gap-8">
            <Head title="Organization" />
            <form
                className="grid gap-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.transform((data) => ({
                        ...data,
                        document_types: data.document_types
                            .split('\n')
                            .map((line) => line.trim())
                            .filter(Boolean),
                    }));
                    form.patch('/settings/organization');
                }}
            >
                <h1 className="text-xl font-semibold">Organization</h1>
                <label className="text-sm">
                    Timezone
                    <input
                        className="mt-1 h-9 w-full rounded-md border px-2"
                        value={form.data.timezone}
                        onChange={(event) =>
                            form.setData('timezone', event.target.value)
                        }
                    />
                </label>
                <label className="text-sm">
                    Tracking link lifetime (hours)
                    <input
                        type="number"
                        min={1}
                        className="mt-1 h-9 w-full rounded-md border px-2"
                        value={form.data.tracking_token_ttl_hours}
                        onChange={(event) =>
                            form.setData(
                                'tracking_token_ttl_hours',
                                Number(event.target.value),
                            )
                        }
                    />
                </label>
                <label className="text-sm">
                    Tax estimate (basis points)
                    <input
                        type="number"
                        min={0}
                        className="mt-1 h-9 w-full rounded-md border px-2"
                        value={form.data.tax_rate_bps}
                        onChange={(event) =>
                            form.setData(
                                'tax_rate_bps',
                                Number(event.target.value),
                            )
                        }
                    />
                </label>
                <p className="text-xs text-muted-foreground">
                    Basis points are an estimate only. They are not a filed tax
                    return.
                </p>
                {checks.map(([key, label]) => (
                    <label
                        key={key}
                        className="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            checked={Boolean(form.data[key])}
                            onChange={(event) =>
                                form.setData(key, event.target.checked)
                            }
                        />
                        {label}
                    </label>
                ))}
                <label className="text-sm">
                    Document types, one per line
                    <textarea
                        className="mt-1 min-h-28 w-full rounded-md border px-2 py-1"
                        value={form.data.document_types}
                        onChange={(event) =>
                            form.setData('document_types', event.target.value)
                        }
                    />
                </label>
                <Button type="submit">Save settings</Button>
            </form>

            <section className="grid gap-3">
                <h2 className="text-sm font-semibold">Members</h2>
                {members.map((item) => (
                    <p key={item.id} className="text-sm">
                        {item.user?.name} · {item.user?.email} · {item.role}
                    </p>
                ))}
                <form
                    className="grid gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        member.post('/settings/members');
                    }}
                >
                    <input
                        required
                        aria-label="Member name"
                        placeholder="Name"
                        className="h-9 rounded-md border px-2"
                        value={member.data.name}
                        onChange={(event) =>
                            member.setData('name', event.target.value)
                        }
                    />
                    <input
                        required
                        type="email"
                        aria-label="Member email"
                        placeholder="Email"
                        className="h-9 rounded-md border px-2"
                        value={member.data.email}
                        onChange={(event) =>
                            member.setData('email', event.target.value)
                        }
                    />
                    <input
                        required
                        type="password"
                        aria-label="Temporary password"
                        placeholder="Temporary password"
                        className="h-9 rounded-md border px-2"
                        value={member.data.password}
                        onChange={(event) =>
                            member.setData('password', event.target.value)
                        }
                    />
                    <select
                        aria-label="Role"
                        className="h-9 rounded-md border px-2"
                        value={member.data.role}
                        onChange={(event) =>
                            member.setData('role', event.target.value)
                        }
                    >
                        {editableRoles.map((item) => (
                            <option key={item} value={item}>
                                {item.replaceAll('_', ' ')}
                            </option>
                        ))}
                    </select>
                    {member.data.role === 'customer' && (
                        <select
                            aria-label="Customer record"
                            className="h-9 rounded-md border px-2"
                            value={member.data.customer_id}
                            onChange={(event) =>
                                member.setData(
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
                    )}
                    {member.data.role === 'driver' && (
                        <select
                            aria-label="Driver record"
                            className="h-9 rounded-md border px-2"
                            value={member.data.driver_id}
                            onChange={(event) =>
                                member.setData(
                                    'driver_id',
                                    Number(event.target.value),
                                )
                            }
                        >
                            {drivers.map((driver) => (
                                <option key={driver.id} value={driver.id}>
                                    {driver.name}
                                </option>
                            ))}
                        </select>
                    )}
                    <Button type="submit">Add member</Button>
                    <p className="text-sm text-destructive">
                        {member.errors.email ||
                            member.errors.driver_id ||
                            member.errors.customer_id ||
                            errors?.email}
                    </p>
                </form>
            </section>

            <section className="grid gap-3">
                <h2 className="text-sm font-semibold">Role permissions</h2>
                <p className="text-sm text-muted-foreground">
                    Owner access stays complete so the organization cannot be
                    locked out.
                </p>
                <select
                    aria-label="Role to edit"
                    className="h-9 rounded-md border px-2"
                    value={role}
                    onChange={(event) => chooseRole(event.target.value)}
                >
                    {editableRoles.map((item) => (
                        <option key={item} value={item}>
                            {item.replaceAll('_', ' ')}
                        </option>
                    ))}
                </select>
                <div className="grid gap-1 sm:grid-cols-2">
                    {permissions.map((permission) => (
                        <label
                            key={permission}
                            className="flex items-center gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                checked={selected.includes(permission)}
                                onChange={(event) =>
                                    setSelected((current) =>
                                        event.target.checked
                                            ? [...current, permission]
                                            : current.filter(
                                                  (item) => item !== permission,
                                              ),
                                    )
                                }
                            />
                            {permission}
                        </label>
                    ))}
                </div>
                <Button
                    type="button"
                    onClick={() =>
                        router.put('/settings/permissions', {
                            role,
                            permissions: selected,
                        })
                    }
                >
                    Save {role.replaceAll('_', ' ')} permissions
                </Button>
            </section>
        </div>
    );
}
