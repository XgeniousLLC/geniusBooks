<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsDriver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class VonageSmsDriver implements SmsDriver
{
    public function __construct(
        private readonly ?string $key,
        private readonly ?string $secret,
        private readonly ?string $from,
    ) {}

    public function send(string $to, string $message, ?string $from = null): void
    {
        if (! $this->key || ! $this->secret) {
            throw new RuntimeException('Vonage credentials are not configured.');
        }

        Http::asForm()
            ->post('https://rest.nexmo.com/sms/json', [
                'api_key' => $this->key,
                'api_secret' => $this->secret,
                'from' => $from ?: $this->from,
                'to' => $to,
                'text' => $message,
            ])
            ->throw();
    }
}
