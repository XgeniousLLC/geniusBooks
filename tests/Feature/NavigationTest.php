<?php

it('shares the active company and roles for a member', function () {
    [$owner, $company] = userWithCompany('owner', ['name' => 'ABC Digital Agency']);
    actingAsCompany($owner, $company);

    $this->get('/portal')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.roles', ['owner'])
            ->where('currentCompany.name', 'ABC Digital Agency')
            ->has('companies', 1));
});

it('shares empty roles when no company context exists', function () {
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)->get('/portal/onboarding')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('auth.roles', []));
});
