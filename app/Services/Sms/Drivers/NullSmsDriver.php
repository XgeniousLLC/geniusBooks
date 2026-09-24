<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsDriver;

class NullSmsDriver implements SmsDriver
{
    public function send(string $to, string $message, ?string $from = null): void
    {
        // Intentionally does nothing.
    }
}
