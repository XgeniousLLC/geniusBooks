<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiApplication extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'token_hash',
        'last_used_at',
        'expires_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public static function generateToken(): string
    {
        return 'gb_'.Str::random(60);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
