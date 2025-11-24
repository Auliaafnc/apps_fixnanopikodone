<?php

namespace App\Filament\Admin\Resources\PerbaikandataResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerbaikandataRequest extends FormRequest
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
			'company_id' => 'required',
			'department_id' => 'required',
			'employee_id' => 'required',
			'customer_categories_id' => 'required',
			'customer_id' => 'required',
			'pilihan_data' => 'required',
			'data_baru' => 'required|string',
			'address' => 'required',
			'image' => 'required',
			'status_pengajuan' => 'required'
		];
    }
}