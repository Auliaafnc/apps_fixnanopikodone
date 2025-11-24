<?php

namespace App\Filament\Admin\Resources\ProductReturnResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ProductReturn;

class CreateProductReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kalau saat CREATE ada status yang diisi, cek policy updateStatus (atau updateAll via policy, jika ada)
        $isStatusProvided = $this->filled('status_pengajuan')
            || $this->filled('status_product')
            || $this->filled('status_return');

        if (! $isStatusProvided) {
            return true;
        }

        // Delegasi ke policy supaya role/izin tidak di-hardcode di Request
        return (bool) $this->user()?->can('updateStatus', new ProductReturn);
    }

    public function rules(): array
    {
        return [
            // --- field relasi utama ---
            'company_id'             => ['required','exists:companies,id'],
            'customer_categories_id' => ['required','exists:customer_categories,id'],
            'customer_id'            => ['required','exists:customers,id'],
            'employee_id'            => ['required','exists:employees,id'],
            'department_id'          => ['required','exists:departments,id'],

            // --- info kontak & alasan ---
            'phone'   => ['required','string','max:20'],

            // address boleh STRING (alamat biasa) ATAU ARRAY (alamat dari Flutter)
            'address' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (! is_string($value) && ! is_array($value)) {
                        $fail("The {$attribute} field must be a string or an array.");
                    }
                },
            ],

            'reason'  => ['required','string'],
            'note'    => ['nullable','string'],

            // --- detail produk (repeater) ---
            'products'                   => ['required','array','min:1'],
            'products.*.brand_id'        => ['nullable','integer','exists:brands,id'],
            'products.*.kategori_id'     => ['nullable','integer','exists:categories,id'],
            'products.*.produk_id'       => ['required','integer','exists:products,id'],
            'products.*.warna_id'        => ['required','string'],        // label warna (bukan index)
            'products.*.quantity'        => ['required','integer','min:1'],

            // --- FOTO BARANG (MULTIPLE FILE dari Flutter) ---
            // Flutter kirim image[] (multipart) -> di PHP jadi array "image"
            'image'   => ['nullable', 'array'],
            'image.*' => ['file', 'image', 'max:4096'],
            // kalau mau spesifik extensi:
            // 'image.*' => ['file', 'mimes:jpg,jpeg,png', 'max:4096'],

            // --- status (selaras dengan Resource) ---
            'status_pengajuan'           => ['nullable', Rule::in(['pending','approved','rejected'])],
            'status_product'             => ['nullable', Rule::in(['pending','ready_stock','sold_out','rejected'])],
            'status_return'              => ['nullable', Rule::in(['pending','confirmed','processing','on_hold','delivered','completed','cancelled','rejected'])],

            // --- komentar & audit trail ---
            'rejection_comment'          => ['nullable','string','min:5'],
            'rejected_by'                => ['nullable','exists:employees,id'],

            'sold_out_comment'           => ['nullable','string','min:5'],
            'sold_out_by'                => ['nullable','exists:employees,id'],

            'on_hold_comment'            => ['nullable','string','min:5'],
            'on_hold_until'              => ['nullable','date','after:now'],
            'on_hold_by'                 => ['nullable','exists:employees,id'],

            'cancelled_comment'          => ['nullable','string','min:5'],
            'cancelled_by'               => ['nullable','exists:employees,id'],

            // --- bukti delivery (untuk create API kamu pakai saat update, jadi tetap string/array path) ---
            'delivery_images'        => ['nullable','array'],
            'delivery_images.*'      => ['string'],
            'delivered_at'           => ['nullable','date'],
            'delivered_by'           => ['nullable','exists:employees,id'],

            // --- file opsional (mengikuti field di Resource) ---
            'return_file'                => ['nullable','string'],
            'return_excel'               => ['nullable','string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $sp  = $this->input('status_pengajuan');
            $sg  = $this->input('status_return');
            $spx = $this->input('status_product');

            // status_pengajuan = rejected -> wajib komentar
            if ($sp === 'rejected' && blank($this->input('rejection_comment'))) {
                $v->errors()->add('rejection_comment', 'Komentar wajib diisi saat status pengajuan = rejected.');
            }

            // status_return = on_hold -> wajib komentar & tanggal
            if ($sg === 'on_hold') {
                if (blank($this->input('on_hold_comment'))) {
                    $v->errors()->add('on_hold_comment', 'Komentar wajib diisi saat status return = on_hold.');
                }
                if (blank($this->input('on_hold_until'))) {
                    $v->errors()->add('on_hold_until', 'Tanggal batas on hold wajib diisi saat status return = on_hold.');
                }
            }

            // status_return = cancelled -> wajib komentar
            if ($sg === 'cancelled' && blank($this->input('cancelled_comment'))) {
                $v->errors()->add('cancelled_comment', 'Komentar wajib diisi saat status return = cancelled.');
            }

            // status_product = sold_out -> wajib komentar
            if ($spx === 'sold_out' && blank($this->input('sold_out_comment'))) {
                $v->errors()->add('sold_out_comment', 'Komentar wajib diisi saat status produk = sold out.');
            }
        });
    }
}
