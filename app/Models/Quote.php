<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'converted_invoice_id',
        'number',
        'status',
        'issue_date',
        'valid_until',
        'currency',
        'tax_inclusive',
        'discount_type',
        'discount_value',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'notes',
        'terms',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'tax_inclusive' => 'boolean',
            'discount_value' => 'decimal:4',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function status(): QuoteStatus
    {
        return QuoteStatus::tryFrom($this->status) ?? QuoteStatus::Draft;
    }

    public function displayStatus(): QuoteStatus
    {
        $stored = $this->status();

        if ($stored === QuoteStatus::Sent && $this->valid_until && $this->valid_until->isPast()) {
            return QuoteStatus::Expired;
        }

        return $stored;
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [QuoteStatus::Draft->value, QuoteStatus::Sent->value], true);
    }

    public function isConverted(): bool
    {
        return $this->status === QuoteStatus::Converted->value;
    }
}
