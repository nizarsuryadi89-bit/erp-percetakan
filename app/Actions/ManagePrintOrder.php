<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PrintOrder;
use App\Models\PrintOrderEvent;
use App\Models\PrintOrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\DeskprintAccess;
use App\Support\TenantContext;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ManagePrintOrder
{
    public function __construct(private DeskprintTransaction $transaction, private CalculateProductPrice $calculator, private DeskprintAccess $access, private TenantContext $context) {}

    /** @param array<string,mixed> $data */
    public function create(User $actor, array $data): PrintOrder
    {
        $result = $this->transaction->run($actor, (int) $data['branch_id'], 'order.create', $data, function (Branch $branch) use ($actor, $data): array {
            $customer = Customer::where('branch_id', $branch->id)->findOrFail($data['customer_id']);
            $branch->increment('spk_sequence');
            $tenant = $this->context->tenant();
            $order = PrintOrder::create([
                'branch_id' => $branch->id, 'customer_id' => $customer->id, 'created_by' => $actor->id, 'creator_name' => $actor->name,
                'number' => 'SPK-'.$branch->id.'-'.now($tenant->timezone)->format('Ym').'-'.str_pad((string) $branch->spk_sequence, 6, '0', STR_PAD_LEFT),
                'method' => $data['method'], 'marketplace' => $data['method'] === 'marketplace' ? ['name' => $data['marketplace_name'] ?? '', 'reference' => $data['marketplace_reference'] ?? '', 'tracking' => $data['marketplace_tracking'] ?? ''] : null,
                'customer_snapshot' => $customer->only(['name', 'phone', 'address']),
                'company_snapshot' => [...$tenant->only(['name', 'phone', 'address', 'timezone']), 'branch_name' => $branch->name, 'branch_code' => $branch->code],
                'notes' => $data['notes'] ?? null, 'deadline' => $this->deadline($data['deadline'] ?? null), 'status' => 'draft', 'version' => 1, 'total' => '0.00',
            ]);
            $this->event($actor, $order, 'spk.dibuat', ['status' => 'draft']);

            return ['order_id' => $order->id];
        });

        return PrintOrder::findOrFail($result['order_id']);
    }

    /** @param array<string,mixed> $data */
    public function item(User $actor, PrintOrder $order, array $data, ?PrintOrderItem $previous = null): PrintOrder
    {
        $this->access->order($actor, $order, true);
        $this->transaction->run($actor, $order->branch_id, 'order.'.$order->id.'.item.'.($previous?->id ?? 'new'), $data, function () use ($actor, $order, $data, $previous): array {
            $order = PrintOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->editable($order, (int) $data['version']);
            $previous = $previous ? $order->items()->where('state', 'active')->findOrFail($previous->id) : null;
            if (! $previous && $order->items()->where('state', 'active')->count() >= 100) {
                throw ValidationException::withMessages(['product_id' => 'Maksimal 100 item aktif per SPK.']);
            }
            $product = Product::whereKey($data['product_id'])->lockForUpdate()->firstOrFail();
            $snapshot = $this->calculator->handle($product, $data);
            $price = $product->prices()->findOrFail($snapshot['price_id']);
            $item = PrintOrderItem::create(['print_order_id' => $order->id, 'product_id' => $product->id, 'replaces_item_id' => $previous?->id, 'state' => 'active', 'snapshot' => $snapshot, 'cost_snapshot' => ['price_id' => $price->id, 'hpp' => $price->hpp], 'notes' => $data['notes'] ?? null, 'process_location' => $data['process_location'] ?? null, 'deadline' => $this->deadline($data['deadline'] ?? null)]);
            $previous?->update(['state' => 'superseded']);
            $this->recalculate($order);
            $this->event($actor, $order, $previous ? 'spk.item_direvisi' : 'spk.item_ditambah', ['item_id' => $item->id, 'previous_item_id' => $previous?->id]);

            return ['order_id' => $order->id];
        });

        return $order->refresh();
    }

    /** @param array<string,mixed> $data */
    public function remove(User $actor, PrintOrder $order, PrintOrderItem $item, array $data): PrintOrder
    {
        $this->access->order($actor, $order, true);
        $this->transaction->run($actor, $order->branch_id, 'order.'.$order->id.'.remove.'.$item->id, $data, function () use ($actor, $order, $item, $data): array {
            $order = PrintOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->editable($order, (int) $data['version']);
            $order->items()->where('state', 'active')->findOrFail($item->id)->update(['state' => 'removed']);
            $this->recalculate($order);
            $this->event($actor, $order, 'spk.item_dihapus', ['item_id' => $item->id]);

            return ['order_id' => $order->id];
        });

        return $order->refresh();
    }

    /** @param array<string,mixed> $data */
    public function transition(User $actor, PrintOrder $order, array $data): PrintOrder
    {
        $this->access->order($actor, $order, true);
        $this->transaction->run($actor, $order->branch_id, 'order.'.$order->id.'.status', $data, function () use ($actor, $order, $data): array {
            $order = PrintOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $this->version($order, (int) $data['version']);
            $target = $data['status'];
            if (! in_array($target, ['confirmed', 'cancelled'], true) || $order->status === 'cancelled' || ($target === 'confirmed' && $order->status !== 'draft')) {
                throw ValidationException::withMessages(['status' => 'Transisi status tidak diizinkan. SPK terkonfirmasi tidak dapat dibuka ulang.']);
            }
            if ($target === 'confirmed' && ! $order->items()->where('state', 'active')->exists()) {
                throw ValidationException::withMessages(['status' => 'Tambahkan minimal satu item sebelum konfirmasi.']);
            }
            if ($target === 'cancelled' && trim($data['reason'] ?? '') === '') {
                throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi.']);
            }
            if ($target === 'cancelled' && Schema::hasTable('invoices') && Invoice::where('print_order_id', $order->id)->where('status', 'issued')->exists()) {
                throw ValidationException::withMessages(['status' => 'SPK memiliki invoice aktif. Pengelola harus menyelesaikan review dan void invoice terlebih dahulu.']);
            }
            $from = $order->status;
            $order->update(['status' => $target, 'version' => $order->version + 1]);
            $this->event($actor, $order, 'spk.status_diubah', ['from' => $from, 'to' => $target, 'reason' => $data['reason'] ?? null]);

            return ['order_id' => $order->id];
        });

        return $order->refresh();
    }

    private function editable(PrintOrder $order, int $version): void
    {
        $this->version($order, $version);
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Item hanya dapat diubah saat SPK draft.']);
        }
    }

    private function version(PrintOrder $order, int $version): void
    {
        if ($order->version !== $version) {
            throw ValidationException::withMessages(['version' => 'SPK telah berubah. Muat ulang dan periksa rincian sebelum mencoba lagi.']);
        }
    }

    private function recalculate(PrintOrder $order): void
    {
        $total = BigDecimal::zero();
        foreach ($order->items()->where('state', 'active')->get() as $item) {
            $total = $total->plus($item->snapshot['subtotal']);
        }
        if ($total->isGreaterThan('9999999999999.99')) {
            throw ValidationException::withMessages(['total' => 'Total SPK melampaui batas nominal. Pisahkan pesanan.']);
        }
        $order->update(['total' => (string) $total->toScale(2), 'version' => $order->version + 1]);
    }

    private function deadline(?string $value): ?Carbon
    {
        return $value ? Carbon::createFromFormat('!Y-m-d\TH:i', $value, $this->context->tenant()->timezone)->utc() : null;
    }

    /** @param array<string,mixed> $metadata */
    private function event(User $actor, PrintOrder $order, string $action, array $metadata): void
    {
        PrintOrderEvent::create(['print_order_id' => $order->id, 'actor_id' => $actor->id, 'actor_name' => $actor->name, 'action' => $action, 'metadata' => $metadata]);
        AuditLog::create(['actor_id' => $actor->id, 'action' => $action, 'metadata' => ['order_id' => $order->id, 'branch_id' => $order->branch_id, ...$metadata]]);
    }
}
