<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Services\Imports\CsvImportService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function customers(): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('Imports/Upload', [
            'type' => 'customers',
            'title' => 'Import customers',
            'columns' => CsvImportService::CUSTOMER_COLUMNS,
            'sample' => ['Acme Ltd', 'Acme Corporation', 'billing@acme.test', '+1 555 0100', '1 Main St', '', 'TAX-1234', '15', ''],
            'indexUrl' => route('portal.customers.index'),
            'templateUrl' => route('portal.imports.customers.template'),
        ]);
    }

    public function importCustomers(Request $request, CsvImportService $imports): RedirectResponse
    {
        Gate::authorize('create', Customer::class);

        $path = $this->validatedFilePath($request);

        try {
            $result = $imports->importCustomers($this->company(), $path);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('portal.imports.customers')
            ->with('importResult', $result->toArray());
    }

    public function products(): Response
    {
        Gate::authorize('create', Product::class);

        return Inertia::render('Imports/Upload', [
            'type' => 'products',
            'title' => 'Import products & services',
            'columns' => CsvImportService::PRODUCT_COLUMNS,
            'sample' => ['Website Development', 'WEB-DEV', 'Build a website', 'service', '2500.00', '10', 'Consulting'],
            'indexUrl' => route('portal.products.index'),
            'templateUrl' => route('portal.imports.products.template'),
        ]);
    }

    public function importProducts(Request $request, CsvImportService $imports): RedirectResponse
    {
        Gate::authorize('create', Product::class);

        $path = $this->validatedFilePath($request);

        try {
            $result = $imports->importProducts($this->company(), $path);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()->route('portal.imports.products')
            ->with('importResult', $result->toArray());
    }

    public function customersTemplate(): StreamedResponse
    {
        return $this->template(CsvImportService::CUSTOMER_COLUMNS, 'customers-template.csv');
    }

    public function productsTemplate(): StreamedResponse
    {
        return $this->template(CsvImportService::PRODUCT_COLUMNS, 'products-template.csv');
    }

    private function validatedFilePath(Request $request): string
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        return $request->file('file')->getRealPath();
    }

    /**
     * @param  list<string>  $columns
     */
    private function template(array $columns, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $columns);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}
