<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditNote extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    public const STATUS_APPLIED = 'applied';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'company_id',
        'customer_id',
        'invoice_id',
        'bank_account_id',
        'transaction_id',
        'number',
        'issue_date',
        'amount',
        'reason',
        'status',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'amount' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
