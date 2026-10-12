<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Identity\OrganizationContext;
use App\Domain\Shared\Activity;
use App\Models\Charge;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RecordCharge
{
    public function __construct(private Activity $activity) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(OrganizationContext $ctx, Shipment $shipment, array $input): Charge
    {
        abort_unless($ctx->allows('finance.write'), 403);
        $data = Validator::make($input, [
            'description' => ['required', 'string', 'max:200'],
            'kind' => ['required', Rule::in(['base', 'accessorial', 'discount'])],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'invoice_reference' => ['nullable', 'string', 'max:80'],
        ])->validate();

        return DB::transaction(function () use ($ctx, $shipment, $data): Charge {
            if ($data['kind'] === 'discount') {
                $subtotal = $this->subtotal($shipment);
                $existingDiscounts = (int) $shipment->charges()->where('kind', 'discount')->where('billing_status', '!=', 'void')->sum('amount_cents');
                if ($existingDiscounts + $data['amount_cents'] > $subtotal) {
                    throw ValidationException::withMessages([
                        'amount_cents' => 'Discounts cannot exceed the non-void base and accessorial total.',
                    ]);
                }
            }

            $charge = Charge::query()->create([
                'organization_id' => $ctx->organization->id,
                'shipment_id' => $shipment->id,
                'customer_id' => $shipment->customer_id,
                'description' => $data['description'],
                'kind' => $data['kind'],
                'amount_cents' => $data['amount_cents'],
                'currency' => $ctx->organization->currency,
                'billing_status' => filled($data['invoice_reference'] ?? null) ? 'invoiced' : 'unbilled',
                'payment_status' => 'unpaid',
                'invoice_reference' => $data['invoice_reference'] ?? null,
                'created_by' => $ctx->user->id,
            ]);
            $this->activity->record($ctx, 'charge.created', 'charge', $charge->id);

            return $charge;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(OrganizationContext $ctx, Charge $charge, array $input): Charge
    {
        abort_unless($ctx->allows('finance.write'), 403);
        $data = Validator::make($input, [
            'billing_status' => ['required', Rule::in(['unbilled', 'invoiced', 'void'])],
            'payment_status' => ['required', Rule::in(['unpaid', 'partial', 'paid', 'written_off'])],
            'invoice_reference' => ['nullable', 'string', 'max:80'],
            'external_accounting_ref' => ['nullable', 'string', 'max:80'],
        ])->validate();
        $charge->fill($data)->save();
        $this->activity->record($ctx, 'charge.updated', 'charge', $charge->id, $data);

        return $charge;
    }

    private function subtotal(Shipment $shipment): int
    {
        return (int) $shipment->charges()
            ->whereIn('kind', ['base', 'accessorial'])
            ->where('billing_status', '!=', 'void')
            ->sum('amount_cents');
    }
}
