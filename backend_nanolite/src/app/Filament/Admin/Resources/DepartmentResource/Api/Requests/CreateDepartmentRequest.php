<?php

namespace App\Filament\Admin\Resources\DepartmentResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDepartmentRequest extends FormRequest
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
            'status'     => 'required|string|in:active,inactive',
        ];
    }
}
