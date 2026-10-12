<?php

namespace App\Http\Middleware;

use App\Domain\Identity\OrganizationContext;
use App\Domain\Identity\ProvisionOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganization
{
    public function __construct(private ProvisionOrganization $provision) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->bound(OrganizationContext::class)) {
            app()->forgetInstance(OrganizationContext::class);
        }

        $user = $request->user();
        if ($user) {
            $organizationId = $request->session()->get('organization_id');
            $requested = is_numeric($organizationId) ? (int) $organizationId : null;
            $context = $this->provision->contextFor($user, $requested);
            if (! $context && $requested) {
                $request->session()->forget('organization_id');
                $context = $this->provision->contextFor($user, null);
            }
            if ($context instanceof OrganizationContext) {
                $request->session()->put('organization_id', $context->organization->id);
                app()->instance(OrganizationContext::class, $context);
            }
        }

        return $next($request);
    }
}
