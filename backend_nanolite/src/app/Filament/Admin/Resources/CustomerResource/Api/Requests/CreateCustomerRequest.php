<?php

namespace App\Filament\Admin\Resources\CustomerResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // izinkan semua request (bisa diubah pakai policy)
    }

    public function rules(): array
    {
        return [
            'company_id'             => 'required|exists:companies,id',
            'customer_categories_id' => 'required|exists:customer_categories,id',
            'department_id'          => 'required|exists:departments,id',
            'employee_id'            => 'nullable|exists:employees,id',
            'customer_program_id'    => 'nullable|exists:customer_programs,id',

            'name'   => 'required|string|max:255',
            'phone'  => 'required|string|max:20',
            'email'  => 'nullable|email',

            'address'                        => 'nullable|array',

            'address.*.provinsi_code'        => 'sometimes|string',
            'address.*.provinsi_name'        => 'sometimes|string',

            'address.*.kota_kab_code'        => 'sometimes|string',
            'address.*.kota_kab_name'        => 'sometimes|string',

            'address.*.kecamatan_code'       => 'sometimes|string',
            'address.*.kecamatan_name'       => 'sometimes|string',

            'address.*.kelurahan_code'       => 'sometimes|string',
            'address.*.kelurahan_name'       => 'sometimes|string',

            'address.*.kode_pos'             => 'sometimes|string',
            'address.*.detail_alamat'        => 'sometimes|string',


            'gmaps_link'     => 'nullable|string',
            'jumlah_program' => 'nullable|integer',
            'reward_point'   => 'nullable|integer',

            // ✅ multi foto
            'image'   => 'nullable',
            'image.*' => 'file|image|max:2048',  // lebih besar dari sebelumnya biar aman

            // ================= STATUS & ALASAN (SAMA DENGAN RESOURCE) =================
            'status_pengajuan' => 'nullable|string|in:pending,approved,rejected',
            // Wajib diisi kalau status_pengajuan = rejected
            'alasan_penolakan' => 'nullable|string|required_if:status_pengajuan,rejected',

            // enum status di DB: ['active', 'non-active', 'pending']
            'status'  => 'nullable|string|in:active,non-active,pending',
        ];
    }
}
