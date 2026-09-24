<?php

namespace App\Services\Documents;

use App\Models\Company;
use App\Models\Invoice;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\Storage;

/**
 * Renders professional invoice PDFs from company + invoice data.
 */
class InvoicePdfService
{
    public function make(Invoice $invoice): DomPdf
    {
        $invoice->loadMissing(['customer', 'items', 'company']);

        return Pdf::loadView('documents.invoice', [
            'invoice' => $invoice,
            'company' => $invoice->company,
            'customer' => $invoice->customer,
            'items' => $invoice->items,
            'logo' => $this->logoDataUri($invoice->company),
            'money' => fn (int $amount) => Money::of($amount, $invoice->currency)->format(),
        ])->setPaper('a4');
    }

    public function output(Invoice $invoice): string
    {
        return $this->make($invoice)->output();
    }

    public function filename(Invoice $invoice): string
    {
        return 'invoice-'.$invoice->number.'.pdf';
    }

    private function logoDataUri(?Company $company): ?string
    {
        if (! $company || ! $company->logo_path) {
            return null;
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($company->logo_path)) {
            return null;
        }

        $mime = $disk->mimeType($company->logo_path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($company->logo_path));
    }
}
