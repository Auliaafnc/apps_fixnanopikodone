<?php

namespace App\Filament\Admin\Resources\CustomerProgramResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'         => 'required|exists:companies,id',
            'name'               => 'required|string|max:255',
            'deskripsi'          => 'nullable|string',
            'category_ids'       => 'nullable|array',
            'category_ids.*'     => 'integer|exists:customer_categories,id',
        ];
    }
}
