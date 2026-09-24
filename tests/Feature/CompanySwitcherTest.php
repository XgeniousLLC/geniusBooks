<?php

use App\Models\Company;
use App\Models\User;

it('switches the active company for a member', function () {
    $user = User::factory()->create();

    $first = Company::factory()->create(['name' => 'First Co']);
    $second = Company::factory()->create(['name' => 'Second Co']);

    $user->companies()->attach([$first->id, $second->id]);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $first->id])
        ->post('/portal/company/switch', ['company_id' => $second->id])
        ->assertRedirect(route('portal.home'))
        ->assertSessionHas('current_company_id', $second->id);
});

it('forbids switching to a company the user does not belong to', function () {
    $user = User::factory()->create();
    $own = Company::factory()->create();
    $other = Company::factory()->create();

    $user->companies()->attach($own->id);

    $this->actingAs($user)
        ->withSession(['current_company_id' => $own->id])
        ->post('/portal/company/switch', ['company_id' => $other->id])
        ->assertForbidden();
});
