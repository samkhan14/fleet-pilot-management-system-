<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesOrganization;
use App\Models\DeliveryEvidence;
use App\Models\Document;
use App\Models\Shipment;
use App\Models\StoredFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    use UsesOrganization;

    public function store(Request $request): RedirectResponse
    {
        $ctx = $this->authorizePermission('documents.write');
        $settings = $ctx->organization->settings()->firstOrFail();
        $data = $request->validate([
            'shipment_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:160'],
            'doc_type' => ['required', 'string', 'in:'.implode(',', $settings->document_types ?? [])],
            'visibility' => ['required', 'in:internal,customer_visible,sensitive'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.config('fleetpilot.evidence_max_kilobytes')],
        ]);
        if (! empty($data['shipment_id'])) {
            $ctx->shipments()->findOrFail($data['shipment_id']);
        }
        if ($data['visibility'] === 'sensitive') {
            abort_unless($ctx->allows('drivers.read_sensitive') || $ctx->allows('settings.manage'), 403);
        }
        $upload = $request->file('file');
        $binary = $upload->getContent();
        $path = 'documents/'.$ctx->organization->id.'/'.bin2hex(random_bytes(16));
        Storage::disk('local')->put($path, $binary);
        $document = Document::query()->create([
            'organization_id' => $ctx->organization->id,
            'shipment_id' => $data['shipment_id'] ?? null,
            'doc_type' => $data['doc_type'],
            'visibility' => $data['visibility'],
            'title' => $data['title'],
            'created_by' => $ctx->user->id,
        ]);
        StoredFile::query()->create([
            'organization_id' => $ctx->organization->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $upload->getClientOriginalName(),
            'mime_type' => (string) $upload->getMimeType(),
            'size_bytes' => strlen($binary),
            'checksum_sha256' => hash('sha256', $binary),
            'kind' => 'document',
            'document_id' => $document->id,
            'created_by' => $ctx->user->id,
        ]);

        return back();
    }

    public function download(int $file): StreamedResponse
    {
        $ctx = $this->authorizePermission('documents.read');
        $stored = StoredFile::query()->where('organization_id', $ctx->organization->id)->findOrFail($file);
        if ($stored->document_id) {
            $document = Document::query()->findOrFail($stored->document_id);
            if ($document->visibility === 'sensitive' && ! $ctx->allows('drivers.read_sensitive')) {
                abort(403);
            }
            if ($ctx->role === 'customer' && $document->visibility !== 'customer_visible') {
                abort(403);
            }
            if ($document->shipment_id) {
                $ctx->shipments()->findOrFail($document->shipment_id);
            }
        }
        if ($stored->evidence_id && ($ctx->role === 'customer' || $ctx->role === 'driver')) {
            $shipmentId = Shipment::query()->whereKey(
                DeliveryEvidence::query()->whereKey($stored->evidence_id)->value('shipment_id')
            )->value('id');
            $ctx->shipments()->findOrFail($shipmentId);
        }
        abort_unless(Storage::disk($stored->disk)->exists($stored->path), 404);

        return Storage::disk($stored->disk)->download($stored->path, $stored->original_name);
    }
}
