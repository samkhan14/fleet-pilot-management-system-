<?php

namespace App\Http\Controllers;

use App\Domain\Notifications\QueueShipmentNotification;
use App\Domain\Tracking\RecordEvent;
use App\Http\Controllers\Concerns\UsesOrganization;
use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExceptionController extends Controller
{
    use UsesOrganization;

    public function index(Request $request): Response
    {
        $ctx = $this->authorizePermission('exceptions.read');
        $query = ShipmentException::query()
            ->where('organization_id', $ctx->organization->id)
            ->with('shipment:id,shipment_number,customer_id')
            ->latest();
        if ($ctx->role === 'driver' || $ctx->role === 'customer') {
            $query->whereIn('shipment_id', $ctx->shipments()->select('shipments.id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return Inertia::render('exceptions/index', [
            'exceptions' => $query->paginate(25)->withQueryString(),
        ]);
    }

    public function resolve(Request $request, int $exception, RecordEvent $events, QueueShipmentNotification $notifications): RedirectResponse
    {
        $ctx = $this->authorizePermission('exceptions.resolve');
        $record = ShipmentException::query()->where('organization_id', $ctx->organization->id)->findOrFail($exception);
        $data = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:2000'],
        ]);
        $record->fill([
            'status' => 'resolved',
            'resolution_notes' => $data['resolution_notes'],
            'resolved_by' => $ctx->user->id,
            'resolved_at' => now(),
        ])->save();
        $shipment = Shipment::query()->findOrFail($record->shipment_id);
        if ((int) $shipment->current_exception_id === $record->id) {
            $shipment->forceFill(['current_exception_id' => null])->save();
        }
        $events->write($ctx, $shipment, 'exception_recorded', 'An exception was resolved.', $shipment->status, $data['resolution_notes']);

        return back();
    }
}
