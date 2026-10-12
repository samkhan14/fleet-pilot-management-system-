const tones: Record<string, string> = {
    draft: 'bg-stone-200 text-stone-800 dark:bg-stone-700 dark:text-stone-100',
    ready_for_dispatch:
        'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-100',
    assigned:
        'bg-indigo-100 text-indigo-900 dark:bg-indigo-950 dark:text-indigo-100',
    picked_up: 'bg-cyan-100 text-cyan-950 dark:bg-cyan-950 dark:text-cyan-100',
    in_transit: 'bg-teal-100 text-teal-950 dark:bg-teal-950 dark:text-teal-100',
    delivered:
        'bg-emerald-100 text-emerald-950 dark:bg-emerald-950 dark:text-emerald-100',
    failed_pickup:
        'bg-orange-100 text-orange-950 dark:bg-orange-950 dark:text-orange-100',
    failed_delivery:
        'bg-rose-100 text-rose-950 dark:bg-rose-950 dark:text-rose-100',
    on_hold:
        'bg-amber-100 text-amber-950 dark:bg-amber-950 dark:text-amber-100',
    damaged_or_missing:
        'bg-orange-200 text-orange-950 dark:bg-orange-900 dark:text-orange-50',
    returned_to_sender:
        'bg-violet-100 text-violet-950 dark:bg-violet-950 dark:text-violet-100',
    cancelled:
        'bg-stone-300 text-stone-700 dark:bg-stone-800 dark:text-stone-200',
    open: 'bg-rose-100 text-rose-900',
    in_progress: 'bg-amber-100 text-amber-950',
    resolved: 'bg-emerald-100 text-emerald-950',
    urgent: 'bg-rose-600 text-white',
    high: 'bg-orange-500 text-white',
    normal: 'bg-stone-200 text-stone-800',
    low: 'bg-stone-100 text-stone-600',
};

export function labelize(value: string): string {
    return value.replaceAll('_', ' ');
}

export function StatusBadge({ value }: { value: string }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold tracking-wide uppercase ${tones[value] ?? 'bg-muted text-foreground'}`}
        >
            {labelize(value)}
        </span>
    );
}

export function formatWhen(
    value: string | null | undefined,
    timeZone?: string,
): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(undefined, {
        timeZone: timeZone || 'UTC',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

export function money(cents: number, currency = 'USD'): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
    }).format(cents / 100);
}
