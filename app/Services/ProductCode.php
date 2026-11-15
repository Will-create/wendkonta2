<?php

namespace App\Services;

use Keygen\Keygen;
use Illuminate\Support\Facades\DB;

class ProductCode
{
    public function generate(): string
    {
        for ($attempt = 0; $attempt < 32; ++$attempt) {
            $code = (string) Keygen::numeric(8)->generate();
            if (!DB::table('products')->where('code', $code)->exists()
                && !DB::table('product_variants')->where('item_code', $code)->exists()) return $code;
        }
        throw new \RuntimeException('Unable to generate a unique product code. Please retry.');
    }
}
