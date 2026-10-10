<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spk.manage') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['request_key' => ['required', 'uuid'], 'branch_id' => ['required', 'integer'], 'name' => ['required', 'string', 'max:150'], 'phone' => ['nullable', 'string', 'max:30', 'regex:/^[+0-9() .-]+$/'], 'address' => ['nullable', 'string', 'max:1000']];
    }
}
