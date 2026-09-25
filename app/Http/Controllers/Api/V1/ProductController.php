<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);
        return response()->json(Product::latest()->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'sku' => ['nullable','string','max:50'],
            'type' => ['required', Rule::in(['product','service'])],
            'unit_price' => ['required','integer','min:0'],
            'tax_rate' => ['nullable','numeric','min:0','max:100'],
            'category' => ['nullable','string','max:100'],
            'description' => ['nullable','string','max:2000'],
            'is_active' => ['sometimes','boolean'],
        ]);

        $product = Product::create($data);

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'sku' => ['nullable','string','max:50'],
            'type' => ['sometimes', Rule::in(['product','service'])],
            'unit_price' => ['sometimes','integer','min:0'],
            'tax_rate' => ['nullable','numeric','min:0','max:100'],
            'category' => ['nullable','string','max:100'],
            'description' => ['nullable','string','max:2000'],
            'is_active' => ['sometimes','boolean'],
        ]);

        $product->update($data);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(null, 204);
    }
}
