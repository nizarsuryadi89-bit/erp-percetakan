<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transaksi.view') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'qty' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\d+(\.\d{1,4})?$/'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:10000', 'regex:/^\d+(\.\d{1,4})?$/'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:10000', 'regex:/^\d+(\.\d{1,4})?$/'],
            'variant' => ['nullable', 'string', 'max:100'],
            'machine' => ['nullable', 'string', 'max:100'],
            'print_side' => ['nullable', 'string', 'max:100'],
            'finishing' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['qty.gt' => 'Jumlah pesanan harus lebih besar dari nol.', '*.regex' => 'Gunakan angka biasa dengan maksimal 4 angka desimal, tanpa pemisah ribuan.'];
    }
}
