<?php

namespace App\Managers\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PermissionEnum;
use App\Models\Folio;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Traits\HasMessages;
use Illuminate\Support\Facades\DB;

class PaymentManager
{
    use HasMessages;

    public function __construct(private readonly FolioManager $folios) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(User $actor, Folio $folio, array $data): Payment
    {
        $this->folios->assertPermission($actor, PermissionEnum::FoliosRecordPayment);
        $this->folios->assertOwned($actor, $folio);
        $this->folios->assertOpen($folio);

        $amount = (int) $data['amountCents'];
        if ($amount <= 0) {
            $this->throwValidationError('amountCents', 'messages.folios.payment_amount_invalid');
        }

        $method = PaymentMethod::tryFrom((string) $data['method']);
        if ($method === null) {
            $this->throwValidationError('method', 'messages.folios.payment_method_invalid');
        }

        return DB::transaction(function () use ($actor, $folio, $data, $amount, $method) {
            $payment = Payment::query()->create([
                'property_id' => $folio->property_id,
                'folio_id' => $folio->id,
                'method' => $method,
                'amount_cents' => $amount,
                'currency' => $folio->currency,
                'paid_at' => $data['paidAt'] ?? now(),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $actor->id,
            ]);

            PaymentAllocation::query()->create([
                'payment_id' => $payment->id,
                'folio_id' => $folio->id,
                'amount_cents' => $amount,
            ]);

            $this->folios->recalculate($folio);
            $this->folios->recordEvent($folio, $actor, 'payment.recorded', Payment::class, $payment->id);

            return $payment->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function refund(User $actor, Payment $payment, array $data): Payment
    {
        $this->folios->assertPermission($actor, PermissionEnum::FoliosRefundPayment);
        $folio = $payment->folio;
        $this->folios->assertOwned($actor, $folio);
        $this->folios->assertOpen($folio);

        if ($payment->isVoided()) {
            $this->throwValidationError('payment', 'messages.folios.payment_already_voided');
        }

        if ($payment->amount_cents <= 0) {
            $this->throwValidationError('payment', 'messages.folios.cannot_refund_refund');
        }

        $amount = (int) ($data['amountCents'] ?? $payment->amount_cents);
        if ($amount <= 0 || $amount > $payment->amount_cents) {
            $this->throwValidationError('amountCents', 'messages.folios.payment_amount_invalid');
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            $this->throwValidationError('reason', 'messages.folios.void_reason_required');
        }

        return DB::transaction(function () use ($actor, $folio, $payment, $amount, $reason, $data) {
            $refund = Payment::query()->create([
                'property_id' => $folio->property_id,
                'folio_id' => $folio->id,
                'method' => $payment->method,
                'amount_cents' => -$amount,
                'currency' => $folio->currency,
                'paid_at' => $data['paidAt'] ?? now(),
                'reference' => $data['reference'] ?? $payment->reference,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $actor->id,
                'refund_of_payment_id' => $payment->id,
            ]);

            PaymentAllocation::query()->create([
                'payment_id' => $refund->id,
                'folio_id' => $folio->id,
                'amount_cents' => -$amount,
            ]);

            $this->folios->recalculate($folio);
            $this->folios->recordEvent($folio, $actor, 'payment.refunded', Payment::class, $refund->id, $reason, [
                'of_payment_id' => $payment->id,
                'amount_cents' => $amount,
            ]);

            return $refund->refresh();
        });
    }

    public function void(User $actor, Payment $payment, string $reason): Payment
    {
        $this->folios->assertPermission($actor, PermissionEnum::FoliosRefundPayment);
        $folio = $payment->folio;
        $this->folios->assertOwned($actor, $folio);
        $this->folios->assertOpen($folio);

        if ($payment->isVoided()) {
            $this->throwValidationError('payment', 'messages.folios.payment_already_voided');
        }

        if (trim($reason) === '') {
            $this->throwValidationError('reason', 'messages.folios.void_reason_required');
        }

        return DB::transaction(function () use ($actor, $payment, $folio, $reason) {
            $payment->voided_at = now();
            $payment->voided_by = $actor->id;
            $payment->void_reason = $reason;
            $payment->save();

            $this->folios->recalculate($folio);
            $this->folios->recordEvent($folio, $actor, 'payment.voided', Payment::class, $payment->id, $reason);

            return $payment->refresh();
        });
    }
}
