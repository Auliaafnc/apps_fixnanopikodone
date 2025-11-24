<?php

namespace App\Filament\Admin\Resources\CategoryResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryTransformer extends JsonResource
{
    public function toArray($request): array
    {
        $this->resource->loadMissing(['company:id,name','brand:id,name','products:id,category_id']);

        return [
            'id'             => $this->id,
            'company'        => $this->company?->name,
            'brand'          => $this->brand?->name,
            'name'           => $this->name,
            'deskripsi'      => $this->deskripsi,
            'status'         => $this->status === 'active' ? 'Aktif' : 'Nonaktif',
            'image'          => $this->image ? Storage::url($this->image) : null,
            'products_count' => $this->products?->count() ?? 0,
            'created_at'     => optional($this->created_at)->format('d/m/Y'),
            'updated_at'     => optional($this->updated_at)->format('d/m/Y'),
        ];
    }
}
