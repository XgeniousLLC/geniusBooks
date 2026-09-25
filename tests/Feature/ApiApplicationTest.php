<?php

use App\Models\ApiApplication;
use App\Models\Company;

it('owner can create and revoke an API application', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $res = $this->post(route('portal.settings.api.store'), [
        'name' => 'Zapier',
        'expires_at' => null,
    ]);
    $res->assertRedirect(route('portal.settings.api.index'));
    $res->assertSessionHas('plain_token');
    $plain = session('plain_token');
    expect($plain)->toStartWith('gb_');

    $app = ApiApplication::where('company_id', $company->id)->first();
    expect($app)->not->toBeNull();
    expect($app->token_hash)->toBe(ApiApplication::hashToken($plain));

    // revoke
    $this->delete(route('portal.settings.api.destroy', $app))->assertRedirect();
    expect(ApiApplication::find($app->id))->toBeNull();
});

it('staff cannot manage API applications', function () {
    [$user, $company] = userWithCompany('staff');
    actingAsCompany($user, $company);

    $this->post(route('portal.settings.api.store'), ['name' => 'X'])->assertForbidden();
});

it('API token authenticates and scopes to company', function () {
    [$user, $company] = userWithCompany('owner');
    $plain = ApiApplication::generateToken();
    ApiApplication::create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'name' => 'Test App',
        'token_hash' => ApiApplication::hashToken($plain),
    ]);

    // unauthenticated
    $this->getJson('/api/v1/me')->assertStatus(401);
    $this->getJson('/api/v1/customers')->assertStatus(401);

    // bad token
    $this->getJson('/api/v1/me', ['Authorization' => 'Bearer bad'])->assertStatus(401);

    // good token
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$plain}"])->assertOk()->assertJson(['company_id' => $company->id]);

    // expired token
    $expiredPlain = ApiApplication::generateToken();
    ApiApplication::create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'name' => 'Expired',
        'token_hash' => ApiApplication::hashToken($expiredPlain),
        'expires_at' => now()->subDay(),
    ]);
    $this->getJson('/api/v1/me', ['Authorization' => "Bearer {$expiredPlain}"])->assertStatus(401);
});

it('API token cannot access another company', function () {
    [$userA, $companyA] = userWithCompany('owner');
    [$userB, $companyB] = userWithCompany('owner');
    \App\Models\Customer::factory()->create(['company_id' => $companyB->id, 'name' => 'OtherCo Customer']);

    $plain = ApiApplication::generateToken();
    ApiApplication::create([
        'company_id' => $companyA->id,
        'created_by' => $userA->id,
        'name' => 'App A',
        'token_hash' => ApiApplication::hashToken($plain),
    ]);

    $res = $this->getJson('/api/v1/customers', ['Authorization' => "Bearer {$plain}"]);
    $res->assertOk();
    expect(collect($res->json('data'))->pluck('name'))->not->toContain('OtherCo Customer');
});

// Helper to create customer via factory with company_id
