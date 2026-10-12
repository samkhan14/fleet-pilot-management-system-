<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Identity\OrganizationContext;

trait UsesOrganization
{
    protected function context(): OrganizationContext
    {
        abort_unless(app()->bound(OrganizationContext::class), 403, 'You are not a member of an organization.');

        return app(OrganizationContext::class);
    }

    protected function authorizePermission(string $permission): OrganizationContext
    {
        $context = $this->context();
        abort_unless($context->allows($permission), 403);

        return $context;
    }
}
