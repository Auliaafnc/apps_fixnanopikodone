<?php
namespace App\Filament\Admin\Resources\PerbaikandataResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Perbaikandata;
use Illuminate\Support\Facades\Storage;

/**
 * @property Perbaikandata $resource
 */
class PerbaikandataTransformer extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $m = $this->resource;

        // siapkan image_url (ambil item pertama dari array image)
        $firstImage = is_array($m->image) && !empty($m->image) ? $m->image[0] : null;
        $imageUrl   = $firstImage
            ? (preg_match('#^https?://#i', $firstImage) ? $firstImage : Storage::url($firstImage))
            : null;

        return [
            'id'                      => $m->id,
            'department_name'         => optional($m->department)->name,
            'employee_name'           => optional($m->employee)->name,
            'customer_name'           => optional($m->customer)->name,
            'customer_category_name'  => optional($m->customerCategory)->name,

            'pilihan_data'            => $m->pilihan_data,
            'data_baru'               => $m->data_baru,

            // kirim address apa adanya (array) — Flutter sudah siap
            'address'                 => $m->address ?? [],
            'image_url'               => $imageUrl,
            'images'                  => collect($m->image ?? [])->map(
                fn($p) => preg_match('#^https?://#i', $p) ? $p : Storage::url($p)
            )->values(),

            'status_pengajuan'        => $m->status_pengajuan,
            'alasan_penolakan'        => $m->alasan_penolakan,

            'created_at'              => optional($m->created_at)->toISOString(),
            'updated_at'              => optional($m->updated_at)->toISOString(),
        ];
    }
}