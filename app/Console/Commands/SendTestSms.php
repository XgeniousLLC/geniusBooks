<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsManager;
use Illuminate\Console\Command;
use Throwable;

class SendTestSms extends Command
{
    protected $signature = 'sms:test {phone : Recipient phone number in E.164 format} {--message= : Optional custom message}';

    protected $description = 'Send a test SMS to verify the configured gateway';

    public function handle(SmsManager $sms): int
    {
        $phone = (string) $this->argument('phone');
        $message = $this->option('message') ?: 'Test message from '.config('app.name').' at '.now()->toDateTimeString();

        try {
            $sms->send($phone, $message);
        } catch (Throwable $e) {
            $this->error('SMS failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Test SMS sent to {$phone} using driver [".config('sms.driver').'].');

        return self::SUCCESS;
    }
}
