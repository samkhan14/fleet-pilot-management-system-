import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

export default function OrganizationSettings({
    settings,
    members,
}: {
    settings: {
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
    members: Array<{
        id: number;
        role: string;
        user?: { name: string; email: string };
    }>;
}) {
    const form = useForm({ ...settings, timezone: settings.timezone });

    return (
        <div className="flex flex-col gap-4">
            <Head title="Organization" />
            <h1 className="text-xl font-semibold">Organization</h1>
            <form
                className="grid gap-3"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.patch('/settings/organization');
                }}
            >
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
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.require_recipient_name}
                        onChange={(event) =>
                            form.setData(
                                'require_recipient_name',
                                event.target.checked,
                            )
                        }
                    />{' '}
                    Require recipient name
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.require_pod_photo}
                        onChange={(event) =>
                            form.setData(
                                'require_pod_photo',
                                event.target.checked,
                            )
                        }
                    />{' '}
                    Require delivery photo
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.require_pod_signature}
                        onChange={(event) =>
                            form.setData(
                                'require_pod_signature',
                                event.target.checked,
                            )
                        }
                    />{' '}
                    Require a signature image
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.allow_overlapping_assignments}
                        onChange={(event) =>
                            form.setData(
                                'allow_overlapping_assignments',
                                event.target.checked,
                            )
                        }
                    />{' '}
                    Allow overlapping driver or vehicle assignments
                </label>
                <Button type="submit">Save settings</Button>
            </form>
            <section>
                <h2 className="text-sm font-semibold">Members</h2>
                {members.map((member) => (
                    <p key={member.id} className="text-sm">
                        {member.user?.name} · {member.user?.email} ·{' '}
                        {member.role}
                    </p>
                ))}
            </section>
        </div>
    );
}
