<?php

namespace App\Filament\Admin\Resources\BannerResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BannerTransformer extends JsonResource
{
    public function toArray($request): array
    {
        $this->resource->loadMissing(['company:id,name']);

        $img = fn ($p) => $p ? Storage::url($p) : null;

        return [
            'id'       => $this->id,
            'company'  => $this->company?->name,
            'image_1'  => $img($this->image_1),
            'image_2'  => $img($this->image_2),
            'image_3'  => $img($this->image_3),
            'image_4'  => $img($this->image_4),
            'created_at' => optional($this->created_at)->format('d/m/Y'),
            'updated_at' => optional($this->updated_at)->format('d/m/Y'),
        ];
    }
}
