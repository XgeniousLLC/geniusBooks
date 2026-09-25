<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(Vendor::latest()->paginate(min((int)$request->integer('per_page',15),100)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'phone' => ['nullable','string','max:50'],
            'tax_id' => ['nullable','string','max:50'],
            'address' => ['nullable','string','max:2000'],
        ]);
        $vendor = Vendor::create($data);
        return response()->json($vendor, 201);
    }

    public function show(Vendor $vendor)
    {
        return response()->json($vendor);
    }

    public function update(Request $request, Vendor $vendor)
    {
        $data = $request->validate([
            'name' => ['sometimes','string','max:255'],
            'email' => ['nullable','email','max:255'],
            'phone' => ['nullable','string','max:50'],
        ]);
        $vendor->update($data);
        return response()->json($vendor);
    }

    public function destroy(Vendor $vendor)
    {
        if ($vendor->expenses()->exists()) {
            return response()->json(['message' => 'Cannot delete vendor with expenses.'], 422);
        }
        $vendor->delete();
        return response()->json(null, 204);
    }
}
