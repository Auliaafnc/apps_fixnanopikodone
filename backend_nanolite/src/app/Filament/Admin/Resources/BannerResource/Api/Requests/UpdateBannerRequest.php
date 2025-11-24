<?php

namespace App\Filament\Admin\Resources\BannerResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => 'sometimes|exists:companies,id',
            'image_1'    => 'nullable|string',
            'image_2'    => 'nullable|string',
            'image_3'    => 'nullable|string',
            'image_4'    => 'nullable|string',
        ];
    }
}
