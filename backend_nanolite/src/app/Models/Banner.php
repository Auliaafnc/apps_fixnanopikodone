<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [ 'company_id','image_1','image_2','image_3','image_4'];
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

}
