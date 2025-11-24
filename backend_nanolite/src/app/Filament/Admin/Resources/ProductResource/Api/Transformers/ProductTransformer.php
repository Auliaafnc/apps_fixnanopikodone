<?php

namespace App\Filament\Admin\Resources\ProductResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductTransformer extends JsonResource
{
    public function toArray($request): array
    {
        $this->resource->loadMissing(['brand:id,name','category:id,name','company:id,name']);

        return [
            'id'         => $this->id,
            'company'    => $this->company?->name ?? null,
            'brand'      => $this->brand?->name ?? '-',
            'category'   => $this->category?->name ?? '-',
            'name'       => $this->name,
            'price'      => (int) ($this->price ?? 0),
            'colors'     => is_array($this->colors) ? $this->colors : [],
            'description'=> $this->description,
            'image'      => $this->image ? Storage::url($this->image) : null,
            'status'     => $this->status === 'active' ? 'Aktif' : 'Nonaktif',
            'created_at' => optional($this->created_at)->format('d/m/Y'),
            'updated_at' => optional($this->updated_at)->format('d/m/Y'),
        ];
    }
}
