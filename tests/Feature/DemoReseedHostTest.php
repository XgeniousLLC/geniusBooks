<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

it('creates a working demo owner for a new host after importing data seeded on another host', function () {
    // 1. Seed as if on the "local" host.
    Config::set('accounting.demo.email', 'demo@local.test');
    $this->seed(DemoDataSeeder::class);

    $localUser = User::where('email', 'demo@local.test')->first();
    expect($localUser)->not->toBeNull();
    expect($localUser->companies()->count())->toBe(2);

    $abc = Company::where('name', 'ABC Digital Agency')->first();
    $abcCount = Invoice::withoutCompanyScope()->where('company_id', $abc->id)->count();
    expect($abcCount)->toBeGreaterThan(0);

    // 2. Re-seed as if on the production host (simulates importing the DB then
    //    running the seeder so the domain-correct demo account exists).
    Config::set('accounting.demo.email', 'demo@prod.example.com');
    $this->seed(DemoDataSeeder::class);

    $prodUser = User::where('email', 'demo@prod.example.com')->first();
    expect($prodUser)->not->toBeNull();

    // It can actually authenticate and is attached to the existing companies.
    expect(Auth::guard('web')->attempt([
        'email' => 'demo@prod.example.com',
        'password' => 'password',
    ]))->toBeTrue();

    expect($prodUser->companies()->count())->toBe(2);
    expect($abc->users()->whereKey($prodUser->id)->exists())->toBeTrue();
    expect(Company::where('name', 'Nova Retail Ltd')->first()->users()->whereKey($prodUser->id)->exists())->toBeTrue();

    // Original demo data is untouched (idempotent).
    expect(Invoice::withoutCompanyScope()->where('company_id', $abc->id)->count())->toBe($abcCount);
});
