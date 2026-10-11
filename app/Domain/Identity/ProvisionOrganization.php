<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\PermissionGrant;
use App\Models\User;

final class ProvisionOrganization
{
    public function create(string $name, string $slug, string $timezone = 'UTC', string $currency = 'USD'): Organization
    {
        $organization = Organization::query()->create([
            'name' => $name,
            'slug' => $slug,
            'timezone' => $timezone,
            'currency' => $currency,
        ]);
        OrganizationSetting::query()->create([
            'organization_id' => $organization->id,
            'document_types' => Permissions::DOCUMENT_TYPES,
        ]);
        foreach (Permissions::defaults() as $role => $permissions) {
            foreach ($permissions as $permission) {
                PermissionGrant::query()->create([
                    'organization_id' => $organization->id,
                    'role' => $role,
                    'permission' => $permission,
                ]);
            }
        }

        return $organization->load('settings');
    }

    public function addMember(Organization $organization, User $user, string $role, ?int $customerId = null, ?int $driverId = null): Membership
    {
        return Membership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => $role,
            'customer_id' => $customerId,
            'driver_id' => $driverId,
        ]);
    }

    public function contextFor(User $user, ?int $organizationId = null): ?OrganizationContext
    {
        $membership = Membership::query()
            ->with('organization.settings')
            ->where('user_id', $user->id)
            ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
            ->first();
        if (! $membership || ! $membership->organization) {
            return null;
        }
        $permissions = [];
        foreach (
            PermissionGrant::query()
                ->where('organization_id', $membership->organization_id)
                ->where('role', $membership->role)
                ->pluck('permission') as $permission
        ) {
            if (is_string($permission) && $permission !== '') {
                $permissions[] = $permission;
            }
        }

        return new OrganizationContext(
            $membership->organization,
            $user,
            $membership->role,
            $membership->customer_id,
            $membership->driver_id,
            $permissions,
        );
    }
}
