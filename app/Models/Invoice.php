<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Invoice extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'customer_id',
        'number',
        'status',
        'issue_date',
        'due_date',
        'currency',
        'tax_inclusive',
        'discount_type',
        'discount_value',
        'subtotal',
        'discount_total',
        'tax_total',
        'total',
        'amount_paid',
        'credit_total',
        'notes',
        'terms',
        'sent_at',
        'viewed_at',
        'paid_at',
        'cancelled_at',
        'cancel_reason',
        'due_reminder_sent_at',
        'overdue_reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'tax_inclusive' => 'boolean',
            'discount_value' => 'decimal:4',
            'subtotal' => 'integer',
            'discount_total' => 'integer',
            'tax_total' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'credit_total' => 'integer',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'due_reminder_sent_at' => 'datetime',
            'overdue_reminder_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Sent and later invoices are financial records: cancel, do not delete.
        static::deleting(function (self $invoice) {
            if ($invoice->status !== InvoiceStatus::Draft->value) {
                throw new RuntimeException(
                    'Only draft invoices can be deleted; cancel issued invoices instead.'
                );
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('position');
    }

    public function status(): InvoiceStatus
    {
        return InvoiceStatus::from($this->status);
    }

    /**
     * The status shown to users, including derived payment/overdue states.
     */
    public function displayStatus(): InvoiceStatus
    {
        $stored = $this->status();

        if ($stored === InvoiceStatus::Cancelled) {
            return $stored;
        }

        if ($this->total > 0 && $this->settledTotal() >= $this->total) {
            return InvoiceStatus::Paid;
        }

        if ($this->amount_paid > 0) {
            return InvoiceStatus::PartiallyPaid;
        }

        if (
            in_array($stored, [InvoiceStatus::Sent, InvoiceStatus::Viewed], true)
            && $this->due_date
            && $this->due_date->isPast()
        ) {
            return InvoiceStatus::Overdue;
        }

        return $stored;
    }

    public function balance(): int
    {
        return max(0, (int) $this->total - $this->settledTotal());
    }

    /**
     * Payments plus applied credits.
     */
    public function settledTotal(): int
    {
        return (int) $this->amount_paid + (int) $this->credit_total;
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft->value;
    }

    public function isCancelled(): bool
    {
        return $this->status === InvoiceStatus::Cancelled->value;
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /**
     * Record the first view of an issued invoice and advance its status.
     */
    public function markViewed(): bool
    {
        if ($this->viewed_at !== null || $this->isCancelled()) {
            return false;
        }

        $attributes = ['viewed_at' => now()];

        if ($this->status === InvoiceStatus::Sent->value) {
            $attributes['status'] = InvoiceStatus::Viewed->value;
        }

        $this->update($attributes);

        return true;
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            InvoiceStatus::Sent->value,
            InvoiceStatus::Viewed->value,
        ]);
    }
}
