<?php

namespace App\Filament\Admin\Resources\PerbaikandataResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePerbaikandataRequest extends FormRequest
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
            'company_id' => ['nullable', 'integer'],
			'department_id' => 'required',
			'employee_id' => 'required',
			'customer_categories_id' => 'required',
			'customer_id' => 'required',
			'pilihan_data' => 'required',
			'data_baru' => 'nullable|string',
			'address' => 'nullable',
			'images' => ['nullable','array'],
            'images.*' => ['file','image','max:5120'], // 5MB
			'status_pengajuan' => ['nullable', 'string'],
            'alasan_penolakan' => ['nullable', 'string'],
		];
    }
}