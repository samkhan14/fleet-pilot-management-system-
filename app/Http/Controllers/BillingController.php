<?php

namespace App\Http\Controllers;

use App\Domain\Billing\RecordCharge;
use App\Http\Controllers\Concerns\UsesOrganization;
use App\Http\Presenters\OperationsPresenter;
use App\Models\Charge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    use UsesOrganization;

    public function index(OperationsPresenter $presenter): Response
    {
        $ctx = $this->authorizePermission('finance.read');
        $charges = Charge::query()
            ->where('organization_id', $ctx->organization->id)
            ->with(['shipment:id,shipment_number', 'customer:id,name'])
            ->latest()
            ->paginate(30);

        $net = (int) Charge::query()->where('organization_id', $ctx->organization->id)->where('billing_status', '!=', 'void')->whereIn('kind', ['base', 'accessorial'])->sum('amount_cents')
            - (int) Charge::query()->where('organization_id', $ctx->organization->id)->where('billing_status', '!=', 'void')->where('kind', 'discount')->sum('amount_cents');

        return Inertia::render('billing/index', [
            'charges' => $charges->through(fn (Charge $charge) => $presenter->charge($charge)),
            'net_cents' => $net,
            'currency' => $ctx->organization->currency,
            'tax_rate_bps' => $ctx->organization->settings->tax_rate_bps ?? 0,
        ]);
    }

    public function store(Request $request, RecordCharge $charges): RedirectResponse
    {
        $ctx = $this->context();
        $shipment = $ctx->shipments()->findOrFail($request->integer('shipment_id'));
        $charges->create($ctx, $shipment, $request->all());

        return back();
    }

    public function update(Request $request, int $charge, RecordCharge $charges): RedirectResponse
    {
        $ctx = $this->context();
        $record = Charge::query()->where('organization_id', $ctx->organization->id)->findOrFail($charge);
        $charges->update($ctx, $record, $request->all());

        return back();
    }
}
