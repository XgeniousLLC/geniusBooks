<?php

use App\Models\User;

it('renders the legal pages', function () {
    $this->get('/legal/terms')->assertOk()->assertInertia(fn ($page) => $page->component('Legal/Terms'));
    $this->get('/legal/privacy')->assertOk()->assertInertia(fn ($page) => $page->component('Legal/Privacy'));
});

it('blocks the last owner from deleting their account', function () {
    [$owner, $company] = userWithCompany('owner');
    actingAsCompany($owner, $company);

    $this->post('/portal/account')->assertRedirect()->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $owner->id]);
});

it('allows a non-owner member to delete their account', function () {
    [$staff, $company] = userWithCompany('staff');
    actingAsCompany($staff, $company);

    $this->post('/portal/account')->assertRedirect(route('portal.login'));

    $fresh = User::find($staff->id);
    expect($fresh)->not->toBeNull()
        ->and($fresh->is_active)->toBeFalse()
        ->and($fresh->email)->toStartWith('deleted+')
        ->and($company->users()->whereKey($staff->id)->exists())->toBeFalse();
});
