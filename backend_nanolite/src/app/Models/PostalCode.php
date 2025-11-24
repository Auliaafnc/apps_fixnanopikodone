<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostalCode extends Model
{
    protected $fillable = [
        'village_code',
        'village_name',
        'postal_code',
    ];
}
