<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('redirects unverified users to the verification notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/portal/verify-email')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Auth/VerifyEmail'));
});

it('sends a verification notification', function () {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->post('/portal/email/verification-notification')
        ->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('verifies the email through a signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $this->actingAs($user)->get($url)->assertRedirect(route('portal.onboarding'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects a tampered verification link', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get("/portal/verify-email/{$user->id}/invalid-hash")
        ->assertForbidden();
});
