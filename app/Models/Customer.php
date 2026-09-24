<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'company_name',
        'email',
        'phone',
        'billing_address',
        'shipping_address',
        'tax_id',
        'currency',
        'payment_terms_days',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'payment_terms_days' => 'integer',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function effectiveCurrency(): string
    {
        return $this->currency ?: ($this->company?->currency ?? 'USD');
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creditNotes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function effectivePaymentTermsDays(): int
    {
        return $this->payment_terms_days
            ?? ($this->company?->default_payment_terms_days ?? 15);
    }
}
