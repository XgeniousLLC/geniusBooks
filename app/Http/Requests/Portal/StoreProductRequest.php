<?php

namespace App\Http\Requests\Portal;

use App\Models\Product;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Product::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', $this->uniqueSkuRule()],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(Product::TYPES)],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    private function uniqueSkuRule(): \Illuminate\Validation\Rules\Unique
    {
        $rule = Rule::unique('products', 'sku')
            ->where('company_id', app(CompanyContext::class)->id());

        $product = $this->route('product');
        if ($product instanceof Product) {
            $rule->ignore($product->id);
        }

        return $rule;
    }
}
