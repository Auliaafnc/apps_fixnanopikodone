<?php

namespace App\Filament\Admin\Resources\CompanyResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'email'   => 'nullable|email',
            'phone'   => 'nullable|string|max:20',
            'address' => 'nullable|array',
            'status'  => 'nullable|string|in:active,inactive',
            'image'   => 'nullable|string',
        ];
    }
}
