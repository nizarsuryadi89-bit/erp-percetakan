<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('invoice.issue') ?? false;
    }

    /** @return array<string,array<mixed>> */
    public function rules(): array
    {
        return ['request_key' => ['required', 'uuid']];
    }
}
