<?php

namespace App\Http\Requests;

class StorePrintOrderItemRequest extends QuoteProductRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spk.manage') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return [...parent::rules(), 'request_key' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:1'], 'product_id' => ['required', 'integer'], 'notes' => ['nullable', 'string', 'max:2000'], 'process_location' => ['nullable', 'string', 'max:150'], 'deadline' => ['nullable', 'date_format:Y-m-d\TH:i']];
    }
}
