<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsDriver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioSmsDriver implements SmsDriver
{
    public function __construct(
        private readonly ?string $sid,
        private readonly ?string $token,
        private readonly ?string $from,
    ) {}

    public function send(string $to, string $message, ?string $from = null): void
    {
        if (! $this->sid || ! $this->token) {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        Http::asForm()
            ->withBasicAuth($this->sid, $this->token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                'From' => $from ?: $this->from,
                'To' => $to,
                'Body' => $message,
            ])
            ->throw();
    }
}
