<?php

namespace App\Filament\Admin\Resources\OrderResource\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Order;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('order');

        // Hanya cek policy ketika ada field status di-request
        $isStatusRequest = $this->has('status_pengajuan')
            || $this->has('status_product')
            || $this->has('status_order');

        if (! $isStatusRequest) {
            return true;
        }

        return (bool) $this->user()?->can('updateStatus', $order);
    }

    public function rules(): array
    {
        return [
            /* ================= RELASI ================= */
            'company_id'             => ['sometimes', 'integer', 'exists:companies,id'],
            'department_id'          => ['sometimes', 'integer', 'exists:departments,id'],
            'employee_id'            => ['sometimes', 'integer', 'exists:employees,id'],
            'customer_id'            => ['sometimes', 'integer', 'exists:customers,id'],
            'customer_categories_id' => ['sometimes', 'integer', 'exists:customer_categories,id'],
            'customer_program_id'    => ['sometimes', 'integer', 'exists:customer_programs,id'],

            /* ================= KONTAK / ALAMAT ========= */
            // boleh string / array (biar fleksibel dengan mobile)
            'address'                => ['sometimes'],
            'phone'                  => ['sometimes', 'string'],

            /* ================= PRODUK ================= */
            'products'                        => ['sometimes', 'array', 'min:1'],
            'products.*.brand_produk_id'      => ['nullable', 'integer', 'exists:brands,id'],
            'products.*.kategori_produk_id'   => ['nullable', 'integer', 'exists:categories,id'],
            'products.*.produk_id'            => ['required_with:products', 'integer', 'exists:products,id'],
            'products.*.warna_id'             => ['required_with:products', 'string'],
            'products.*.quantity'             => ['required_with:products', 'integer', 'min:1'],
            'products.*.price'                => ['required_with:products', 'numeric', 'min:0'],

            /* ================= DISKON ================= */
            'diskons_enabled'        => ['sometimes', 'boolean'],

            'diskon_1'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penjelasan_diskon_1'    => ['nullable', 'string'],

            'diskon_2'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penjelasan_diskon_2'    => ['nullable', 'string'],

            'diskon_3'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penjelasan_diskon_3'    => ['nullable', 'string'],

            'diskon_4'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'penjelasan_diskon_4'    => ['nullable', 'string'],

            /* ========== PROGRAM & REWARD POINT ========= */
            'program_enabled'        => ['sometimes', 'boolean'],
            'jumlah_program'         => ['nullable', 'integer'],
            'reward_enabled'         => ['sometimes', 'boolean'],
            'reward_point'           => ['nullable', 'integer'],

            /* ================= TOTAL HARGA ============= */
            'total_harga'            => ['sometimes', 'numeric', 'min:0'],
            'total_harga_after_tax'  => ['nullable', 'numeric', 'min:0'],

            /* ================= STATUS & PEMBAYARAN ===== */
            'payment_method'         => ['sometimes', 'string', Rule::in(['cash', 'tempo'])],
            'payment_due_until'      => ['sometimes', 'nullable', 'date'],

            'status_pembayaran'      => [
                'sometimes',
                'string',
                Rule::in(['sudah bayar', 'belum bayar', 'belum lunas', 'sudah lunas']),
            ],

            // ===== status_pengajuan =====
            'status_pengajuan'       => [
                'sometimes',
                'string',
                Rule::in(['pending', 'approved', 'rejected']),
            ],
            'rejection_comment'      => ['sometimes', 'nullable', 'string', 'min:5'],

            // ===== status_product =====
            'status_product'         => [
                'sometimes',
                'string',
                Rule::in(['pending', 'ready_stock', 'sold_out', 'rejected']),
            ],
            'sold_out_comment'       => ['sometimes', 'nullable', 'string', 'min:5'],
            'sold_out_by'            => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],

            // ===== status_order =====
            'status_order'           => [
                'sometimes',
                'string',
                Rule::in([
                    'pending',
                    'confirmed',
                    'processing',
                    'on_hold',
                    'delivered',
                    'completed',
                    'cancelled',
                    'rejected',
                ]),
            ],
            'on_hold_comment'        => ['sometimes', 'nullable', 'string', 'min:5'],
            'on_hold_until'          => ['sometimes', 'nullable', 'date', 'after:now'],
            'cancelled_comment'      => ['sometimes', 'nullable', 'string', 'min:5'],
            'cancelled_by'           => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],

            /* ================= DELIVERY / DELIVERY META ================= */
            'delivery_images'        => ['sometimes', 'nullable', 'array'],
            'delivery_images.*'      => ['string'],
            'delivered_by'           => ['sometimes', 'nullable', 'integer', 'exists:employees,id'],
            'delivered_at'           => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $user  = $this->user();
            $order = $this->route('order');

            $sp    = $this->input('status_pengajuan');
            $so    = $this->input('status_order');
            $sprod = $this->input('status_product');

            // ===== Wajib komentar sesuai status =====
            if ($this->has('status_pengajuan') && $sp === 'rejected' && blank($this->input('rejection_comment'))) {
                $v->errors()->add(
                    'rejection_comment',
                    'Komentar wajib diisi saat status pengajuan = rejected.'
                );
            }

            if ($this->has('status_order') && $so === 'on_hold' && blank($this->input('on_hold_comment'))) {
                $v->errors()->add(
                    'on_hold_comment',
                    'Komentar wajib diisi saat status order = on_hold.'
                );
            }

            if ($this->has('status_order') && $so === 'cancelled' && blank($this->input('cancelled_comment'))) {
                $v->errors()->add(
                    'cancelled_comment',
                    'Komentar wajib diisi saat status order = cancelled.'
                );
            }

            if ($this->has('status_product') && $sprod === 'sold_out' && blank($this->input('sold_out_comment'))) {
                $v->errors()->add(
                    'sold_out_comment',
                    'Komentar wajib diisi saat status produk = sold out.'
                );
            }

            // ===== Guard: delegasi ke Policy (tanpa hardcode role) =====
            if (
                ($this->has('status_pengajuan') && $sp === 'rejected') ||
                ($this->has('status_order') && in_array($so, ['on_hold', 'cancelled', 'completed'], true)) ||
                ($this->has('status_product') && in_array($sprod, ['sold_out', 'rejected'], true))
            ) {
                if (! $user?->can('updateStatus', $order)) {
                    if ($this->has('status_pengajuan')) {
                        $v->errors()->add('status_pengajuan', 'Anda tidak berhak mengubah status pengajuan.');
                    }
                    if ($this->has('status_order')) {
                        $v->errors()->add('status_order', 'Anda tidak berhak mengubah status order.');
                    }
                    if ($this->has('status_product')) {
                        $v->errors()->add('status_product', 'Anda tidak berhak mengubah status produk.');
                    }
                }
            }

            // ===== Completed butuh konfirmasi & bukti delivery =====
            if ($this->has('status_order') && $so === 'completed' && $order) {
                $hasConfirm = (bool) $order->delivered_at;
                $hasProof   = is_array($order->delivery_images) && count($order->delivery_images) > 0;

                if (! $hasConfirm || ! $hasProof) {
                    $v->errors()->add(
                        'status_order',
                        'Tidak bisa set completed tanpa konfirmasi & bukti delivery.'
                    );
                }
            }
        });
    }
}
