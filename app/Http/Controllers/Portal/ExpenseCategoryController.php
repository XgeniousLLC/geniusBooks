<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', ExpenseCategory::class);

        $categories = ExpenseCategory::query()
            ->withCount('expenses')
            ->orderBy('name')
            ->get()
            ->map(fn (ExpenseCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'is_default' => $category->is_default,
                'expenses_count' => $category->expenses_count,
            ])
            ->values();

        return Inertia::render('Expenses/Categories', [
            'categories' => $categories,
            'can' => [
                'create' => Gate::allows('create', ExpenseCategory::class),
            ],
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::create([
            'name' => $request->validated()['name'],
            'is_default' => false,
            'is_active' => true,
        ]);

        return back()->with('success', 'Category added.');
    }

    public function destroy(ExpenseCategory $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        if ($category->expenses()->exists()) {
            return back()->with('error', 'This category is in use and cannot be deleted.');
        }

        $category->delete();

        return back()->with('success', 'Category removed.');
    }
}
