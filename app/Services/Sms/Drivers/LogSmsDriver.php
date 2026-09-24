<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsDriver;
use Illuminate\Support\Facades\Log;

class LogSmsDriver implements SmsDriver
{
    public function send(string $to, string $message, ?string $from = null): void
    {
        Log::info('SMS sent (log driver)', ['to' => $to, 'from' => $from, 'message' => $message]);
    }
}
