<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\ProductQuoteController;
use App\Http\Controllers\TenantSwitchController;
use App\Http\Controllers\TenantUserController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/perusahaan/buat', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('/perusahaan', [CompanyController::class, 'store'])->middleware('throttle:10,1')->name('companies.store');
    Route::post('/perusahaan/beralih', [TenantSwitchController::class, 'store'])->name('companies.switch');
    Route::middleware('platform')->group(function (): void {
        Route::get('/platform', [PlatformController::class, 'index'])->name('platform.index');
        Route::get('/platform/perusahaan/{tenant:uuid}/modul', [PlatformController::class, 'show'])->name('platform.companies.show');
        Route::patch('/platform/perusahaan/{tenant:uuid}/modul', [PlatformController::class, 'update'])->name('platform.companies.update');
    });
    Route::middleware('tenant')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
        Route::get('/pengaturan/perusahaan', [CompanyController::class, 'edit'])->name('companies.edit');
        Route::patch('/pengaturan/perusahaan', [CompanyController::class, 'update'])->name('companies.update');
        Route::get('/pengaturan/cabang', [BranchController::class, 'index'])->name('branches.index');
        Route::post('/pengaturan/cabang', [BranchController::class, 'store'])->name('branches.store');
        Route::patch('/pengaturan/cabang/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::post('/cabang/beralih', [BranchController::class, 'switch'])->name('branches.switch');
        Route::get('/pengaturan/pengguna', [TenantUserController::class, 'index'])->name('tenant-users.index');
        Route::post('/pengaturan/pengguna', [TenantUserController::class, 'store'])->name('tenant-users.store');
        Route::patch('/pengaturan/pengguna/{user}', [TenantUserController::class, 'update'])->name('tenant-users.update');
        Route::get('/pengaturan/modul', [ModuleController::class, 'index'])->name('modules.index');
        Route::patch('/pengaturan/modul/{code}', [ModuleController::class, 'update'])->name('modules.update');
        Route::middleware('module:transaksi')->group(function (): void {
            Route::get('/transaksi/produk', [ProductController::class, 'index'])->name('products.index');
            Route::post('/transaksi/produk', [ProductController::class, 'store'])->name('products.store');
            Route::get('/transaksi/produk/{product}', [ProductController::class, 'show'])->name('products.show');
            Route::patch('/transaksi/produk/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::post('/transaksi/produk/{product}/harga', [ProductPriceController::class, 'store'])->name('product-prices.store');
            Route::patch('/transaksi/produk/{product}/harga/{price}', [ProductPriceController::class, 'update'])->name('product-prices.update');
            Route::post('/transaksi/produk/{product}/hitung', [ProductQuoteController::class, 'store'])->middleware('throttle:60,1')->name('product-quotes.store');
        });
        Route::get('/modul/{module:code}', [WorkspaceController::class, 'module'])->middleware('module')->name('module-workspace.show');
        foreach (array_keys(config('erp.modules')) as $code) {
            Route::get('/'.$code, [WorkspaceController::class, 'show'])->defaults('code', $code)->middleware('module:'.$code)->name($code.'.index');
        }
    });
});

require __DIR__.'/settings.php';
