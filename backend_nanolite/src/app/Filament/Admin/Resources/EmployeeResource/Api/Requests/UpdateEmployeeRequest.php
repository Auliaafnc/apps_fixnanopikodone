<?php

namespace App\Filament\Admin\Resources\EmployeeResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'    => 'sometimes|exists:companies,id',
            'department_id' => 'sometimes|exists:departments,id',
            'name'          => 'sometimes|string|max:255',
            'email'         => 'sometimes|email|max:255',
            'phone'         => 'sometimes|string|max:20',
            'address'       => 'sometimes|array',
            'photo'         => 'sometimes|string',
            'status'        => 'sometimes|in:active,inactive',
        ];
    }
}
