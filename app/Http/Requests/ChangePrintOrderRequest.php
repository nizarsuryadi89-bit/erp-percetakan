<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePrintOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spk.manage') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['request_key' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:1'], 'status' => ['exclude_unless:_transition,1', 'required', Rule::in(['confirmed', 'cancelled'])], 'reason' => ['exclude_unless:_transition,1', 'nullable', 'required_if:status,cancelled', 'string', 'max:1000']];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['_transition' => $this->routeIs('print-orders.status') ? '1' : '0']);
    }
}
