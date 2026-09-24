<?php

namespace App\Http\Requests\Portal;

use App\Models\Product;

class UpdateProductRequest extends StoreProductRequest
{
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            && (bool) $this->user()?->can('update', $product);
    }
}
