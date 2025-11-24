<?php

namespace App\Filament\Admin\Resources\CategoryResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'brand_id'   => 'required|exists:brands,id',
            'company_id' => 'required|exists:companies,id',
            'name'       => 'required|string|max:255',
            'deskripsi'  => 'nullable|string',
            'image'      => 'nullable|string',
        ];
    }
}
