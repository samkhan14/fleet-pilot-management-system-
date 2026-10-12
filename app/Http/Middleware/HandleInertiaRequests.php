<?php

namespace App\Http\Middleware;

use App\Domain\Identity\OrganizationContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'organization' => app()->bound(OrganizationContext::class)
                    ? app(OrganizationContext::class)->organization->only(['id', 'name', 'timezone', 'currency'])
                    : null,
                'role' => app()->bound(OrganizationContext::class) ? app(OrganizationContext::class)->role : null,
                'permissions' => app()->bound(OrganizationContext::class) ? app(OrganizationContext::class)->permissions : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'tracking_token' => fn () => $request->session()->get('tracking_token'),
            ],
            'demoAccounts' => config('fleetpilot.demo') ? [
                ['role' => 'Owner', 'email' => 'mara.ellison@harborpine.example'],
                ['role' => 'Dispatcher', 'email' => 'owen.park@harborpine.example'],
                ['role' => 'Driver', 'email' => 'nia.rahman@harborpine.example'],
                ['role' => 'Finance', 'email' => 'helen.cho@harborpine.example'],
                ['role' => 'Customer', 'email' => 'andrea.moss@harborpine.example'],
            ] : [],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
