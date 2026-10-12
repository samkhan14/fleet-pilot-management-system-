<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class OrganizationContext
{
    /**
     * @param  list<string>  $permissions
     */
    public function __construct(
        public Organization $organization,
        public User $user,
        public string $role,
        public ?int $customerId,
        public ?int $driverId,
        public array $permissions,
    ) {}

    public function allows(string $permission): bool
    {
        if ($this->role === 'owner') {
            return true;
        }

        return in_array($permission, $this->permissions, true);
    }

    /**
     * @return Builder<Shipment>
     */
    public function shipments(): Builder
    {
        $query = Shipment::query()->where('organization_id', $this->organization->id);

        if ($this->role === 'customer') {
            $query->where('customer_id', $this->customerId ?? 0);
        }

        if ($this->role === 'driver') {
            $driverId = $this->driverId ?? 0;
            $query->where(function (Builder $inner) use ($driverId): void {
                $inner->where('current_driver_id', $driverId)
                    ->orWhereExists(function ($sub) use ($driverId): void {
                        $sub->selectRaw('1')
                            ->from('assignments')
                            ->whereColumn('assignments.shipment_id', 'shipments.id')
                            ->where('assignments.driver_id', $driverId);
                    });
            });
        }

        return $query;
    }

    /**
     * @return Builder<Customer>
     */
    public function customers(): Builder
    {
        $query = Customer::query()->where('organization_id', $this->organization->id);

        if ($this->role === 'customer') {
            $query->whereKey($this->customerId ?? 0);
        }

        return $query;
    }
}
