<?php

namespace App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'name'       => 'required|string|max:255',
            'deskripsi'  => 'nullable|string',
        ];
    }
}
