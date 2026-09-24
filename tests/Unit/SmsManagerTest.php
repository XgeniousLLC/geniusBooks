<?php

use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Http;

it('posts to a generic http sms gateway', function () {
    Http::fake(['sms.example.test/*' => Http::response(['status' => 'ok'])]);

    config()->set('sms.drivers.http', [
        'driver' => 'http',
        'url' => 'https://sms.example.test/send',
        'method' => 'POST',
        'token' => 'secret-token',
        'params' => ['api_key' => 'gateway-key'],
    ]);

    (new SmsManager)->driver('http')->send('+8801700000000', 'Hello world');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'sms.example.test/send')
        && $request['to'] === '+8801700000000'
        && $request['message'] === 'Hello world'
        && $request['api_key'] === 'gateway-key'
        && $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('posts to twilio with basic auth', function () {
    Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'])]);

    config()->set('sms.drivers.twilio', [
        'driver' => 'twilio', 'sid' => 'AC123', 'token' => 'tok', 'from' => '+100',
    ]);

    (new SmsManager)->driver('twilio')->send('+8801700000000', 'Hi');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'Accounts/AC123/Messages.json')
        && $request['To'] === '+8801700000000'
        && $request['From'] === '+100'
        && $request['Body'] === 'Hi');
});

it('records messages with the fake driver', function () {
    $fake = SmsManager::fake();

    app(SmsManager::class)->send('+123', 'Test');

    $fake->assertSent(fn ($message) => $message['to'] === '+123' && $message['message'] === 'Test');
});

it('ignores empty recipients or messages', function () {
    $fake = SmsManager::fake();

    app(SmsManager::class)->send('  ', 'Hello');
    app(SmsManager::class)->send('+123', '   ');

    $fake->assertNothingSent();
});
