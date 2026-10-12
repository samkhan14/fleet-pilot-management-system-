<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Activity;
use App\Domain\Shared\Sequences;
use App\Http\Controllers\Concerns\UsesOrganization;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerContact;
use App\Models\ShipmentOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    use UsesOrganization;

    public function index(Request $request): Response
    {
        $ctx = $this->authorizePermission('customers.read');
        $customers = $ctx->customers()->withCount('shipments')->orderBy('name');
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $customers->where(function ($query) use ($term): void {
                $query->where('name', 'like', $term)->orWhere('company_name', 'like', $term);
            });
        }

        return Inertia::render('customers/index', [
            'customers' => $customers->paginate(25)->withQueryString(),
            'filters' => $request->only('q'),
        ]);
    }

    public function store(Request $request, Activity $activity): RedirectResponse
    {
        $ctx = $this->authorizePermission('customers.write');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'billing_name' => ['nullable', 'string', 'max:160'],
            'billing_email' => ['nullable', 'email', 'max:160'],
            'billing_line1' => ['nullable', 'string', 'max:160'],
            'billing_city' => ['nullable', 'string', 'max:80'],
            'billing_region' => ['nullable', 'string', 'max:80'],
            'billing_postal_code' => ['nullable', 'string', 'max:20'],
            'billing_country' => ['nullable', 'string', 'max:56'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $customer = Customer::query()->create(['organization_id' => $ctx->organization->id, ...$data]);
        $activity->record($ctx, 'customer.created', 'customer', $customer->id);

        return redirect()->route('customers.show', $customer);
    }

    public function update(Request $request, int $customer, Activity $activity): RedirectResponse
    {
        $ctx = $this->authorizePermission('customers.write');
        $record = $ctx->customers()->findOrFail($customer);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'billing_name' => ['nullable', 'string', 'max:160'],
            'billing_email' => ['nullable', 'email', 'max:160'],
            'billing_line1' => ['nullable', 'string', 'max:160'],
            'billing_city' => ['nullable', 'string', 'max:80'],
            'billing_region' => ['nullable', 'string', 'max:80'],
            'billing_postal_code' => ['nullable', 'string', 'max:20'],
            'billing_country' => ['nullable', 'string', 'max:56'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $record->fill($data)->save();
        $activity->record($ctx, 'customer.updated', 'customer', $record->id);

        return back()->with('success', 'Customer updated.');
    }

    public function show(int $customer): Response
    {
        $ctx = $this->authorizePermission('customers.read');
        $record = $ctx->customers()->with(['contacts', 'addresses', 'shipments' => fn ($query) => $query->latest()->limit(20)])->findOrFail($customer);

        return Inertia::render('customers/show', ['customer' => $record]);
    }

    public function storeContact(Request $request, int $customer): RedirectResponse
    {
        $ctx = $this->authorizePermission('customers.write');
        $record = $ctx->customers()->findOrFail($customer);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_title' => ['nullable', 'string', 'max:80'],
            'authorized' => ['boolean'],
        ]);
        CustomerContact::query()->create(['customer_id' => $record->id, 'authorized' => $request->boolean('authorized', true), ...$data]);

        return back();
    }

    public function storeAddress(Request $request, int $customer): RedirectResponse
    {
        $ctx = $this->authorizePermission('customers.write');
        $record = $ctx->customers()->findOrFail($customer);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'line1' => ['required', 'string', 'max:160'],
            'line2' => ['nullable', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:56'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'instructions' => ['nullable', 'string', 'max:500'],
        ]);
        CustomerAddress::query()->create(['customer_id' => $record->id, ...$data]);

        return back();
    }

    public function storeOrder(Request $request, int $customer, Sequences $sequences): RedirectResponse
    {
        $ctx = $this->authorizePermission('shipments.write');
        $record = $ctx->customers()->findOrFail($customer);
        $data = $request->validate([
            'customer_reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        ShipmentOrder::query()->create([
            'organization_id' => $ctx->organization->id,
            'customer_id' => $record->id,
            'order_number' => $sequences->next($ctx->organization->id, 'order', 'ORD'),
            'customer_reference' => $data['customer_reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $ctx->user->id,
        ]);

        return back();
    }
}
