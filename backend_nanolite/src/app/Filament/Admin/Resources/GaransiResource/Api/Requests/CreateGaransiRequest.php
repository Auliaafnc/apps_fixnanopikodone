<?php

namespace App\Filament\Admin\Resources\GaransiResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Garansi;

class CreateGaransiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kalau saat CREATE ada status yang diisi, cek policy updateStatus
        $isStatusProvided = $this->filled('status_pengajuan')
            || $this->filled('status_product')
            || $this->filled('status_garansi');

        if (! $isStatusProvided) {
            return true;
        }

        // Delegasi ke policy supaya role tidak di-hardcode di Request
        return (bool) $this->user()?->can('updateStatus', new Garansi);
    }

    public function rules(): array
    {
        return [
            // --- field utama ---
            'no_garansi'             => ['sometimes','nullable','string','unique:garansis,no_garansi'],
            'company_id'             => ['nullable','exists:companies,id'],
            'customer_categories_id' => ['nullable','exists:customer_categories,id'],
            'employee_id'            => ['required','exists:employees,id'],
            'department_id'          => ['required','exists:departments,id'],
            'customer_id'            => ['required','exists:customers,id'],

            'address'                => ['nullable'],
            'phone'                  => ['required','string','max:20'],

            'products'               => ['required','array'],

            'purchase_date'          => ['required','date'],
            'claim_date'             => ['required','date','after_or_equal:purchase_date'],

            'reason'                 => ['required','string'],
            'note'                   => ['nullable','string'],

            // JSON gambar (sesuai migration)
            'image'                  => ['nullable'],
            'image.*'                => ['string'],

            // --- status sesuai migration ---
            'status_pengajuan'       => ['nullable', Rule::in(['pending','approved','rejected'])],
            'status_product'         => ['nullable', Rule::in(['pending','ready_stock','sold_out','rejected'])],
            'status_garansi'         => ['nullable', Rule::in(['pending','confirmed','processing','on_hold','delivered','completed','cancelled','rejected'])],

            // --- komentar & audit ---
            'rejection_comment'      => ['nullable','string','min:5'],
            'rejected_by'            => ['nullable','exists:employees,id'],

            'sold_out_comment'       => ['nullable','string','min:5'],
            'sold_out_by'            => ['nullable','exists:employees,id'],

            'on_hold_comment'        => ['nullable','string','min:5'],
            'on_hold_until'          => ['nullable','date','after:now'],
            'on_hold_by'             => ['nullable','exists:employees,id'],

            'cancelled_comment'      => ['nullable','string','min:5'],
            'cancelled_by'           => ['nullable','exists:employees,id'],

            // --- bukti delivery (sesuai migration)
            'delivery_images'        => ['nullable','array'],
            'delivery_images.*'      => ['string'],
            'delivered_at'           => ['nullable','date'],
            'delivered_by'           => ['nullable','exists:employees,id'],

            // file opsional (path string)
            'garansi_file'           => ['nullable','string'],
            'garansi_excel'          => ['nullable','string'],
        ];
    }

    protected function prepareForValidation()
    {
        // kalau image kirim tunggal (string base64), bungkus jadi array
        $img = $this->input('image');
        if (is_string($img)) {
            $this->merge(['image' => [$img]]);
        }
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $sp  = $this->input('status_pengajuan');
            $sg  = $this->input('status_garansi');
            $spx = $this->input('status_product');

            if ($sp === 'rejected' && blank($this->input('rejection_comment'))) {
                $v->errors()->add('rejection_comment', 'Komentar wajib diisi saat status pengajuan = rejected.');
            }

            if ($sg === 'on_hold' && blank($this->input('on_hold_comment'))) {
                $v->errors()->add('on_hold_comment', 'Komentar wajib diisi saat status garansi = on_hold.');
            }

            if ($sg === 'cancelled' && blank($this->input('cancelled_comment'))) {
                $v->errors()->add('cancelled_comment', 'Komentar wajib diisi saat status garansi = cancelled.');
            }

            if ($spx === 'sold_out' && blank($this->input('sold_out_comment'))) {
                $v->errors()->add('sold_out_comment', 'Komentar wajib diisi saat status produk = sold out.');
            }
        });
    }
}
