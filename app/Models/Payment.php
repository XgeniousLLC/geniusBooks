<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'bank_account_id',
        'date',
        'amount',
        'method',
        'reference',
        'notes',
        'idempotency_key',
        'transaction_id',
        'voided_at',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'payment_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function allocatedTotal(): int
    {
        return (int) $this->allocations()->sum('amount');
    }

    public function unapplied(): int
    {
        return max(0, (int) $this->amount - $this->allocatedTotal());
    }

    public function currency(): string
    {
        return $this->bankAccount?->currency
            ?? $this->company?->currency
            ?? 'USD';
    }

    public function scopeNotVoided($query)
    {
        return $query->whereNull('voided_at');
    }
}
