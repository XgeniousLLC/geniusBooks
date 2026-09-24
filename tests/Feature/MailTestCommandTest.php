<?php

use App\Mail\TestEmail;
use Illuminate\Support\Facades\Mail;

it('sends a test email through the configured mailer', function () {
    Mail::fake();

    $this->artisan('mail:test', ['email' => 'owner@example.test'])
        ->assertExitCode(0);

    Mail::assertSent(TestEmail::class, function (TestEmail $mail) {
        return $mail->hasTo('owner@example.test');
    });
});
