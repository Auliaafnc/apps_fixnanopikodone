<?php

namespace App\Filament\Admin\Resources\DepartmentResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentTransformer extends JsonResource
{
    public function toArray($request): array
    {
        $this->resource->loadMissing(['company:id,name','employees:id,department_id']);

        return [
            'id'              => $this->id,
            'company'         => $this->company?->name,
            'name'            => $this->name,
            'status'          => $this->status === 'active' ? 'Aktif' : 'Nonaktif',
            'employees_count' => $this->employees?->count() ?? 0,
            'created_at'      => optional($this->created_at)->format('d/m/Y'),
            'updated_at'      => optional($this->updated_at)->format('d/m/Y'),
        ];
    }
}
