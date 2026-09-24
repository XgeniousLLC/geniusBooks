<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LedgerAccount extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    /** @var list<array{code: string, name: string, type: string, parent?: string}> */
    public const DEFAULTS = [
        ['code' => '1000', 'name' => 'Assets', 'type' => 'asset'],
        ['code' => '1100', 'name' => 'Cash', 'type' => 'asset', 'parent' => '1000'],
        ['code' => '1200', 'name' => 'Bank Account', 'type' => 'asset', 'parent' => '1000'],
        ['code' => '1300', 'name' => 'Accounts Receivable', 'type' => 'asset', 'parent' => '1000'],
        ['code' => '2000', 'name' => 'Liabilities', 'type' => 'liability'],
        ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'parent' => '2000'],
        ['code' => '3000', 'name' => 'Equity', 'type' => 'equity'],
        ['code' => '3100', 'name' => "Owner's Equity", 'type' => 'equity', 'parent' => '3000'],
        ['code' => '4000', 'name' => 'Revenue', 'type' => 'revenue'],
        ['code' => '4100', 'name' => 'Sales Revenue', 'type' => 'revenue', 'parent' => '4000'],
        ['code' => '5000', 'name' => 'Expenses', 'type' => 'expense'],
        ['code' => '5100', 'name' => 'Software', 'type' => 'expense', 'parent' => '5000'],
        ['code' => '5200', 'name' => 'Marketing', 'type' => 'expense', 'parent' => '5000'],
        ['code' => '5300', 'name' => 'Office', 'type' => 'expense', 'parent' => '5000'],
    ];

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'parent_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Net movement recorded directly against this account.
     */
    public function movement(): int
    {
        return (int) Transaction::withoutCompanyScope()
            ->where('ledger_account_id', $this->id)
            ->get(['direction', 'amount'])
            ->sum(fn (Transaction $transaction) => $transaction->signedAmount());
    }
}
