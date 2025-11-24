<?php

namespace App\Filament\Admin\Resources\BannerResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'required|exists:companies,id',
            'image_1'    => 'nullable|string',
            'image_2'    => 'nullable|string',
            'image_3'    => 'nullable|string',
            'image_4'    => 'nullable|string',
        ];
    }
}
