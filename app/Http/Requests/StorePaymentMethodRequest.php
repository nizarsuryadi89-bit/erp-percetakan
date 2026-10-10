<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment-methods.manage') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['kind' => ['required', Rule::in(['cash', 'transfer', 'debit', 'ewallet'])], 'name' => ['required', 'string', 'max:100'], 'details' => ['nullable', 'string', 'max:150']];
    }
}
