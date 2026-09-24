<?php

namespace App\Enums;

enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';
    case Converted = 'converted';

    /**
     * Stored statuses. "Expired" is derived from the valid-until date.
     *
     * @return list<string>
     */
    public static function stored(): array
    {
        return [
            self::Draft->value,
            self::Sent->value,
            self::Accepted->value,
            self::Declined->value,
            self::Converted->value,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Accepted => 'Accepted',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
            self::Converted => 'Converted',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-600',
            self::Sent => 'bg-blue-100 text-blue-800',
            self::Accepted => 'bg-green-100 text-green-800',
            self::Declined => 'bg-red-100 text-red-800',
            self::Expired => 'bg-amber-100 text-amber-800',
            self::Converted => 'bg-indigo-100 text-indigo-800',
        };
    }
}
