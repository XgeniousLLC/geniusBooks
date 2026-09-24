<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single line imported from a bank statement, matched to a ledger
 * transaction during reconciliation.
 */
class BankStatementLine extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'matched_transaction_id',
        'import_batch',
        'date',
        'description',
        'reference',
        'amount',
        'currency',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
            'reconciled_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matchedTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }

    public function isMatched(): bool
    {
        return $this->matched_transaction_id !== null;
    }

    public function isReconciled(): bool
    {
        return $this->reconciled_at !== null;
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('bank_account_id', $accountId);
    }
}
