<?php

namespace App\Filament\Admin\Resources\ProductReturnResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\ProductReturn;

class UpdateProductReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var \App\Models\ProductReturn|null $productReturn */
        $productReturn = $this->route('product_return') ?? $this->route('return') ?? null;

        // Hanya cek policy ketika ada field status di-request
        $isStatusRequest = $this->has('status_pengajuan')
            || $this->has('status_product')
            || $this->has('status_return');

        if (! $isStatusRequest) {
            return true;
        }

        // Delegasi ke policy supaya role/izin tidak di-hardcode di Request
        return (bool) $this->user()?->can('updateStatus', $productReturn ?? new ProductReturn);
    }

    public function rules(): array
    {
        return [
            // --- field relasi & info utama ---
            'company_id'             => ['sometimes','exists:companies,id'],
            'customer_categories_id' => ['sometimes','exists:customer_categories,id'],
            'customer_id'            => ['sometimes','exists:customers,id'],
            'employee_id'            => ['sometimes','exists:employees,id'],
            'department_id'          => ['sometimes','exists:departments,id'],

            'phone'                  => ['sometimes','string','max:20'],

            // address boleh string / array
            'address' => [
                'sometimes',
                function ($attribute, $value, $fail) {
                    if (! is_string($value) && ! is_array($value)) {
                        $fail("The {$attribute} field must be a string or an array.");
                    }
                },
            ],

            'reason'                 => ['sometimes','nullable','string'],
            'note'                   => ['sometimes','nullable','string'],
            'amount'                 => ['sometimes','numeric','min:0'],

            // --- detail produk (repeater) ---
            'products'                   => ['sometimes','array','min:1'],
            'products.*.brand_id'        => ['nullable','integer','exists:brands,id'],
            'products.*.kategori_id'     => ['nullable','integer','exists:categories,id'],
            'products.*.produk_id'       => ['required_with:products','integer','exists:products,id'],
            'products.*.warna_id'        => ['required_with:products','string'],
            'products.*.quantity'        => ['required_with:products','integer','min:1'],

            // --- foto barang ---
            'image' => [
                'sometimes','nullable',
                function ($attribute, $value, $fail) {
                    if (! is_null($value) && ! is_string($value) && ! is_array($value)) {
                        $fail("The {$attribute} field must be a string or an array.");
                    }
                },
            ],
            // 'image.*' => ['string'],

            // --- status (samakan dengan Resource) ---
            'status_pengajuan'           => ['sometimes', Rule::in(['pending','approved','rejected'])],
            'status_product'             => ['sometimes', Rule::in(['pending','ready_stock','sold_out','rejected'])],
            'status_return'              => ['sometimes', Rule::in(['pending','confirmed','processing','on_hold','delivered','completed','cancelled','rejected'])],

            // --- komentar & audit trail ---
            'rejection_comment'          => ['sometimes','nullable','string','min:5'],
            'rejected_by'                => ['sometimes','nullable','exists:employees,id'],

            'sold_out_comment'           => ['sometimes','nullable','string','min:5'],
            'sold_out_by'                => ['sometimes','nullable','exists:employees,id'],

            'on_hold_comment'            => ['sometimes','nullable','string','min:5'],
            'on_hold_until'              => ['sometimes','nullable','date','after:now'],
            'on_hold_by'                 => ['sometimes','nullable','exists:employees,id'],

            'cancelled_comment'          => ['sometimes','nullable','string','min:5'],
            'cancelled_by'               => ['sometimes','nullable','exists:employees,id'],

            // --- bukti delivery ---
            'delivery_images' => [
                'sometimes','nullable',
                function ($attribute, $value, $fail) {
                    if (! is_null($value) && ! is_string($value) && ! is_array($value)) {
                        $fail("The {$attribute} field must be a string or an array.");
                    }
                },
            ],
            // 'delivery_images.*' => ['string'],

            'delivered_at'               => ['sometimes','nullable','date'],
            'delivered_by'               => ['sometimes','nullable','exists:employees,id'],

            // --- file opsional (path string) ---
            'return_file'                => ['sometimes','nullable','string'],
            'return_excel'               => ['sometimes','nullable','string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            /** @var \App\Models\ProductReturn|null $record */
            $record = $this->route('product_return') ?? $this->route('return') ?? null;

            $sp  = $this->input('status_pengajuan');
            $sg  = $this->input('status_return');
            $spp = $this->input('status_product');

            // ===== Wajib komentar sesuai status =====
            if ($this->has('status_pengajuan') && $sp === 'rejected' && blank($this->input('rejection_comment'))) {
                $v->errors()->add('rejection_comment', 'Komentar wajib diisi saat status pengajuan = rejected.');
            }

            if ($this->has('status_product') && $spp === 'sold_out' && blank($this->input('sold_out_comment'))) {
                $v->errors()->add('sold_out_comment', 'Komentar wajib diisi saat status produk = sold out.');
            }

            if ($this->has('status_return') && $sg === 'on_hold') {
                if (blank($this->input('on_hold_comment'))) {
                    $v->errors()->add('on_hold_comment', 'Komentar wajib diisi saat status return = on_hold.');
                }
                if (blank($this->input('on_hold_until'))) {
                    $v->errors()->add('on_hold_until', 'Tanggal batas on hold wajib diisi saat status return = on_hold.');
                }
            }

            if ($this->has('status_return') && $sg === 'cancelled' && blank($this->input('cancelled_comment'))) {
                $v->errors()->add('cancelled_comment', 'Komentar wajib diisi saat status return = cancelled.');
            }

            // ===== Guard tambahan: Completed butuh konfirmasi & bukti delivery yang sudah ada di record =====
            if ($this->has('status_return') && $sg === 'completed' && $record) {
                $hasConfirm = (bool) $record->delivered_at;
                $hasProof   = is_array($record->delivery_images) && count($record->delivery_images) > 0;
                if (! $hasConfirm || ! $hasProof) {
                    $v->errors()->add('status_return', 'Tidak bisa set completed tanpa konfirmasi & bukti delivery.');
                }
            }
        });
    }
}