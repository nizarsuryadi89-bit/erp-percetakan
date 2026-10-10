<?php

namespace App\Http\Requests;

use App\Models\Branch;
use App\Support\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('cabang.manage') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $unique = Rule::unique('branches', 'code')->where('tenant_id', app(TenantContext::class)->id());
        $branch = $this->route('branch');
        if ($branch instanceof Branch) {
            $unique->ignore($branch->id);
        }

        return ['name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:30', 'alpha_dash', $unique], 'address' => ['nullable', 'string', 'max:1000'], 'is_active' => ['sometimes', 'boolean']];
    }
}
