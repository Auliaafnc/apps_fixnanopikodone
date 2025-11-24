<?php

namespace App\Filament\Admin\Resources\CategoryResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id'   => 'sometimes|exists:brands,id',
            'company_id' => 'sometimes|exists:companies,id',
            'name'       => 'sometimes|string|max:255',
            'deskripsi'  => 'nullable|string',
            'image'      => 'nullable|string',
        ];
    }
}
