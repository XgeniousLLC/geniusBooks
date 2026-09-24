<?php

namespace App\Enums;

enum TransactionDirection: string
{
    case In = 'in';
    case Out = 'out';

    public function signed(int $amount): int
    {
        return $this === self::In ? $amount : -$amount;
    }
}
