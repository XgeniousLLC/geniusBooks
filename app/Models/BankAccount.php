<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    public const TYPES = ['bank', 'cash', 'other'];

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'currency',
        'opening_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Balance is derived: opening balance plus the signed ledger movements.
     */
    public function balance(): int
    {
        $signed = Transaction::withoutCompanyScope()
            ->where('bank_account_id', $this->id)
            ->get(['direction', 'amount'])
            ->sum(fn (Transaction $transaction) => $transaction->direction === 'in'
                ? (int) $transaction->amount
                : -(int) $transaction->amount);

        return (int) $this->opening_balance + $signed;
    }
}
