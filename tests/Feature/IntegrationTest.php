<?php

use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\Customer;
use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\SyncService;

it('owner can connect and disconnect integrations', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post(route('portal.settings.integrations.store'), [
        'provider' => 'xero',
        'access_token' => 'xero-token-123',
    ])->assertRedirect();

    $integration = Integration::where('company_id', $company->id)->where('provider', 'xero')->first();
    expect($integration)->not->toBeNull();
    expect($integration->status)->toBe('connected');
    expect($integration->provider)->toBe(IntegrationProvider::Xero);

    // update token
    $this->post(route('portal.settings.integrations.store'), [
        'provider' => 'xero',
        'access_token' => 'new-token-12345',
    ])->assertRedirect();
    expect($integration->fresh()->access_token)->toBe('new-token-12345');

    // disconnect
    $this->delete(route('portal.settings.integrations.destroy', $integration))->assertRedirect();
    expect($integration->fresh()->status)->toBe('disconnected');
});

it('staff cannot manage integrations', function () {
    [$user, $company] = userWithCompany('staff');
    actingAsCompany($user, $company);
    $this->post(route('portal.settings.integrations.store'), ['provider'=>'xero','access_token'=>'x'])->assertForbidden();
});

it('all ten providers are available and labels correct', function () {
    $options = IntegrationProvider::options();
    expect($options)->toHaveCount(10);
    $values = array_column($options, 'value');
    expect($values)->toContain('xero','quickbooks','freshbooks','hubspot','zoho_books','wave','sage','netsuite','myob','kashoo');

    foreach (IntegrationProvider::cases() as $p) {
        expect($p->apiBaseUrl())->not->toBeEmpty();
        expect($p->docsUrl())->toStartWith('https://');
        expect($p->supportsTwoWaySync())->toBeTrue();
    }
});

it('sync service pushes and pulls with logs', function () {
    [$user, $company] = userWithCompany('owner');
    $integration = (new IntegrationManager)->connect($company, IntegrationProvider::QuickBooks, ['access_token'=>'tok']);
    $customer = Customer::factory()->create(['company_id'=>$company->id]);

    $sync = app(SyncService::class);

    $push = $sync->push($integration, 'customer', $customer->id);
    expect($push['success'])->toBeTrue();
    expect($push['log']->direction)->toBe('push');
    expect($push['log']->status)->toBe('success');
    expect($integration->fresh()->last_sync_at)->not->toBeNull();

    $pull = $sync->pull($integration, 'customer');
    expect($pull['success'])->toBeTrue();
    expect($pull['log']->direction)->toBe('pull');
    // pull creates a local customer
    expect(Customer::withoutCompanyScope()->where('company_id',$company->id)->count())->toBeGreaterThan(1);
});

it('sync isolates by company', function () {
    [$u1, $c1] = userWithCompany('owner');
    [$u2, $c2] = userWithCompany('owner');
    $int1 = (new IntegrationManager)->connect($c1, IntegrationProvider::HubSpot, ['access_token'=>'t1']);
    $customer2 = Customer::factory()->create(['company_id'=>$c2->id]);

    $sync = app(SyncService::class);
    // Try to push c2's customer via c1's integration — should fail (not found)
    $result = $sync->push($int1, 'customer', $customer2->id);
    expect($result['success'])->toBeFalse();
    expect($result['log']->status)->toBe('failed');
});

it('logs endpoint returns JSON', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);
    $int = (new IntegrationManager)->connect($company, IntegrationProvider::FreshBooks, ['access_token'=>'t']);
    app(SyncService::class)->push($int, 'customer', Customer::factory()->create(['company_id'=>$company->id])->id);

    $res = $this->get(route('portal.settings.integrations.logs', $int));
    $res->assertOk()->assertJsonCount(1);
});
