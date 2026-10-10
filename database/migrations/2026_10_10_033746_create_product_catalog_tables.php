<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->string('category', 100);
            $table->string('unit_name', 40);
            $table->string('pricing_basis', 20)->default('quantity');
            $table->string('tier_basis', 20)->default('quantity');
            $table->string('dimension_unit', 10)->default('cm');
            $table->decimal('minimum_length', 9, 4)->default(0);
            $table->decimal('minimum_width', 9, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'name', 'id']);
        });
        Schema::create('product_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id');
            $table->string('detail_product');
            foreach (['variant', 'machine', 'print_side', 'finishing'] as $column) {
                $table->string($column, 100)->default('');
            }
            $table->decimal('qty_min', 14, 4)->default(1);
            $table->decimal('qty_max', 14, 4)->nullable();
            $table->decimal('unit_price', 15, 2);
            $table->decimal('hpp', 15, 2)->default(0);
            $table->decimal('minimum_charge', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign(['tenant_id', 'product_id'])->references(['tenant_id', 'id'])->on('products')->cascadeOnDelete();
            $table->index(['tenant_id', 'product_id', 'is_active', 'qty_min']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('products');
    }
};
