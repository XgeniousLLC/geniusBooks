<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreVendorRequest;
use App\Http\Requests\Portal\UpdateVendorRequest;
use App\Models\Vendor;
use App\Support\ListQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Vendor::class);

        $list = ListQuery::fromRequest($request, [
            'defaultSort' => 'name',
            'allowedSorts' => ['name', 'email', 'created_at'],
            'filters' => ['status'],
        ]);

        $query = Vendor::query();

        if (($list->filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($list->filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }

        $list->apply($query, ['name', 'email', 'phone', 'address', 'tax_id']);

        return Inertia::render('Vendors/Index', [
            'vendors' => $list->paginate($query)->through(fn (Vendor $vendor) => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'email' => $vendor->email,
                'phone' => $vendor->phone,
                'is_active' => $vendor->is_active,
            ]),
            'filters' => $list->meta(),
            'can' => ['create' => Gate::allows('create', Vendor::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Vendor::class);

        return Inertia::render('Vendors/Form', ['vendor' => null]);
    }

    public function store(StoreVendorRequest $request): RedirectResponse
    {
        Vendor::create($request->validated());

        return redirect()->route('portal.vendors.index')->with('success', 'Vendor created.');
    }

    public function edit(Vendor $vendor): Response
    {
        Gate::authorize('update', $vendor);

        return Inertia::render('Vendors/Form', [
            'vendor' => $vendor->only(['id', 'name', 'email', 'phone', 'address', 'tax_id', 'notes', 'is_active']),
        ]);
    }

    public function update(UpdateVendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $vendor->update($request->validated());

        return redirect()->route('portal.vendors.index')->with('success', 'Vendor updated.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        Gate::authorize('delete', $vendor);

        if ($vendor->expenses()->exists()) {
            return back()->with('error', 'Vendors with expenses cannot be deleted. Mark them inactive instead.');
        }

        $vendor->delete();

        return redirect()->route('portal.vendors.index')->with('success', 'Vendor archived.');
    }
}
