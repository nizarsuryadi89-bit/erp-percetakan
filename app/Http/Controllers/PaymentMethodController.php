<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use App\Support\TenantContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodController extends Controller
{
    public function __construct(private TenantContext $context) {}

    public function index(): Response
    {
        Gate::authorize('payment-methods.manage');

        return Inertia::render('cashier/methods', ['methods' => PaymentMethod::orderBy('name')->get()]);
    }

    public function store(StorePaymentMethodRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $request->validate(['name' => [Rule::unique('payment_methods')->where('tenant_id', $this->context->id())]]);
        try {
            DB::transaction(function () use ($data, $request): void {
                $method = PaymentMethod::create([...$data, 'is_active' => true]);
                AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'metode_pembayaran.dibuat', 'metadata' => ['method_id' => $method->id, 'kind' => $method->kind]]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['name' => 'Nama metode sudah digunakan.']);
        }

        return to_route('payment-methods.index')->with('success', 'Metode pembayaran ditambahkan.');
    }

    public function update(Request $request, PaymentMethod $method): RedirectResponse
    {
        Gate::authorize('payment-methods.manage');
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($method, $data, $request): void {
            $method = PaymentMethod::whereKey($method->id)->lockForUpdate()->firstOrFail();
            $before = $method->is_active;
            $method->update($data);
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'metode_pembayaran.status_diubah', 'metadata' => ['method_id' => $method->id, 'from' => $before, 'to' => $method->is_active]]);
        });

        return to_route('payment-methods.index')->with('success', 'Status metode pembayaran diperbarui.');
    }
}
