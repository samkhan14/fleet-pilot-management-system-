<?php

namespace App\Http\Controllers;

use App\Domain\Shipments\TransitionShipment;
use App\Http\Controllers\Concerns\UsesOrganization;
use App\Http\Presenters\OperationsPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriveController extends Controller
{
    use UsesOrganization;

    public function index(OperationsPresenter $presenter): Response
    {
        $ctx = $this->authorizePermission('shipments.driver_action');
        abort_unless($ctx->driverId !== null, 403, 'This account is not linked to a driver.');
        $shipments = $ctx->shipments()
            ->where('current_driver_id', $ctx->driverId)
            ->whereNotIn('status', ['cancelled', 'returned_to_sender', 'draft', 'ready_for_dispatch'])
            ->with(['customer:id,name', 'pickupAddress', 'deliveryAddress'])
            ->orderBy('pickup_window_start')
            ->get();

        return Inertia::render('drive/index', [
            'shipments' => $shipments->map(fn ($shipment) => $presenter->shipmentDetail($shipment, $ctx)),
        ]);
    }

    public function act(Request $request, int $shipment, TransitionShipment $action): RedirectResponse
    {
        $ctx = $this->context();
        $record = $ctx->shipments()->where('current_driver_id', $ctx->driverId)->findOrFail($shipment);
        $action->handle($ctx, $record, $request->all(), $request->header('Idempotency-Key'));

        return back()->with('success', 'Update saved.');
    }
}
