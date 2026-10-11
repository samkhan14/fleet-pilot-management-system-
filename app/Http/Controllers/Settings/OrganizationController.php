<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Identity\Permissions;
use App\Domain\Identity\ProvisionOrganization;
use App\Http\Controllers\Concerns\UsesOrganization;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\PermissionGrant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    use UsesOrganization;

    public function edit(): Response
    {
        $ctx = $this->authorizePermission('settings.manage');

        return Inertia::render('settings/organization', [
            'settings' => $ctx->organization->settings,
            'members' => $ctx->organization->memberships()->with('user:id,name,email')->get(),
            'grants' => PermissionGrant::query()->where('organization_id', $ctx->organization->id)->get(['role', 'permission']),
            'permissions' => Permissions::ALL,
            'roles' => array_keys(Permissions::defaults()),
            'drivers' => Driver::query()->where('organization_id', $ctx->organization->id)->get(['id', 'name']),
            'customers' => Customer::query()->where('organization_id', $ctx->organization->id)->get(['id', 'name']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $ctx = $this->authorizePermission('settings.manage');
        $data = $request->validate([
            'timezone' => ['required', 'timezone'],
            'allow_overlapping_assignments' => ['boolean'],
            'require_pod_photo' => ['boolean'],
            'require_pod_signature' => ['boolean'],
            'require_recipient_name' => ['boolean'],
            'tracking_token_ttl_hours' => ['required', 'integer', 'min:1', 'max:8760'],
            'public_tracking_enabled' => ['boolean'],
            'notify_on_assign' => ['boolean'],
            'notify_on_delay' => ['boolean'],
            'notify_on_failed' => ['boolean'],
            'notify_on_delivered' => ['boolean'],
            'tax_rate_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'document_types' => ['required', 'array', 'min:1'],
            'document_types.*' => ['string', 'max:60'],
        ]);
        $ctx->organization->forceFill(['timezone' => $data['timezone']])->save();
        $settings = $data;
        unset($settings['timezone']);
        $ctx->organization->settings()->update([
            ...$settings,
            'allow_overlapping_assignments' => $request->boolean('allow_overlapping_assignments'),
            'require_pod_photo' => $request->boolean('require_pod_photo'),
            'require_pod_signature' => $request->boolean('require_pod_signature'),
            'require_recipient_name' => $request->boolean('require_recipient_name'),
            'public_tracking_enabled' => $request->boolean('public_tracking_enabled'),
            'notify_on_assign' => $request->boolean('notify_on_assign'),
            'notify_on_delay' => $request->boolean('notify_on_delay'),
            'notify_on_failed' => $request->boolean('notify_on_failed'),
            'notify_on_delivered' => $request->boolean('notify_on_delivered'),
        ]);

        return back();
    }

    public function storeMember(Request $request, ProvisionOrganization $provision): RedirectResponse
    {
        $ctx = $this->authorizePermission('members.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:160'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(array_keys(Permissions::defaults()))],
            'customer_id' => ['nullable', 'integer'],
            'driver_id' => ['nullable', 'integer'],
        ]);
        if ($data['role'] === 'owner') {
            abort(403, 'Owner membership is not created from this form.');
        }
        if ($data['role'] === 'customer') {
            Customer::query()->where('organization_id', $ctx->organization->id)->findOrFail($data['customer_id']);
        }
        if ($data['role'] === 'driver') {
            Driver::query()->where('organization_id', $ctx->organization->id)->findOrFail($data['driver_id']);
        }
        $user = User::query()->firstOrCreate(
            ['email' => strtolower($data['email'])],
            ['name' => $data['name'], 'password' => Hash::make($data['password']), 'email_verified_at' => now()],
        );
        $provision->addMember($ctx->organization, $user, $data['role'], $data['customer_id'] ?? null, $data['driver_id'] ?? null);

        return back();
    }

    public function updateGrants(Request $request): RedirectResponse
    {
        $ctx = $this->authorizePermission('settings.manage');
        $data = $request->validate([
            'role' => ['required', Rule::in(array_keys(Permissions::defaults()))],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(Permissions::ALL)],
        ]);
        if ($data['role'] === 'owner') {
            abort(403, 'Owner access stays complete so the organization cannot be locked out.');
        }
        PermissionGrant::query()->where('organization_id', $ctx->organization->id)->where('role', $data['role'])->delete();
        foreach ($data['permissions'] ?? [] as $permission) {
            PermissionGrant::query()->create([
                'organization_id' => $ctx->organization->id,
                'role' => $data['role'],
                'permission' => $permission,
            ]);
        }

        return back();
    }
}
