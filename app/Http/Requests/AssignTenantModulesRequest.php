<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignTenantModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('platform.manage') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['module_codes' => ['present', 'array'], 'module_codes.*' => ['required', 'string', 'distinct', Rule::exists('modules', 'code')->where('is_active', true)], 'access_status' => ['sometimes', Rule::in(['pending', 'active', 'suspended'])], 'expires_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'], 'module_expiries' => ['sometimes', 'array'], 'module_expiries.*' => ['nullable', 'date_format:Y-m-d']];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $codes = $this->input('module_codes');
            $expiries = $this->input('module_expiries', []);
            if (is_array($codes) && is_array($expiries) && array_diff(array_keys($expiries), $codes)) {
                $validator->errors()->add('module_expiries', 'Masa akses hanya dapat diatur untuk modul yang dipilih.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['module_codes.*.exists' => 'Modul yang dipilih tidak tersedia.', 'module_codes.*.distinct' => 'Pilihan modul tidak boleh berulang.', 'module_codes.present' => 'Kirim daftar modul yang dipilih. Daftar boleh kosong.'];
    }
}
