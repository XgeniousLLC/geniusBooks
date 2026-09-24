<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single-entry ledger line. Every money movement is recorded here; account
 * balances and reports are derived from these rows.
 */
class Transaction extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'bank_account_id',
        'ledger_account_id',
        'type',
        'direction',
        'amount',
        'currency',
        'description',
        'occurred_on',
        'source_type',
        'source_id',
        'transfer_group',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'occurred_on' => 'date',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function ledgerAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function signedAmount(): int
    {
        return $this->direction === 'in' ? (int) $this->amount : -(int) $this->amount;
    }
}
