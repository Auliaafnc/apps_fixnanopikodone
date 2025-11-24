<?php
namespace App\Filament\Admin\Resources\PointMinimumResource\Api\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\PointMinimum;

/**
 * @property PointMinimum $resource
 */
class PointMinimumTransformer extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return $this->resource->toArray();
    }
}
