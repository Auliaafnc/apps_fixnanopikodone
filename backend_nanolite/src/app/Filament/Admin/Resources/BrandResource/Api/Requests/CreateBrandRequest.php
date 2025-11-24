<?php

namespace App\Filament\Admin\Resources\BrandResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBrandRequest extends FormRequest
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
            'image'      => 'nullable|string',
        ];
    }
}
