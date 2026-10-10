<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', fn (Blueprint $table) => $table->unsignedBigInteger('invoice_sequence')->default(0));
        Schema::table('print_orders', fn (Blueprint $table) => $table->unique(['tenant_id', 'branch_id', 'id'], 'orders_invoice_scope_unique'));
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('kind', 20);
            $table->string('name', 100);
            $table->string('details', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->unique(['tenant_id', 'name']);
        });
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('branch_id');
            $table->foreignId('print_order_id');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('issuer_name');
            $table->string('number', 100);
            $table->string('order_number', 100);
            $table->json('company_snapshot');
            $table->json('customer_snapshot');
            $table->json('items_snapshot');
            $table->text('notes')->nullable();
            if (DB::connection()->getDriverName() === 'sqlite') {
                $table->string('total', 16);
            } else {
                $table->decimal('total', 15, 2);
            }
            $table->string('status', 20)->default('issued');
            $table->string('payment_status', 20)->default('unpaid');
            if (DB::connection()->getDriverName() === 'sqlite') {
                $table->string('paid_amount', 16)->default('0.00');
            } else {
                $table->decimal('paid_amount', 15, 2)->default(0);
            }
            if (DB::connection()->getDriverName() === 'sqlite') {
                $table->string('balance', 16);
            } else {
                $table->decimal('balance', 15, 2);
            }
            $table->dateTime('issued_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'print_order_id']);
            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'branch_id', 'id']);
            $table->foreign(['tenant_id', 'branch_id', 'print_order_id'], 'invoices_order_scope_foreign')->references(['tenant_id', 'branch_id', 'id'])->on('print_orders')->restrictOnDelete();
            $table->index(['tenant_id', 'branch_id', 'status', 'payment_status', 'id'], 'invoices_list_index');
        });
        Schema::create('invoice_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('branch_id');
            $table->foreignId('invoice_id');
            $table->foreignId('payment_method_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('entry_type', 20);
            $table->unsignedBigInteger('reverses_payment_id')->nullable();
            if (DB::connection()->getDriverName() === 'sqlite') {
                $table->string('amount', 16);
            } else {
                $table->decimal('amount', 15, 2);
            }
            $table->json('method_snapshot');
            $table->string('reference', 150)->nullable();
            $table->text('notes')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('paid_at');
            $table->timestamps();
            $table->unique(['tenant_id', 'branch_id', 'invoice_id', 'id'], 'payment_scope_unique');
            $table->unique(['tenant_id', 'reverses_payment_id'], 'payment_single_reversal_unique');
            $table->foreign(['tenant_id', 'branch_id', 'invoice_id'], 'payments_invoice_scope_foreign')->references(['tenant_id', 'branch_id', 'id'])->on('invoices')->restrictOnDelete();
            $table->foreign(['tenant_id', 'payment_method_id'], 'payments_method_scope_foreign')->references(['tenant_id', 'id'])->on('payment_methods')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id', 'invoice_id', 'reverses_payment_id'], 'payments_reversal_scope_foreign')->references(['tenant_id', 'branch_id', 'invoice_id', 'id'])->on('invoice_payments')->restrictOnDelete();
        });
        Schema::create('invoice_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('branch_id');
            $table->foreignId('invoice_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('action', 60);
            $table->json('metadata');
            $table->timestamps();
            $table->foreign(['tenant_id', 'branch_id', 'invoice_id'], 'events_invoice_scope_foreign')->references(['tenant_id', 'branch_id', 'id'])->on('invoices')->restrictOnDelete();
        });
        Schema::create('cashier_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('branch_id');
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('request_key');
            $table->char('fingerprint', 64);
            $table->json('result');
            $table->timestamps();
            $table->unique(['tenant_id', 'actor_id', 'request_key']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
        });
        DB::table('tenants')->orderBy('id')->each(function (object $tenant): void {
            foreach (['cash' => 'Tunai', 'transfer' => 'Transfer bank', 'debit' => 'Debit / EDC', 'ewallet' => 'E-wallet'] as $kind => $name) {
                DB::table('payment_methods')->insertOrIgnore(['tenant_id' => $tenant->id, 'kind' => $kind, 'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        foreach (['cashier_requests', 'invoice_events', 'invoice_payments', 'invoices', 'payment_methods'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('print_orders', fn (Blueprint $table) => $table->dropUnique('orders_invoice_scope_unique'));
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('invoice_sequence'));
    }
};
