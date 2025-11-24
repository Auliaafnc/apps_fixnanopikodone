<?php

namespace App\Filament\Admin\Resources\BrandResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BrandTransformer extends JsonResource
{
    public function toArray($request): array
    {
        $this->resource->loadMissing(['company:id,name', 'categories:id,brand_id', 'products:id,brand_id']);

        $status = $this->status === 'active' ? 'Aktif' : 'Nonaktif';

        return [
            'id'               => $this->id,
            'company'          => $this->company?->name,
            'name'             => $this->name,
            'deskripsi'        => $this->deskripsi,
            'status'           => $status,
            'image'            => $this->image ? Storage::url($this->image) : null,
            'categories_count' => $this->categories?->count() ?? 0,
            'products_count'   => $this->products?->count() ?? 0,
            'created_at'       => optional($this->created_at)->format('d/m/Y'),
            'updated_at'       => optional($this->updated_at)->format('d/m/Y'),
        ];
    }
}
