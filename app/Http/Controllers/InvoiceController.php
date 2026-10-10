<?php

namespace App\Http\Controllers;

use App\Actions\ManageInvoice;
use App\Http\Requests\CorrectInvoiceRequest;
use App\Http\Requests\IssueInvoiceRequest;
use App\Http\Requests\StoreInvoicePaymentRequest;
use App\Http\Requests\VoidInvoiceRequest;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\PaymentMethod;
use App\Models\PrintOrder;
use App\Support\BranchAccess;
use App\Support\CashierAccess;
use App\Support\DeskprintAccess;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function __construct(private BranchAccess $branches, private CashierAccess $access, private DeskprintAccess $orders, private TenantContext $context) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'view' => ['nullable', Rule::in(['ready', 'invoices', 'receivables'])], 'status' => ['nullable', Rule::in(['unpaid', 'partially_paid', 'paid', 'void'])]]);
        $view = $filters['view'] ?? 'ready';
        $search = $filters['search'] ?? '';
        $branchIds = $this->branches->query($request->user())->select('branches.id');
        if ($view === 'ready') {
            $query = PrintOrder::whereIn('branch_id', $branchIds)->where('status', 'confirmed')->whereDoesntHave('invoice')->select(['id', 'number', 'branch_id', 'customer_snapshot', 'total', 'created_at']);
        } else {
            $query = Invoice::whereIn('branch_id', $branchIds)->select(['id', 'number', 'order_number', 'branch_id', 'customer_snapshot', 'total', 'status', 'payment_status', 'issued_at']);
            if ($view === 'receivables') {
                $query->where('status', 'issued')->whereIn('payment_status', ['unpaid', 'partially_paid']);
            }
            if (($filters['status'] ?? '') === 'void') {
                $query->where('status', 'void');
            } elseif ($filters['status'] ?? null) {
                $query->where('status', 'issued')->where('payment_status', $filters['status']);
            }
            $query->with('payments');
        }
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search, $view): void {
                $query->where('number', 'like', '%'.$search.'%')->orWhere('customer_snapshot->name', 'like', '%'.$search.'%');
                if ($view !== 'ready') {
                    $query->orWhere('order_number', 'like', '%'.$search.'%');
                }
            });
        }
        $records = $query->orderByDesc('id')->paginate(20)->withQueryString();
        if ($view !== 'ready') {
            $records->through(function (Invoice $invoice): array {
                $data = $invoice->toArray();
                unset($data['payments']);

                return [...$data, 'summary' => $invoice->summary()];
            });
        }

        return Inertia::render('cashier/index', ['records' => $records, 'filters' => $filters, 'view' => $view, 'canIssue' => $request->user()->can('invoice.issue'), 'canManageMethods' => $request->user()->can('payment-methods.manage')]);
    }

    public function create(Request $request, PrintOrder $order): Response|RedirectResponse
    {
        $this->orders->order($request->user(), $order);
        $this->access->branch($request->user(), $order->branch_id, 'invoice.issue');
        $existing = Invoice::where('print_order_id', $order->id)->first();
        if ($existing) {
            return to_route('invoices.show', $existing);
        }
        abort_unless($order->status === 'confirmed', 422, 'SPK harus terkonfirmasi.');
        $order->load(['items' => fn (HasMany $query) => $query->where('state', 'active')->orderBy('id')]);

        return Inertia::render('cashier/create', ['order' => $order, 'requestKey' => (string) Str::uuid(), 'timezone' => $order->company_snapshot['timezone']]);
    }

    public function store(IssueInvoiceRequest $request, PrintOrder $order, ManageInvoice $invoices): RedirectResponse
    {
        return to_route('invoices.show', $invoices->issue($request->user(), $order, $request->validated()))->with('success', 'Invoice telah diterbitkan. Pembayaran dicatat terpisah.');
    }

    public function show(Request $request, Invoice $invoice): Response
    {
        $this->access->invoice($request->user(), $invoice);

        return Inertia::render('cashier/show', $this->details($request, $invoice));
    }

    public function print(Request $request, Invoice $invoice): Response
    {
        $this->access->invoice($request->user(), $invoice);
        $input = $request->validate(['format' => ['nullable', Rule::in(['a4', 'thermal'])], 'receipt' => ['nullable', 'integer']]);
        $details = $this->details($request, $invoice);
        $receipt = null;
        if ($input['receipt'] ?? null) {
            $receipt = $invoice->payments()->where('entry_type', 'payment')->findOrFail($input['receipt'])->toArray();
        }

        return Inertia::render('cashier/print', [...$details, 'format' => $input['format'] ?? 'a4', 'receipt' => $receipt]);
    }

    public function payment(StoreInvoicePaymentRequest $request, Invoice $invoice, ManageInvoice $invoices): RedirectResponse
    {
        $invoices->pay($request->user(), $invoice, $request->validated());

        return to_route('invoices.show', $invoice)->with('success', 'Pembayaran tercatat di server. Periksa riwayat dan sisa tagihan.');
    }

    public function reverse(CorrectInvoiceRequest $request, Invoice $invoice, InvoicePayment $payment, ManageInvoice $invoices): RedirectResponse
    {
        $invoices->reverse($request->user(), $invoice, $payment, $request->validated());

        return to_route('invoices.show', $invoice)->with('success', 'Koreksi pencatatan tersimpan. Tidak ada pengembalian uang otomatis.');
    }

    public function void(VoidInvoiceRequest $request, Invoice $invoice, ManageInvoice $invoices): RedirectResponse
    {
        $invoices->void($request->user(), $invoice, $request->validated());

        return to_route('invoices.show', $invoice)->with('success', 'Invoice di-void; nomor dan riwayat dipertahankan.');
    }

    /** @return array<string,mixed> */
    private function details(Request $request, Invoice $invoice): array
    {
        $invoice->load(['payments' => fn (HasMany $query) => $query->orderBy('id'), 'events' => fn (HasMany $query) => $query->orderByDesc('id')]);

        return ['invoice' => [...$invoice->toArray(), 'summary' => $invoice->summary()], 'methods' => PaymentMethod::where('is_active', true)->orderBy('name')->get(['id', 'name', 'kind', 'details']), 'canPay' => $request->user()->can('payment.record'), 'canReverse' => $request->user()->can('payment.reverse'), 'canVoid' => $request->user()->can('invoice.void'), 'requestKey' => (string) Str::uuid(), 'timezone' => $invoice->company_snapshot['timezone'], 'inputTimezone' => $this->context->tenant()->timezone, 'inputTime' => now($this->context->tenant()->timezone)->format('Y-m-d\TH:i')];
    }
}
