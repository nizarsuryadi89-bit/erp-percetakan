<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('produk.manage') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $unique = Rule::unique('products', 'code')->where('tenant_id', app(TenantContext::class)->id());
        $product = $this->route('product');
        if ($product instanceof Product) {
            $unique->ignore($product->id);
        }

        return [
            'code' => ['required', 'string', 'max:40', 'alpha_dash', $unique],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'unit_name' => ['required', 'string', 'max:40'],
            'pricing_basis' => ['required', Rule::in(['quantity', 'length', 'area'])],
            'tier_basis' => ['required', Rule::in(['quantity', 'billable'])],
            'dimension_unit' => ['required', Rule::in(['cm', 'm'])],
            'minimum_length' => ['required', 'numeric', 'min:0', 'max:10000', 'regex:/^\d+(\.\d{1,4})?$/'],
            'minimum_width' => ['required', 'numeric', 'min:0', 'max:10000', 'regex:/^\d+(\.\d{1,4})?$/'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['code.unique' => 'Kode produk sudah digunakan di perusahaan ini.', 'pricing_basis.in' => 'Pilih dasar harga jumlah, panjang, atau luas.', '*.regex' => 'Gunakan angka biasa dengan maksimal 4 angka desimal, tanpa pemisah ribuan.'];
    }
}
