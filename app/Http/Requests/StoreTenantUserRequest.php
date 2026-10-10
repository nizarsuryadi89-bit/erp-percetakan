<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pengguna.manage') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', Password::min(12), 'confirmed'], 'role' => ['required', Rule::in(array_values(array_diff(config('erp.roles'), ['Owner'])))]];
    }
}
