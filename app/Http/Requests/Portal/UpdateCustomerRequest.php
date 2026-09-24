<?php

namespace App\Http\Requests\Portal;

class UpdateCustomerRequest extends StoreCustomerRequest
{
    public function authorize(): bool
    {
        $customer = $this->route('customer');

        return $customer instanceof \App\Models\Customer
            && (bool) $this->user()?->can('update', $customer);
    }
}
