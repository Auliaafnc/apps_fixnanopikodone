<?php

namespace App\Filament\Admin\Resources\CompanyResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'sometimes|string|max:255',
            'email'   => 'sometimes|email',
            'phone'   => 'sometimes|string|max:20',
            'address' => 'nullable|array',
            'status'  => 'nullable|string|in:active,inactive',
            'image'   => 'nullable|string',
        ];
    }
}
