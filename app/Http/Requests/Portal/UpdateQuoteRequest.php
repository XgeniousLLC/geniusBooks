<?php

namespace App\Http\Requests\Portal;

use App\Models\Quote;

class UpdateQuoteRequest extends StoreQuoteRequest
{
    public function authorize(): bool
    {
        $quote = $this->route('quote');

        return $quote instanceof Quote
            && (bool) $this->user()?->can('update', $quote);
    }
}
