<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTenantModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('platform.manage') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['module_codes' => ['present', 'array'], 'module_codes.*' => ['required', 'string', 'distinct', Rule::exists('modules', 'code')->where('is_active', true)], 'access_status' => ['sometimes', Rule::in(['pending', 'active', 'suspended'])], 'expires_at' => ['sometimes', 'nullable', 'date_format:Y-m-d']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['module_codes.*.exists' => 'Modul yang dipilih tidak tersedia.', 'module_codes.*.distinct' => 'Pilihan modul tidak boleh berulang.', 'module_codes.present' => 'Kirim daftar modul yang dipilih. Daftar boleh kosong.'];
    }
}
