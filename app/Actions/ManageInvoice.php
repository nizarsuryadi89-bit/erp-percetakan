<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoicePayment;
use App\Models\PaymentMethod;
use App\Models\PrintOrder;
use App\Models\User;
use App\Support\CashierAccess;
use App\Support\DeskprintAccess;
use App\Support\TenantContext;
use Brick\Math\BigDecimal;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ManageInvoice
{
    public function __construct(private CashierTransaction $transaction, private CashierAccess $access, private DeskprintAccess $orders, private TenantContext $context) {}

    /** @param array<string,mixed> $data */
    public function issue(User $actor, PrintOrder $order, array $data): Invoice
    {
        $this->orders->order($actor, $order);
        $result = $this->transaction->run($actor, $order->branch_id, 'invoice.issue', 'order.'.$order->id.'.invoice', $data, function (Branch $branch) use ($actor, $order): array {
            $order = PrintOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'confirmed') {
                throw ValidationException::withMessages(['status' => 'Hanya SPK terkonfirmasi yang dapat ditagih.']);
            }
            $existing = Invoice::where('print_order_id', $order->id)->first();
            if ($existing) {
                if ($existing->status === 'void') {
                    throw ValidationException::withMessages(['status' => 'Invoice SPK ini telah di-void. Buat SPK pengganti; nomor lama tidak diterbitkan ulang.']);
                }

                return ['invoice_id' => $existing->id];
            }
            $items = [];
            $total = BigDecimal::zero();
            foreach ($order->items()->where('state', 'active')->orderBy('id')->get() as $item) {
                $snapshot = Arr::only($item->snapshot, ['version', 'pricing_rule', 'price_revision', 'product_id', 'product_name', 'unit_name', 'detail_product', 'qty', 'input_length', 'input_width', 'length', 'width', 'dimension_unit', 'pricing_basis', 'tier_basis', 'billable_qty', 'billing_unit', 'pricing_qty', 'lookup_qty', 'qty_min', 'qty_max', 'unit_price', 'minimum_charge', 'minimum_applied', 'rounding', 'subtotal', 'specifications']);
                $snapshot['specifications'] = Arr::only($snapshot['specifications'], ['variant', 'machine', 'print_side', 'finishing']);
                $items[] = ['source_item_id' => $item->id, 'snapshot' => $snapshot, 'notes' => $item->notes, 'process_location' => $item->process_location, 'deadline' => $item->deadline?->toISOString()];
                $total = $total->plus($snapshot['subtotal']);
            }
            if (! $items || ! $total->isPositive() || ! $total->isEqualTo($order->total)) {
                throw ValidationException::withMessages(['total' => 'Snapshot SPK tidak sesuai atau total tidak positif. Hubungi pengelola.']);
            }
            $branch->increment('invoice_sequence');
            $invoice = Invoice::create(['branch_id' => $branch->id, 'print_order_id' => $order->id, 'issued_by' => $actor->id, 'issuer_name' => $actor->name, 'number' => 'INV-'.$branch->id.'-'.now($this->context->tenant()->timezone)->format('Ym').'-'.str_pad((string) $branch->invoice_sequence, 6, '0', STR_PAD_LEFT), 'order_number' => $order->number, 'company_snapshot' => $order->company_snapshot, 'customer_snapshot' => $order->customer_snapshot, 'items_snapshot' => $items, 'notes' => $order->notes, 'total' => (string) $total->toScale(2), 'status' => 'issued', 'payment_status' => 'unpaid', 'paid_amount' => '0.00', 'balance' => (string) $total->toScale(2), 'issued_at' => now()]);
            $this->event($actor, $invoice, 'invoice.diterbitkan', ['order_id' => $order->id, 'total' => $invoice->total]);

            return ['invoice_id' => $invoice->id];
        });

        return Invoice::findOrFail($result['invoice_id']);
    }

    /** @param array<string,mixed> $data */
    public function pay(User $actor, Invoice $invoice, array $data): Invoice
    {
        $this->access->invoice($actor, $invoice, 'payment.record');
        $this->transaction->run($actor, $invoice->branch_id, 'payment.record', 'invoice.'.$invoice->id.'.payment', $data, function () use ($actor, $invoice, $data): array {
            $invoice = $this->lockedActive($invoice);
            $before = $invoice->summary();
            $total = BigDecimal::zero();
            $entries = [];
            foreach ($data['payments'] as $index => $input) {
                $method = PaymentMethod::where('is_active', true)->lockForUpdate()->findOrFail($input['payment_method_id']);
                if (! is_string($input['amount']) || ! preg_match('/^\d{1,13}(\.\d{1,2})?$/D', $input['amount']) || ! BigDecimal::of($input['amount'])->isPositive()) {
                    throw ValidationException::withMessages(['payments.'.$index.'.amount' => 'Nominal harus decimal positif, maksimal 2 digit desimal.']);
                }
                if ($method->kind !== 'cash' && trim($input['reference'] ?? '') === '') {
                    throw ValidationException::withMessages(['payments.'.$index.'.reference' => 'Referensi bukti wajib untuk pembayaran non-tunai.']);
                }
                $time = Carbon::createFromFormat('!Y-m-d\TH:i', $input['paid_at'], $this->context->tenant()->timezone)->utc();
                if ($time->isFuture()) {
                    throw ValidationException::withMessages(['payments.'.$index.'.paid_at' => 'Waktu pembayaran tidak boleh di masa depan.']);
                }
                $amount = BigDecimal::of($input['amount'])->toScale(2);
                $total = $total->plus($amount);
                $entries[] = ['branch_id' => $invoice->branch_id, 'invoice_id' => $invoice->id, 'payment_method_id' => $method->id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'entry_type' => 'payment', 'reverses_payment_id' => null, 'amount' => (string) $amount, 'method_snapshot' => $method->only(['kind', 'name', 'details']), 'reference' => $input['reference'] ?? null, 'notes' => $input['notes'] ?? null, 'reason' => null, 'paid_at' => $time];
            }
            if (! $total->isPositive() || $total->isGreaterThan($before['balance'])) {
                throw ValidationException::withMessages(['payments' => 'Pembayaran melebihi sisa tagihan atau tidak positif. Kembalian belum didukung.']);
            }
            $ids = [];
            foreach ($entries as $entry) {
                $ids[] = InvoicePayment::create($entry)->id;
            }
            $after = $this->project($invoice);
            $this->event($actor, $invoice, 'invoice.pembayaran_dicatat', ['payment_ids' => $ids, 'amount' => (string) $total->toScale(2), 'before' => $before, 'after' => $after]);

            return ['invoice_id' => $invoice->id];
        });

        return $invoice->refresh();
    }

    /** @param array<string,mixed> $data */
    public function reverse(User $actor, Invoice $invoice, InvoicePayment $payment, array $data): Invoice
    {
        $this->access->invoice($actor, $invoice, 'payment.reverse');
        abort_unless($payment->invoice_id === $invoice->id, 404);
        $this->transaction->run($actor, $invoice->branch_id, 'payment.reverse', 'invoice.'.$invoice->id.'.reverse.'.$payment->id, $data, function () use ($actor, $invoice, $payment, $data): array {
            $invoice = $this->lockedActive($invoice);
            $before = $invoice->summary();
            $original = $invoice->payments()->where('entry_type', 'payment')->findOrFail($payment->id);
            if ($invoice->payments()->where('reverses_payment_id', $original->id)->exists()) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah dikoreksi.']);
            }
            if (trim($data['reason'] ?? '') === '' || ! ($data['record_was_incorrect'] ?? false)) {
                throw ValidationException::withMessages(['reason' => 'Koreksi hanya untuk pencatatan salah dengan alasan, bukan pengembalian dana.']);
            }
            $reversal = InvoicePayment::create(['branch_id' => $invoice->branch_id, 'invoice_id' => $invoice->id, 'payment_method_id' => $original->payment_method_id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'entry_type' => 'reversal', 'reverses_payment_id' => $original->id, 'amount' => $original->amount, 'method_snapshot' => $original->method_snapshot, 'reference' => $original->reference, 'notes' => null, 'reason' => $data['reason'], 'paid_at' => now()]);
            $after = $this->project($invoice);
            $this->event($actor, $invoice, 'invoice.pembayaran_dikoreksi', ['payment_id' => $original->id, 'reversal_id' => $reversal->id, 'amount' => $original->amount, 'reason' => $data['reason'], 'correction_only' => true, 'before' => $before, 'after' => $after]);

            return ['invoice_id' => $invoice->id];
        });

        return $invoice->refresh();
    }

    /** @param array<string,mixed> $data */
    public function void(User $actor, Invoice $invoice, array $data): Invoice
    {
        $this->access->invoice($actor, $invoice, 'invoice.void');
        $this->transaction->run($actor, $invoice->branch_id, 'invoice.void', 'invoice.'.$invoice->id.'.void', $data, function () use ($actor, $invoice, $data): array {
            $invoice = $this->lockedActive($invoice);
            $summary = $invoice->summary();
            if ($summary['paid'] !== '0.00') {
                throw ValidationException::withMessages(['status' => 'Invoice memiliki pembayaran sah. Void diblokir; refund nyata belum tersedia.']);
            }
            if (trim($data['reason'] ?? '') === '') {
                throw ValidationException::withMessages(['reason' => 'Alasan void wajib diisi.']);
            }
            $invoice->update(['status' => 'void']);
            $this->event($actor, $invoice, 'invoice.di_void', ['reason' => $data['reason'], 'before' => $summary, 'after' => $invoice->summary()]);

            return ['invoice_id' => $invoice->id];
        });

        return $invoice->refresh();
    }

    private function lockedActive(Invoice $invoice): Invoice
    {
        $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
        if ($invoice->status !== 'issued' || $invoice->order->status !== 'confirmed') {
            throw ValidationException::withMessages(['status' => 'Invoice tidak aktif atau SPK tidak terkonfirmasi.']);
        }

        return $invoice;
    }

    /** @return array{paid:string,balance:string,payment_status:string,collectible:string} */
    private function project(Invoice $invoice): array
    {
        $invoice->unsetRelation('payments');
        $summary = $invoice->summary();
        $invoice->update(['paid_amount' => $summary['paid'], 'balance' => $summary['balance'], 'payment_status' => $summary['payment_status']]);

        return $summary;
    }

    /** @param array<string,mixed> $metadata */
    private function event(User $actor, Invoice $invoice, string $action, array $metadata): void
    {
        InvoiceEvent::create(['branch_id' => $invoice->branch_id, 'invoice_id' => $invoice->id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, 'metadata' => $metadata]);
        AuditLog::create(['actor_id' => $actor->id, 'action' => $action, 'metadata' => ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->number, 'branch_id' => $invoice->branch_id, ...$metadata]]);
    }
}
