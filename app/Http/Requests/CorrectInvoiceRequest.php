<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CorrectInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payment.reverse') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['request_key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:1000'], 'record_was_incorrect' => ['required', 'accepted']];
    }
}
