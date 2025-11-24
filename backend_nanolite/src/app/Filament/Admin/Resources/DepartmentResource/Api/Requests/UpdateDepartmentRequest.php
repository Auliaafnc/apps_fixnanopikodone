<?php

namespace App\Filament\Admin\Resources\DepartmentResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
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
            'status'     => 'sometimes|string|in:active,inactive',
        ];
    }
}
