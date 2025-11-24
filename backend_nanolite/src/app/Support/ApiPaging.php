<?php

namespace App\Support;

use Illuminate\Http\Request;

trait ApiPaging
{
    protected function perPage(Request $request): int
    {
        return max(1, min((int) $request->get('per_page', 15), 500));
    }
}
