<?php

namespace App\Services;

use App\Models\EmailLog;

class EmailLogger
{
    public function log(
        ?int $companyId,
        string $to,
        string $subject,
        string $status = EmailLog::STATUS_QUEUED,
        ?string $mailable = null,
        ?string $error = null,
    ): EmailLog {
        return EmailLog::create([
            'company_id' => $companyId,
            'to_email' => $to,
            'subject' => $subject,
            'status' => $status,
            'mailable' => $mailable,
            'error' => $error,
        ]);
    }
}
