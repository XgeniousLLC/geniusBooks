<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreProductRequest;
use App\Http\Requests\Portal\UpdateProductRequest;
use App\Models\Company;
use App\Models\Product;
use App\Support\CompanyContext;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Product::class);

        $list = ListQuery::fromRequest($request, [
            'defaultSort' => 'name',
            'allowedSorts' => ['name', 'sku', 'unit_price', 'created_at'],
            'filters' => ['type', 'status', 'category'],
        ]);

        $query = Product::query();
        $this->applyFilters($query, $list->filters);
        $list->apply($query, ['name', 'sku', 'description', 'category']);

        $company = $this->company();

        return Inertia::render('Products/Index', [
            'products' => $list->paginate($query)->through(
                fn (Product $product) => $this->present($product, $company)
            ),
            'filters' => $list->meta(),
            'categories' => Product::query()
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
            'can' => [
                'create' => Gate::allows('create', Product::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('Products/Form', [
            'product' => null,
            'currency' => $this->company()->currency,
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($this->payload($request->validated()));

        return redirect()
            ->route('portal.products.index')
            ->with('success', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        Gate::authorize('update', $product);

        $currency = $this->company()->currency;

        return Inertia::render('Products/Form', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'type' => $product->type,
                'unit_price' => Money::of((int) $product->unit_price, $currency)->toDecimal(),
                'tax_rate' => $product->tax_rate,
                'category' => $product->category,
                'is_active' => $product->is_active,
            ],
            'currency' => $currency,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($this->payload($request->validated()));

        return redirect()
            ->route('portal.products.index')
            ->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('delete', $product);

        $product->delete();

        return redirect()
            ->route('portal.products.index')
            ->with('success', 'Product archived.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Product::class);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'action' => ['required', \Illuminate\Validation\Rule::in(['archive', 'activate'])],
        ]);

        $active = $data['action'] === 'activate';
        $count = Product::whereIn('id', $data['ids'])->update(['is_active' => $active]);

        return back()->with('success', $count.' product(s) '.($active ? 'activated' : 'archived').'.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $data['unit_price'] = Money::fromDecimal(
            $data['unit_price'],
            $this->company()->currency
        )->amount();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (($filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Product $product, Company $company): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'type' => $product->type,
            'category' => $product->category,
            'unit_price' => (int) $product->unit_price,
            'unit_price_display' => Money::of((int) $product->unit_price, $company->currency)->format(),
            'tax_rate' => $product->tax_rate,
            'is_active' => $product->is_active,
        ];
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}
