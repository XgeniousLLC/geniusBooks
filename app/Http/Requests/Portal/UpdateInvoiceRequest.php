<?php

namespace App\Http\Requests\Portal;

use App\Models\Invoice;

class UpdateInvoiceRequest extends StoreInvoiceRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            && (bool) $this->user()?->can('update', $invoice);
    }
}
