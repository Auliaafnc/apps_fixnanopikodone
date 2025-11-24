<?php

namespace App\Filament\Admin\Resources\ProductResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'  => 'nullable|integer|exists:companies,id',
            'brand_id'    => 'nullable|integer|exists:brands,id',
            'category_id' => 'nullable|integer|exists:categories,id',
            'name'        => 'required|string|max:255',
            'price'       => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'colors'      => 'nullable|array',
            'colors.*'    => 'string',
            'image'       => 'nullable|image|max:2048',
        ];
    }
}
