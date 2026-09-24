<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    public const INVOICE_SENT = 'invoice_sent';

    public const INVOICE_DUE = 'invoice_due';

    public const INVOICE_OVERDUE = 'invoice_overdue';

    public const PAYMENT_RECEIVED = 'payment_received';

    /** @var list<string> */
    public const KEYS = [
        self::INVOICE_SENT,
        self::INVOICE_DUE,
        self::INVOICE_OVERDUE,
        self::PAYMENT_RECEIVED,
    ];

    protected $fillable = [
        'company_id',
        'key',
        'subject',
        'body',
    ];

    public function label(): string
    {
        return match ($this->key) {
            self::INVOICE_SENT => 'Invoice sent',
            self::INVOICE_DUE => 'Invoice due soon',
            self::INVOICE_OVERDUE => 'Invoice overdue',
            self::PAYMENT_RECEIVED => 'Payment received',
            default => ucfirst(str_replace('_', ' ', $this->key)),
        };
    }
}
