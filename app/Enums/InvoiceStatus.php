<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    /**
     * Statuses that may be stored directly on the invoice row.
     *
     * Partially paid / paid / overdue are derived from amounts and dates.
     *
     * @return list<string>
     */
    public static function stored(): array
    {
        return [self::Draft->value, self::Sent->value, self::Viewed->value, self::Cancelled->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Viewed => 'Viewed',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-600',
            self::Sent => 'bg-blue-100 text-blue-800',
            self::Viewed => 'bg-indigo-100 text-indigo-800',
            self::PartiallyPaid => 'bg-amber-100 text-amber-800',
            self::Paid => 'bg-green-100 text-green-800',
            self::Overdue => 'bg-red-100 text-red-800',
            self::Cancelled => 'bg-slate-200 text-slate-500',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Sent, self::Viewed, self::PartiallyPaid, self::Overdue], true);
    }
}
