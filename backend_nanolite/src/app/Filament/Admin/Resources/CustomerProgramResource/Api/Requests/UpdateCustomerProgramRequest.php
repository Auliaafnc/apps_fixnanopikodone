<?php

namespace App\Filament\Admin\Resources\CustomerProgramResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'         => 'sometimes|exists:companies,id',
            'name'               => 'sometimes|string|max:255',
            'deskripsi'          => 'nullable|string',
            'category_ids'       => 'nullable|array',
            'category_ids.*'     => 'integer|exists:customer_categories,id',
        ];
    }
}
