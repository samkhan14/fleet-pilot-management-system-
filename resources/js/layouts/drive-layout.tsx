import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Truck } from 'lucide-react';
import type { ReactNode } from 'react';

export default function DriveLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;

    return (
        <div className="min-h-dvh bg-background text-foreground">
            <header className="sticky top-0 z-20 flex items-center justify-between border-b bg-card px-4 py-3">
                <div>
                    <p className="text-xs tracking-[0.18em] text-primary uppercase">
                        FleetPilot
                    </p>
                    <p className="font-semibold">{auth.user?.name}</p>
                </div>
                <Link
                    href="/dashboard"
                    className="text-sm text-muted-foreground"
                >
                    Desk
                </Link>
            </header>
            <main className="mx-auto w-full max-w-lg px-4 py-4 pb-24">
                {children}
            </main>
            <nav className="fixed inset-x-0 bottom-0 z-20 grid grid-cols-2 border-t bg-card">
                <Link
                    href="/drive"
                    className="flex flex-col items-center gap-1 py-3 text-xs font-medium"
                >
                    <Truck className="size-5" />
                    Today
                </Link>
                <Link
                    href="/exceptions"
                    className="flex flex-col items-center gap-1 py-3 text-xs font-medium"
                >
                    <LayoutGrid className="size-5" />
                    Exceptions
                </Link>
            </nav>
        </div>
    );
}
