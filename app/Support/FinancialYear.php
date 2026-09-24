<?php

namespace App\Support;

use App\Models\Company;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Resolves a company's financial-year window from its configured start
 * month/day, evaluated in the company timezone.
 */
final class FinancialYear
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} [start, end] inclusive
     */
    public static function range(Company $company, ?CarbonInterface $date = null): array
    {
        $start = self::start($company, $date);

        return [$start, $start->addYear()->subSecond()];
    }

    public static function start(Company $company, ?CarbonInterface $date = null): CarbonImmutable
    {
        $timezone = $company->timezone ?: 'UTC';
        $date = $date
            ? CarbonImmutable::instance($date)->setTimezone($timezone)
            : CarbonImmutable::now($timezone);

        $month = (int) ($company->financial_year_start_month ?: 1);
        $day = (int) ($company->financial_year_start_day ?: 1);

        $candidate = CarbonImmutable::create($date->year, $month, 1, 0, 0, 0, $timezone)
            ->setDay(min($day, CarbonImmutable::create($date->year, $month, 1)->daysInMonth));

        return $date->lessThan($candidate)
            ? $candidate->subYear()
            : $candidate;
    }
}
