<?php

namespace App\Http\Controllers;

use App\Actions\ManagePrintOrder;
use App\Http\Requests\ChangePrintOrderRequest;
use App\Http\Requests\StorePrintOrderRequest;
use App\Models\PrintOrder;
use App\Support\BranchAccess;
use App\Support\DeskprintAccess;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PrintOrderController extends Controller
{
    public function __construct(private BranchAccess $branches, private DeskprintAccess $access, private TenantContext $context) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['draft', 'confirmed', 'cancelled'])]]);
        $search = $filters['search'] ?? '';
        $query = PrintOrder::whereIn('branch_id', $this->branches->query($request->user())->select('branches.id'))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('number', 'like', '%'.$search.'%')->orWhere('customer_snapshot->name', 'like', '%'.$search.'%');
            }))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        return Inertia::render('deskprint/index', ['orders' => $query->orderByDesc('id')->paginate(20)->withQueryString(), 'filters' => $filters, 'canManage' => $request->user()->can('spk.manage')]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('spk.manage');

        return Inertia::render('deskprint/create', ['branches' => $this->branches->query($request->user())->orderBy('name')->get(['id', 'name']), 'orderKey' => (string) Str::uuid(), 'customerKey' => (string) Str::uuid(), 'timezone' => $this->context->tenant()->timezone]);
    }

    public function store(StorePrintOrderRequest $request, ManagePrintOrder $orders): RedirectResponse
    {
        return to_route('print-orders.show', $orders->create($request->user(), $request->validated()))->with('success', 'Draft SPK dibuat. Tambahkan produk pesanan.');
    }

    public function show(Request $request, PrintOrder $order): Response
    {
        $this->access->order($request->user(), $order);

        return Inertia::render('deskprint/show', $this->details($request, $order));
    }

    public function print(Request $request, PrintOrder $order): Response
    {
        $this->access->order($request->user(), $order);

        return Inertia::render('deskprint/print', $this->details($request, $order));
    }

    public function status(ChangePrintOrderRequest $request, PrintOrder $order, ManagePrintOrder $orders): RedirectResponse
    {
        $orders->transition($request->user(), $order, $request->validated());

        return to_route('print-orders.show', $order)->with('success', 'Status SPK diperbarui.');
    }

    /** @return array<string,mixed> */
    private function details(Request $request, PrintOrder $order): array
    {
        $order->load(['items' => fn (HasMany $query) => $query->orderBy('id'), 'events' => fn (HasMany $query) => $query->orderByDesc('id')]);

        return ['order' => $order, 'canManage' => $request->user()->can('spk.manage'), 'requestKey' => (string) Str::uuid(), 'timezone' => $order->company_snapshot['timezone']];
    }
}
