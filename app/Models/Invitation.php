<?php

namespace App\Models;

use App\Enums\CompanyRole;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invitation extends Model
{
    use Auditable, BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'role' => CompanyRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $invitation) {
            if (empty($invitation->token)) {
                $invitation->token = self::generateToken();
            }
        });
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')->where('expires_at', '>', now());
    }
}
