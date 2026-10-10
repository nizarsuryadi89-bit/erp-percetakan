<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePrintOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spk.manage') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['request_key' => ['required', 'uuid'], 'branch_id' => ['required', 'integer'], 'customer_id' => ['required', 'integer'], 'method' => ['required', Rule::in(['walk_in', 'online', 'marketplace'])], 'marketplace_name' => ['nullable', 'required_if:method,marketplace', 'string', 'max:100'], 'marketplace_reference' => ['nullable', 'string', 'max:150'], 'marketplace_tracking' => ['nullable', 'string', 'max:150'], 'deadline' => ['nullable', 'date_format:Y-m-d\TH:i'], 'notes' => ['nullable', 'string', 'max:2000']];
    }
}
