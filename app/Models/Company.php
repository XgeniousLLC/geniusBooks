<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'email',
        'phone',
        'address',
        'country',
        'currency',
        'currency_symbol',
        'currency_position',
        'timezone',
        'financial_year_start_month',
        'financial_year_start_day',
        'tax_registration_number',
        'tax_inclusive',
        'default_tax_rate',
        'payment_instructions',
        'invoice_footer',
        'default_invoice_terms',
        'email_from_name',
        'email_reply_to',
        'reminders_enabled',
        'reminder_days_before',
        'reminder_days_overdue',
        'notify_invoice_sent',
        'notify_payment_received',
        'notify_invoice_due',
        'notify_invoice_overdue',
        'invoice_prefix',
        'invoice_number_padding',
        'default_payment_terms_days',
        'is_active',
        'onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'tax_inclusive' => 'boolean',
            'default_tax_rate' => 'decimal:4',
            'is_active' => 'boolean',
            'reminders_enabled' => 'boolean',
            'notify_invoice_sent' => 'boolean',
            'notify_payment_received' => 'boolean',
            'notify_invoice_due' => 'boolean',
            'notify_invoice_overdue' => 'boolean',
            'onboarded_at' => 'datetime',
            'financial_year_start_month' => 'integer',
            'financial_year_start_day' => 'integer',
            'reminder_days_before' => 'integer',
            'reminder_days_overdue' => 'integer',
            'invoice_number_padding' => 'integer',
            'default_payment_terms_days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $company) {
            if (empty($company->slug)) {
                $company->slug = static::uniqueSlug($company->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('is_active')
            ->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('is_active', true);
    }

    public function documentSequences(): HasMany
    {
        return $this->hasMany(DocumentSequence::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isOnboarded(): bool
    {
        return $this->onboarded_at !== null;
    }
}
