<?php

namespace App\Services\Sms\Contracts;

interface SmsDriver
{
    /**
     * Send a plain-text SMS. Implementations throw on failure.
     */
    public function send(string $to, string $message, ?string $from = null): void;
}
