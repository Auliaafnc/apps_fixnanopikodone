<?php

namespace App\Filament\Admin\Resources\PointMinimumResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePointMinimumRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
			'type' => 'required',
			'program_id' => 'required',
			'min_amount' => 'required',
			'is_active' => 'required'
		];
    }
}
