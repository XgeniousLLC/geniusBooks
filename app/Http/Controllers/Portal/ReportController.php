<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Services\Reporting\ReportService;
use App\Support\CompanyContext;
use App\Support\FinancialYear;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(): Response
    {
        Gate::authorize(Permission::ViewReports);

        return Inertia::render('Reports/Index', [
            'reports' => [
                ['key' => 'profit-and-loss', 'title' => 'Profit & Loss', 'description' => 'Revenue, expenses and net profit.'],
                ['key' => 'income', 'title' => 'Income', 'description' => 'Income by customer and by month.'],
                ['key' => 'expenses', 'title' => 'Expenses', 'description' => 'Spending by category and vendor.'],
                ['key' => 'receivables', 'title' => 'Receivables', 'description' => 'Outstanding invoices and aging.'],
                ['key' => 'tax-summary', 'title' => 'Tax Summary', 'description' => 'Tax collected, paid and taxable sales.'],
            ],
        ]);
    }

    public function profitAndLoss(Request $request): Response|HttpResponse|StreamedResponse
    {
        return $this->render($request, 'profit-and-loss');
    }

    public function income(Request $request): Response|HttpResponse|StreamedResponse
    {
        return $this->render($request, 'income');
    }

    public function expenses(Request $request): Response|HttpResponse|StreamedResponse
    {
        return $this->render($request, 'expenses');
    }

    public function receivables(Request $request): Response|HttpResponse|StreamedResponse
    {
        return $this->render($request, 'receivables');
    }

    public function taxSummary(Request $request): Response|HttpResponse|StreamedResponse
    {
        return $this->render($request, 'tax-summary');
    }

    public function statement(Request $request, Customer $customer): Response|HttpResponse|StreamedResponse
    {
        Gate::authorize(Permission::ViewReports);

        [$from, $to] = $this->range($request, $customer->company);
        $statement = $this->reports->customerStatement($customer, $from, $to);

        if ($request->filled('format')) {
            return $this->respond($request, $this->statementAsReport($statement), 'statement');
        }

        return Inertia::render('Reports/Statement', ['statement' => $statement]);
    }

    public function emailStatement(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize(Permission::ViewReports);

        if (! $customer->email) {
            return back()->with('error', 'This customer has no email address.');
        }

        [$from, $to] = $this->range($request, $customer->company);
        $statement = $this->reports->customerStatement($customer, $from, $to);
        $pdf = Pdf::loadView('reports.pdf', ['report' => $this->statementAsReport($statement)])->output();

        \Illuminate\Support\Facades\Mail::to($customer->email)->queue(
            new \App\Mail\StatementMail($customer, $statement, $pdf)
        );

        return back()->with('success', 'Statement emailed to '.$customer->email.'.');
    }

    private function render(Request $request, string $key): Response|HttpResponse|StreamedResponse
    {
        Gate::authorize(Permission::ViewReports);

        $company = $this->company();
        [$from, $to] = $this->range($request, $company);

        $report = match ($key) {
            'profit-and-loss' => $this->reports->profitAndLoss($company, $from, $to),
            'income' => $this->reports->income($company, $from, $to),
            'expenses' => $this->reports->expenses($company, $from, $to),
            'receivables' => $this->reports->receivables($company, $to),
            'tax-summary' => $this->reports->taxSummary($company, $from, $to),
        };

        return $this->respond($request, $report, $key);
    }

    private function respond(Request $request, array $report, string $key): Response|HttpResponse|StreamedResponse
    {
        $format = $request->string('format')->toString();

        if ($format === 'csv') {
            return $this->csv($report, $key);
        }

        if ($format === 'pdf') {
            return Pdf::loadView('reports.pdf', ['report' => $report])->download($key.'.pdf');
        }

        return Inertia::render('Reports/View', ['report' => $report]);
    }

    private function csv(array $report, string $key): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$report['title']]);
            fputcsv($out, ['From', $report['period']['from'], 'To', $report['period']['to']]);
            fputcsv($out, []);

            fputcsv($out, ['Summary']);
            foreach ($report['summary'] as $stat) {
                fputcsv($out, [$stat['label'], $stat['display']]);
            }

            foreach ($report['sections'] as $section) {
                fputcsv($out, []);
                fputcsv($out, [$section['heading']]);
                foreach ($section['rows'] as $row) {
                    fputcsv($out, [$row['label'], $row['display']]);
                }
                fputcsv($out, ['Total', $section['total_display']]);
            }

            fclose($out);
        }, $key.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request, Company $company): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())->startOfDay()
            : FinancialYear::start($company);

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    private function statementAsReport(array $statement): array
    {
        return [
            'key' => 'statement',
            'title' => 'Statement — '.$statement['customer']['name'],
            'period' => $statement['period'],
            'summary' => [
                ['label' => 'Opening balance', 'value' => 0, 'display' => $statement['opening_display'], 'emphasis' => false],
                ['label' => 'Closing balance', 'value' => 0, 'display' => $statement['closing_display'], 'emphasis' => true],
            ],
            'sections' => [
                [
                    'heading' => 'Activity',
                    'rows' => array_map(fn (array $row) => [
                        'label' => $row['date'].' · '.$row['type'].' · '.$row['reference'],
                        'value' => 0,
                        'display' => $row['debit_display'] ?: $row['credit_display'],
                    ], $statement['rows']),
                    'total' => 0,
                    'total_display' => $statement['closing_display'],
                ],
            ],
        ];
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}
