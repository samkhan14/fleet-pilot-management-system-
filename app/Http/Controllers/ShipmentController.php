<?php

namespace App\Http\Controllers;

use App\Domain\Dispatch\AssignShipment;
use App\Domain\Shipments\CreateShipment;
use App\Domain\Shipments\TransitionShipment;
use App\Domain\Tracking\IssueTrackingToken;
use App\Http\Controllers\Concerns\UsesOrganization;
use App\Http\Presenters\OperationsPresenter;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\ShipmentException;
use App\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShipmentController extends Controller
{
    use UsesOrganization;

    public function index(Request $request, OperationsPresenter $presenter): Response
    {
        $ctx = $this->authorizePermission('shipments.read');
        $query = $ctx->shipments()->with([
            'customer:id,name,company_name',
            'pickupAddress',
            'deliveryAddress',
            'currentDriver:id,name',
            'currentVehicle:id,fleet_number',
        ])->orderByDesc('id');

        foreach (['status', 'service_type', 'priority', 'service_area'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field));
            }
        }
        if ($request->filled('driver_id')) {
            $query->where('current_driver_id', $request->integer('driver_id'));
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->integer('customer_id'));
        }
        if ($request->filled('q')) {
            $term = '%'.$request->string('q').'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('shipment_number', 'like', $term)->orWhere('customer_reference', 'like', $term);
            });
        }

        return Inertia::render('shipments/index', [
            'shipments' => $query->paginate(25)->withQueryString()->through(fn ($shipment) => $presenter->shipmentList($shipment, $ctx)),
            'filters' => $request->only(['status', 'service_type', 'priority', 'service_area', 'driver_id', 'customer_id', 'q']),
        ]);
    }

    public function create(): Response
    {
        $ctx = $this->authorizePermission('shipments.write');

        return Inertia::render('shipments/create', [
            'customers' => Customer::query()->where('organization_id', $ctx->organization->id)->with(['addresses', 'contacts'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, CreateShipment $action): RedirectResponse
    {
        $ctx = $this->authorizePermission('shipments.write');
        $shipment = $action->handle($ctx, $request->all());

        return redirect()->route('shipments.show', $shipment)->with('success', 'Shipment '.$shipment->shipment_number.' created.');
    }

    public function show(Request $request, int $shipment, OperationsPresenter $presenter): Response
    {
        $ctx = $this->authorizePermission('shipments.read');
        $record = $ctx->shipments()->with([
            'customer:id,name,company_name,email,phone',
            'pickupAddress',
            'deliveryAddress',
            'items',
            'currentDriver',
            'currentVehicle',
            'evidences.files',
            'exceptions',
            'documents.files',
            'charges',
        ])->findOrFail($shipment);

        $page = $record->events()
            ->when($request->filled('before'), fn ($query) => $query->where('id', '<', $request->integer('before')))
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->sortBy('id')
            ->values();
        $includeReason = $ctx->allows('dispatch.manage') || $ctx->allows('shipments.write') || $ctx->role === 'driver';

        return Inertia::render('shipments/show', [
            'shipment' => $presenter->shipmentDetail($record, $ctx),
            'events' => $page->map(fn ($event) => $presenter->event($event, $includeReason))->values(),
            'older_cursor' => $page->first()?->id,
            'evidence' => $record->evidences->map(fn ($evidence) => [
                'id' => $evidence->id,
                'type' => $evidence->type,
                'recipient_name' => $evidence->recipient_name,
                'notes' => $evidence->notes,
                'captured_at' => $evidence->captured_at->toIso8601String(),
                'amends_id' => $evidence->amends_id,
                'files' => $evidence->files->map(fn ($file) => [
                    'id' => $file->id,
                    'kind' => $file->kind,
                    'original_name' => $file->original_name,
                    'mime_type' => $file->mime_type,
                ]),
            ]),
            'exceptions' => $record->exceptions,
            'documents' => $ctx->allows('documents.read') ? $record->documents->filter(function ($document) use ($ctx) {
                if ($ctx->role === 'customer') {
                    return $document->visibility === 'customer_visible';
                }

                return $document->visibility !== 'sensitive' || $ctx->allows('drivers.read_sensitive');
            })->values() : [],
            'charges' => $ctx->allows('finance.read') ? $record->charges->map(fn ($charge) => $presenter->charge($charge))->values() : [],
            'drivers' => $ctx->allows('dispatch.manage') ? Driver::query()->where('organization_id', $ctx->organization->id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'availability']) : [],
            'vehicles' => $ctx->allows('dispatch.manage') ? Vehicle::query()->where('organization_id', $ctx->organization->id)->where('status', 'active')->orderBy('fleet_number')->get(['id', 'fleet_number', 'availability', 'maintenance_status']) : [],
        ]);
    }

    public function transition(Request $request, int $shipment, TransitionShipment $action): RedirectResponse
    {
        $ctx = $this->context();
        $record = $ctx->shipments()->findOrFail($shipment);
        $action->handle($ctx, $record, $request->all(), $request->header('Idempotency-Key'));

        return back()->with('success', 'Shipment updated.');
    }

    public function reschedule(Request $request, int $shipment, TransitionShipment $action): RedirectResponse
    {
        $ctx = $this->context();
        $record = $ctx->shipments()->findOrFail($shipment);
        $action->reschedule($ctx, $record, $request->all());

        return back()->with('success', 'Schedule updated.');
    }

    public function assign(Request $request, int $shipment, AssignShipment $action): RedirectResponse
    {
        $ctx = $this->context();
        $record = $ctx->shipments()->findOrFail($shipment);
        $action->handle($ctx, $record, $request->all(), $request->header('Idempotency-Key'));

        return back()->with('success', 'Assignment saved.');
    }

    public function unassign(Request $request, int $shipment, AssignShipment $action): RedirectResponse
    {
        $ctx = $this->context();
        $record = $ctx->shipments()->findOrFail($shipment);
        $action->unassign($ctx, $record, (string) $request->input('reason'));

        return back()->with('success', 'Assignment cancelled.');
    }

    public function token(int $shipment, IssueTrackingToken $tokens): RedirectResponse
    {
        $ctx = $this->authorizePermission('tracking.manage');
        $record = $ctx->shipments()->findOrFail($shipment);
        $issued = $tokens->issue($ctx, $record);

        return back()->with('tracking_token', $issued['token']);
    }

    public function reportException(Request $request, int $shipment): RedirectResponse
    {
        $ctx = $this->authorizePermission('exceptions.write');
        $record = $ctx->shipments()->findOrFail($shipment);
        $data = $request->validate([
            'category' => ['required', 'in:delayed_pickup,delayed_delivery,failed_delivery,incorrect_address,damaged_goods,missing_goods,vehicle_breakdown,driver_unavailable,customer_cancellation'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'description' => ['required', 'string', 'max:2000'],
        ]);
        $exception = ShipmentException::query()->create([
            'organization_id' => $ctx->organization->id,
            'shipment_id' => $record->id,
            'trip_id' => $record->current_trip_id,
            'category' => $data['category'],
            'severity' => $data['severity'],
            'status' => 'open',
            'description' => $data['description'],
            'reported_by' => $ctx->user->id,
        ]);
        $record->forceFill(['current_exception_id' => $exception->id])->save();

        return back()->with('success', 'Exception reported.');
    }
}
