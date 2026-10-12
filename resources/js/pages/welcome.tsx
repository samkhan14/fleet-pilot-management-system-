import { Head, Link } from '@inertiajs/react';

export default function Welcome() {
    return (
        <main className="mx-auto flex min-h-dvh max-w-3xl flex-col justify-center gap-6 px-6">
            <Head title="FleetPilot" />
            <img
                src="/brand/lockup-light.jpg"
                alt="FleetPilot"
                className="h-16 w-auto self-start dark:hidden"
            />
            <img
                src="/brand/lockup-dark.jpg"
                alt=""
                className="hidden h-16 w-auto self-start dark:block"
            />
            <h1 className="text-4xl font-semibold tracking-tight">
                Dispatch for carriers who still know their drivers by name.
            </h1>
            <p className="max-w-xl text-muted-foreground">
                FleetPilot keeps shipments, assignments, proof of delivery, and
                exceptions in one organization. It does not invent live GPS or
                automatic routes.
            </p>
            <Link
                href="/login"
                className="inline-flex h-10 w-fit items-center rounded-md bg-primary px-4 text-sm text-primary-foreground"
            >
                Sign in
            </Link>
        </main>
    );
}
