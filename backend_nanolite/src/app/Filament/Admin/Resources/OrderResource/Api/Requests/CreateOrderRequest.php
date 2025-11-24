<?php

namespace App\Filament\Admin\Resources\OrderResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Order;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kalau tidak ada perubahan status, boleh saja (user biasa bikin order)
        $isStatusProvided = $this->filled('status_pengajuan')
            || $this->filled('status_product')
            || $this->filled('status_order');

        if (! $isStatusProvided) {
            return true;
        }

        // Kalau ada status yang diisi, delegasi ke policy updateStatus
        return (bool) $this->user()?->can('updateStatus', new Order);
    }

    public function rules(): array
    {
        return [
            /* ================= RELASI ================= */
            'company_id'             => ['required', 'integer', 'exists:companies,id'],
            'department_id'          => ['required', 'integer', 'exists:departments,id'],
            'employee_id'            => ['required', 'integer', 'exists:employees,id'],
            'customer_id'            => ['required', 'integer', 'exists:customers,id'],
            'customer_categories_id' => ['nullable', 'integer', 'exists:customer_categories,id'],
            'customer_program_id'    => ['nullable', 'integer', 'exists:customer_programs,id'],

            /* ================= KONTAK / ALAMAT ========= */
            'address'                => ['nullable'], // textarea (string) atau array dari mobile
            'phone'                  => ['required', 'string'],

            /* ================= PRODUK ================= */
            'products'                        => ['required', 'array', 'min:1'],
            'products.*.produk_id'            => ['required', 'integer', 'exists:products,id'],
            'products.*.warna_id'             => ['required', 'string'],
            'products.*.quantity'             => ['required', 'integer', 'min:1'],
            'products.*.price'                => ['required', 'numeric', 'min:0'],

            /* ================= DISKON ================= */
            'diskons_enabled'        => ['boolean'],

            'diskon_1'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penjelasan_diskon_1'    => ['nullable', 'string'],

            'diskon_2'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penjelasan_diskon_2'    => ['nullable', 'string'],

            'diskon_3'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penjelasan_diskon_3'    => ['nullable', 'string'],

            'diskon_4'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penjelasan_diskon_4'    => ['nullable', 'string'],

            /* ========== PROGRAM & REWARD POINT ========= */
            'program_enabled'        => ['boolean'],
            'jumlah_program'         => ['nullable', 'integer'],
            'reward_enabled'         => ['boolean'],
            'reward_point'           => ['nullable', 'integer'],

            /* ================= TOTAL HARGA ============= */
            'total_harga'            => ['required', 'numeric', 'min:0'],
            'total_harga_after_tax'  => ['nullable', 'numeric', 'min:0'],

            /* ================= STATUS & PEMBAYARAN ===== */
            // Resource: cash & tempo saja
            'payment_method'         => ['required', 'string', Rule::in(['cash','tempo'])],
            'payment_due_until'      => ['nullable', 'date'],

            // Resource: belum bayar / sudah bayar / belum lunas / sudah lunas
            'status_pembayaran'      => [
                'required',
                'string',
                Rule::in(['sudah bayar','belum bayar','belum lunas','sudah lunas']),
            ],

            // ===== status_pengajuan (form) =====
            'status_pengajuan'       => [
                'nullable',
                'string',
                Rule::in(['pending','approved','rejected']),
            ],
            'rejection_comment'      => ['nullable', 'string', 'min:5'],

            // ===== status_product (form) =====
            'status_product'         => [
                'nullable',
                'string',
                Rule::in(['pending','ready_stock','sold_out','rejected']),
            ],
            // komentar saat sold_out (sama pola dengan garansi)
            'sold_out_comment'       => ['nullable', 'string', 'min:5'],
            'sold_out_by'            => ['nullable', 'integer', 'exists:employees,id'],

            // ===== status_order (form) =====
            'status_order'           => [
                'nullable',
                'string',
                Rule::in(['pending','confirmed','processing','on_hold','delivered','completed','cancelled','rejected']),
            ],
            'on_hold_comment'        => ['nullable', 'string', 'min:5'],
            'on_hold_until'          => ['nullable', 'date', 'after:now'],
            'cancelled_comment'      => ['nullable', 'string', 'min:5'],
            'cancelled_by'           => ['nullable', 'integer', 'exists:employees,id'],

            /* ================= DELIVERY / DELIVERY META ================= */
            'delivery_images'        => ['nullable'], // upload di-handle di controller
            'delivered_by'           => ['nullable', 'integer', 'exists:employees,id'],
            'delivered_at'           => ['nullable', 'date'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $sp  = $this->input('status_pengajuan');
            $so  = $this->input('status_order');
            $spx = $this->input('status_product');

            // Wajib isi komentar saat pengajuan ditolak
            if ($sp === 'rejected' && blank($this->input('rejection_comment'))) {
                $v->errors()->add(
                    'rejection_comment',
                    'Komentar wajib diisi saat status pengajuan = rejected.'
                );
            }

            // Wajib isi komentar saat order di-hold
            if ($so === 'on_hold' && blank($this->input('on_hold_comment'))) {
                $v->errors()->add(
                    'on_hold_comment',
                    'Komentar wajib diisi saat status order = on_hold.'
                );
            }

            // Wajib isi komentar saat order dibatalkan
            if ($so === 'cancelled' && blank($this->input('cancelled_comment'))) {
                $v->errors()->add(
                    'cancelled_comment',
                    'Komentar wajib diisi saat status order = cancelled.'
                );
            }

            // Wajib isi komentar saat produk sold out
            if ($spx === 'sold_out' && blank($this->input('sold_out_comment'))) {
                $v->errors()->add(
                    'sold_out_comment',
                    'Komentar wajib diisi saat status produk = sold_out.'
                );
            }
        });
    }
}
