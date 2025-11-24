<?php

namespace App\Filament\Admin\Resources\CustomerResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id'             => 'sometimes|exists:companies,id',
            'customer_categories_id' => 'sometimes|exists:customer_categories,id',
            'department_id'          => 'sometimes|exists:departments,id',
            'employee_id'            => 'sometimes|exists:employees,id',
            'customer_program_id'    => 'sometimes|exists:customer_programs,id',

            'name'   => 'sometimes|string|max:255',
            'phone'  => 'sometimes|string|max:20',
            'email'  => 'nullable|email',

            // ✅ alamat array (pakai code + name biar full sama dengan Create)
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
            'image.*' => 'file|image|max:2048',

            // ================= STATUS & ALASAN (SAMA DENGAN RESOURCE) =================
            'status_pengajuan' => 'sometimes|string|in:pending,approved,rejected',
            // required_if tetap boleh dipakai di update, tapi pakai sometimes juga
            'alasan_penolakan' => 'nullable|string|required_if:status_pengajuan,rejected',

            'status'  => 'nullable|string|in:active,non-active,pending',
        ];
    }
}
