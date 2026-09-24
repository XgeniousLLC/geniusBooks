<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\Voidable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes, Voidable;

    public const INTERVALS = ['weekly', 'monthly', 'yearly'];

    protected $fillable = [
        'company_id',
        'vendor_id',
        'expense_category_id',
        'bank_account_id',
        'transaction_id',
        'recurrence_parent_id',
        'date',
        'amount',
        'tax_amount',
        'currency',
        'description',
        'reference',
        'notes',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'is_recurring',
        'recurrence_interval',
        'next_recurrence_on',
        'last_generated_at',
        'voided_at',
        'voided_by',
        'void_reason',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
            'tax_amount' => 'integer',
            'is_recurring' => 'boolean',
            'next_recurrence_on' => 'date',
            'last_generated_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    public function recurrences(): HasMany
    {
        return $this->hasMany(self::class, 'recurrence_parent_id');
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    public function scopeRecurring($query)
    {
        return $query->where('is_recurring', true)->whereNull('voided_at');
    }
}
