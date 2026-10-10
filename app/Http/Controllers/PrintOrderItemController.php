<?php

namespace App\Http\Controllers;

use App\Actions\CalculateProductPrice;
use App\Actions\ManagePrintOrder;
use App\Http\Requests\ChangePrintOrderRequest;
use App\Http\Requests\StorePrintOrderItemRequest;
use App\Models\PrintOrder;
use App\Models\PrintOrderItem;
use App\Models\Product;
use App\Support\DeskprintAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrintOrderItemController extends Controller
{
    public function catalog(Request $request, PrintOrder $order, DeskprintAccess $access): JsonResponse
    {
        $access->order($request->user(), $order);
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'product_id' => ['nullable', 'integer']]);
        $query = Product::where('is_active', true);
        if (! empty($data['product_id'])) {
            $query->whereKey($data['product_id']);
        } else {
            $search = $data['search'] ?? '';
            $query->where(fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%'));
        }

        return response()->json(['products' => $query->with(['prices' => fn (HasMany $query) => $query->where('is_active', true)->select(['id', 'product_id', 'variant', 'machine', 'print_side', 'finishing'])])->orderBy('name')->orderBy('id')->limit(30)->get()]);
    }

    public function preview(StorePrintOrderItemRequest $request, PrintOrder $order, DeskprintAccess $access, CalculateProductPrice $calculate): JsonResponse
    {
        $access->order($request->user(), $order, true);
        abort_unless($order->status === 'draft', 422, 'Preview hanya tersedia untuk draft.');

        return response()->json(['quote' => $calculate->handle(Product::findOrFail($request->validated('product_id')), $request->validated())]);
    }

    public function store(StorePrintOrderItemRequest $request, PrintOrder $order, ManagePrintOrder $orders): RedirectResponse
    {
        $orders->item($request->user(), $order, $request->validated());

        return to_route('print-orders.show', $order)->with('success', 'Item dihitung dan disimpan.');
    }

    public function update(StorePrintOrderItemRequest $request, PrintOrder $order, PrintOrderItem $item, ManagePrintOrder $orders): RedirectResponse
    {
        abort_unless($item->print_order_id === $order->id, 404);
        $orders->item($request->user(), $order, $request->validated(), $item);

        return to_route('print-orders.show', $order)->with('success', 'Revisi disimpan. Snapshot sebelumnya tetap tersedia.');
    }

    public function destroy(ChangePrintOrderRequest $request, PrintOrder $order, PrintOrderItem $item, ManagePrintOrder $orders): RedirectResponse
    {
        abort_unless($item->print_order_id === $order->id, 404);
        $orders->remove($request->user(), $order, $item, $request->validated());

        return to_route('print-orders.show',$order)->with('success','Item dikeluarkan dari draft.');
    }
}
