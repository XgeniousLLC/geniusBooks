<?php

use App\Models\Company;
use App\Support\FinancialYear;
use Carbon\CarbonImmutable;

function company(array $attributes): Company
{
    return new Company(array_merge([
        'timezone' => 'UTC',
        'financial_year_start_month' => 1,
        'financial_year_start_day' => 1,
    ], $attributes));
}

it('resolves a calendar-year financial year', function () {
    [$start, $end] = FinancialYear::range(
        company([]),
        CarbonImmutable::parse('2026-06-15 12:00:00', 'UTC')
    );

    expect($start->toDateString())->toBe('2026-01-01')
        ->and($end->toDateString())->toBe('2026-12-31')
        ->and($end->format('H:i:s'))->toBe('23:59:59');
});

it('resolves an April-start financial year across year boundaries', function () {
    $fy = company(['financial_year_start_month' => 4, 'financial_year_start_day' => 1]);

    [$start, $end] = FinancialYear::range($fy, CarbonImmutable::parse('2026-06-15', 'UTC'));
    expect($start->toDateString())->toBe('2026-04-01')
        ->and($end->toDateString())->toBe('2027-03-31');

    [$start2, $end2] = FinancialYear::range($fy, CarbonImmutable::parse('2026-02-15', 'UTC'));
    expect($start2->toDateString())->toBe('2025-04-01')
        ->and($end2->toDateString())->toBe('2026-03-31');
});

it('evaluates boundaries in the company timezone', function () {
    $fy = company(['timezone' => 'America/New_York']);

    $start = FinancialYear::start($fy, CarbonImmutable::parse('2026-01-01 02:00:00', 'UTC'));

    expect($start->toDateString())->toBe('2025-01-01');
});

it('clamps a start day beyond the month length', function () {
    $fy = company(['financial_year_start_month' => 2, 'financial_year_start_day' => 31]);

    $start = FinancialYear::start($fy, CarbonImmutable::parse('2026-03-01', 'UTC'));

    expect($start->toDateString())->toBe('2026-02-28');
});
