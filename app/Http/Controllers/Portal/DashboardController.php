<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Reporting\DashboardService;
use App\Support\CompanyContext;
use App\Support\FinancialYear;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard): Response
    {
        $company = Company::findOrFail(app(CompanyContext::class)->id());

        $period = $request->string('period', 'fy')->toString();
        [$from, $to] = match ($period) {
            'month' => [now()->startOfMonth(), now()->endOfDay()],
            'quarter' => [now()->startOfQuarter(), now()->endOfDay()],
            'year' => [now()->startOfYear(), now()->endOfDay()],
            default => [FinancialYear::start($company), now()->endOfDay()],
        };

        $trend = $request->string('trend', 'monthly')->toString() === 'weekly' ? 'weekly' : 'monthly';

        return Inertia::render('Dashboard', array_merge(
            $dashboard->overview($company, $from, $to, $trend),
            [
                'selected' => ['period' => $period, 'trend' => $trend],
                'companyName' => $company->name,
            ],
        ));
    }
}
