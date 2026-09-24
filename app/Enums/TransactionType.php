<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Invoice = 'invoice';
    case Payment = 'payment';
    case Transfer = 'transfer';
    case Refund = 'refund';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Income => 'Income',
            self::Expense => 'Expense',
            self::Invoice => 'Invoice',
            self::Payment => 'Payment',
            self::Transfer => 'Transfer',
            self::Refund => 'Refund',
            self::Adjustment => 'Adjustment',
        };
    }
}
