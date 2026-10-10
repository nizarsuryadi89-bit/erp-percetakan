<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment.record') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['request_key' => ['required', 'uuid'], 'payments' => ['required', 'array', 'min:1', 'max:10'], 'payments.*' => ['required', 'array:payment_method_id,amount,reference,paid_at,notes'], 'payments.*.payment_method_id' => ['required', 'integer'], 'payments.*.amount' => ['required', 'string', 'regex:/^\\d{1,13}(\\.\\d{1,2})?$/D', 'gt:0'], 'payments.*.reference' => ['nullable', 'string', 'max:150'], 'payments.*.paid_at' => ['required', 'date_format:Y-m-d\\TH:i'], 'payments.*.notes' => ['nullable', 'string', 'max:1000']];
    }
}
