<?php

namespace App\Filament\Admin\Resources\CustomerCategoriesResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCategoriesTransformer extends JsonResource
{
    public function toArray($request): array
    {
        $this->resource->loadMissing(['company:id,name','customers:id,customer_categories_id']);

        return [
            'id'              => $this->id,
            'company'         => $this->company?->name,
            'name'            => $this->name,
            'deskripsi'       => $this->deskripsi,
            'status'          => $this->status === 'active' ? 'Aktif' : 'Nonaktif',
            'customers_count' => $this->customers?->count() ?? 0,
            'created_at'      => optional($this->created_at)->format('d/m/Y'),
            'updated_at'      => optional($this->updated_at)->format('d/m/Y'),
        ];
    }
}
