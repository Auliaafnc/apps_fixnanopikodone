<?php

namespace App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerCategoriesRequest extends FormRequest

{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|exists:companies,id',
            'name'       => 'sometimes|string|max:255',
            'deskripsi'  => 'nullable|string',
        ];
    }
}
