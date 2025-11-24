<?php

namespace App\Filament\Admin\Resources\CompanyResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CompanyTransformer extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'status'     => $this->status === 'active' ? 'Aktif' : 'Nonaktif',
            'address'    => strip_tags((string) ($this->full_address ?? '-')),
            'image'      => $this->image ? Storage::url($this->image) : null,
            'created_at' => optional($this->created_at)->format('d/m/Y'),
            'updated_at' => optional($this->updated_at)->format('d/m/Y'),
        ];
    }
}
