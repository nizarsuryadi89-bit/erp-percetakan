<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('produk.manage') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $amount = ['required', 'numeric', 'min:0', 'max:9999999999999.99', 'regex:/^\d+(\.\d{1,2})?$/'];

        return [
            'detail_product' => ['required', 'string', 'max:255'],
            'variant' => ['nullable', 'string', 'max:100'],
            'machine' => ['nullable', 'string', 'max:100'],
            'print_side' => ['nullable', 'string', 'max:100'],
            'finishing' => ['nullable', 'string', 'max:100'],
            'qty_min' => ['required', 'numeric', 'min:0', 'max:1000000', 'regex:/^\d+(\.\d{1,4})?$/'],
            'qty_max' => ['nullable', 'numeric', 'gte:qty_min', 'max:1000000', 'regex:/^\d+(\.\d{1,4})?$/'],
            'unit_price' => $amount,
            'hpp' => $amount,
            'minimum_charge' => $amount,
            'is_active' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['qty_max.gte' => 'Batas jumlah maksimum harus sama atau lebih besar dari minimum.', '*.regex' => 'Gunakan angka biasa tanpa pemisah ribuan; harga maksimal 2 desimal dan jumlah maksimal 4 desimal.'];
    }
}
