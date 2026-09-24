<?php

namespace App\Console\Commands;

use App\Mail\TestEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestEmail extends Command
{
    protected $signature = 'mail:test {email : Recipient address}';

    protected $description = 'Send a test email to verify outbound mail configuration';

    public function handle(): int
    {
        $email = $this->argument('email');

        Mail::to($email)->send(new TestEmail);

        $this->info("Test email queued/sent to {$email} using the configured mailer.");

        return self::SUCCESS;
    }
}
