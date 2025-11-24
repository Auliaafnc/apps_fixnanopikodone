<?php

namespace App\Filament\Admin\Resources\EmployeeResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EmployeeTransformer extends JsonResource
{
    public function toArray($request): array
    {
        $this->resource->loadMissing(['company:id,name','department:id,name']);

        return [
            'id'         => $this->id,
            'company'    => $this->company?->name,
            'department' => $this->department?->name,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'address'    => strip_tags((string) ($this->full_address ?? '-')),
            'photo'      => $this->photo ? Storage::url($this->photo) : null,
            'status'     => $this->status === 'active' ? 'Aktif' : 'Nonaktif',
            'created_at' => optional($this->created_at)->format('d/m/Y'),
            'updated_at' => optional($this->updated_at)->format('d/m/Y'),
        ];
    }
}
