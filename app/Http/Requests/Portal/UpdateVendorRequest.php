<?php

namespace App\Http\Requests\Portal;

use App\Models\Vendor;

class UpdateVendorRequest extends StoreVendorRequest
{
    public function authorize(): bool
    {
        $vendor = $this->route('vendor');

        return $vendor instanceof Vendor
            && (bool) $this->user()?->can('update', $vendor);
    }
}
