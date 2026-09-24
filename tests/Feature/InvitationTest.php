<?php

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use Illuminate\Support\Facades\Mail;

it('lets an owner invite a teammate', function () {
    Mail::fake();

    [$owner, $company] = userWithCompany('owner');
    actingAsCompany($owner, $company);

    $this->post('/portal/settings/invitations', [
        'email' => 'newhire@example.test',
        'role' => 'staff',
    ])->assertRedirect();

    $this->assertDatabaseHas('invitations', [
        'company_id' => $company->id,
        'email' => 'newhire@example.test',
        'role' => 'staff',
    ]);

    Mail::assertSent(InvitationMail::class, fn ($mail) => $mail->hasTo('newhire@example.test'));
});

it('forbids staff from inviting', function () {
    [$staff, $company] = userWithCompany('staff');
    actingAsCompany($staff, $company);

    $this->post('/portal/settings/invitations', [
        'email' => 'newhire@example.test',
        'role' => 'staff',
    ])->assertForbidden();
});

it('rejects inviting an existing member', function () {
    [$owner, $company] = userWithCompany('owner');
    $member = User::factory()->create(['email' => 'existing@example.test']);
    $company->users()->attach($member->id, ['is_active' => true]);

    actingAsCompany($owner, $company);

    $this->post('/portal/settings/invitations', [
        'email' => 'existing@example.test',
        'role' => 'staff',
    ])->assertSessionHasErrors('email');
});

it('rejects a duplicate pending invitation', function () {
    [$owner, $company] = userWithCompany('owner');
    Invitation::factory()->for($company)->create(['email' => 'pending@example.test']);

    actingAsCompany($owner, $company);

    $this->post('/portal/settings/invitations', [
        'email' => 'pending@example.test',
        'role' => 'staff',
    ])->assertSessionHasErrors('email');
});

it('revokes an invitation', function () {
    [$owner, $company] = userWithCompany('owner');
    $invitation = Invitation::factory()->for($company)->create();

    actingAsCompany($owner, $company);

    $this->delete("/portal/settings/invitations/{$invitation->id}")->assertRedirect();

    $this->assertDatabaseMissing('invitations', ['id' => $invitation->id]);
});

it('accepts an invitation by creating a verified account and membership', function () {
    $company = \App\Models\Company::factory()->create();
    $invitation = Invitation::factory()->for($company)->role(\App\Enums\CompanyRole::Accountant)->create([
        'email' => 'invitee@example.test',
    ]);

    $this->post("/portal/invitations/{$invitation->token}", [
        'name' => 'Invitee Person',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('portal.home'));

    $user = User::where('email', 'invitee@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->belongsToCompany($company->id))->toBeTrue()
        ->and(app(CompanyProvisioningService::class)->roleOf($company, $user)->value)->toBe('accountant')
        ->and($invitation->fresh()->isAccepted())->toBeTrue();
});

it('accepts an invitation for an already authenticated matching user', function () {
    $company = \App\Models\Company::factory()->create();
    $user = User::factory()->create(['email' => 'member@example.test']);
    $invitation = Invitation::factory()->for($company)->role(\App\Enums\CompanyRole::Staff)->create([
        'email' => 'member@example.test',
    ]);

    $this->actingAs($user)
        ->post("/portal/invitations/{$invitation->token}")
        ->assertRedirect(route('portal.home'));

    expect($user->belongsToCompany($company->id))->toBeTrue()
        ->and(app(CompanyProvisioningService::class)->roleOf($company, $user->fresh())->value)->toBe('staff');
});

it('rejects an expired invitation', function () {
    $company = \App\Models\Company::factory()->create();
    $invitation = Invitation::factory()->for($company)->expired()->create([
        'email' => 'late@example.test',
    ]);

    $this->from('/portal/login')
        ->post("/portal/invitations/{$invitation->token}", [
            'name' => 'Late Person',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertSessionHas('error');

    expect(User::where('email', 'late@example.test')->exists())->toBeFalse();
});

it('redirects an already accepted invitation to login', function () {
    $company = \App\Models\Company::factory()->create();
    $invitation = Invitation::factory()->for($company)->accepted()->create();

    $this->get("/portal/invitations/{$invitation->token}")
        ->assertRedirect(route('portal.login'));
});

it('renders the invitation acceptance page', function () {
    $company = \App\Models\Company::factory()->create();
    $invitation = Invitation::factory()->for($company)->create();

    $this->get("/portal/invitations/{$invitation->token}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Invitations/Accept'));
});
