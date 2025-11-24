<?php

namespace App\Filament\Admin\Resources\BrandResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandRequest extends FormRequest
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
            'image'      => 'nullable|string',
        ];
    }
}
