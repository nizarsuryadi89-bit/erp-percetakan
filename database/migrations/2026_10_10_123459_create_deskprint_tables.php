<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', fn (Blueprint $table) => $table->unsignedBigInteger('spk_sequence')->default(0));
        Schema::create('branch_user', function (Blueprint $table): void {
            $table->foreignId('tenant_id');
            $table->foreignId('branch_id');
            $table->foreignId('user_id');
            $table->primary(['tenant_id', 'branch_id', 'user_id']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'user_id'])->on('tenant_user')->cascadeOnDelete();
        });
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('branch_id');
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'branch_id', 'id']);
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->index(['tenant_id', 'branch_id', 'name']);
        });
        Schema::create('print_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('branch_id');
            $table->foreignId('customer_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 100);
            $table->string('method', 20);
            $table->json('marketplace')->nullable();
            $table->json('customer_snapshot');
            $table->json('company_snapshot');
            $table->string('creator_name');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->dateTime('deadline')->nullable();
            $table->decimal('total', 15, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'id']);
            $table->foreign(['tenant_id', 'branch_id', 'customer_id'])->references(['tenant_id', 'branch_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->index(['tenant_id', 'branch_id', 'status', 'id']);
        });
        Schema::create('print_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('print_order_id');
            $table->foreignId('product_id');
            $table->unsignedBigInteger('replaces_item_id')->nullable();
            $table->string('state', 20)->default('active');
            $table->json('snapshot');
            $table->json('cost_snapshot');
            $table->text('notes')->nullable();
            $table->string('process_location', 150)->nullable();
            $table->dateTime('deadline')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'print_order_id', 'id']);
            $table->foreign(['tenant_id', 'print_order_id'])->references(['tenant_id', 'id'])->on('print_orders')->restrictOnDelete();
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['tenant_id', 'print_order_id', 'replaces_item_id'], 'items_replacement_foreign')->references(['tenant_id', 'print_order_id', 'id'])->on('print_order_items')->restrictOnDelete();
        });
        Schema::create('print_order_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id');
            $table->foreignId('print_order_id');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->string('action', 60);
            $table->json('metadata');
            $table->timestamps();
            $table->foreign(['tenant_id', 'print_order_id'])->references(['tenant_id', 'id'])->on('print_orders')->restrictOnDelete();
        });
        Schema::create('deskprint_requests', function (Blueprint $table): void {
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
    }

    public function down(): void
    {
        foreach (['deskprint_requests', 'print_order_events', 'print_order_items', 'print_orders', 'customers', 'branch_user'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('spk_sequence'));
    }
};
