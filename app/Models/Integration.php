<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Integration extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'provider',
        'status',
        'access_token',
        'refresh_token',
        'external_id',
        'settings',
        'last_sync_at',
        'last_error',
    ];

    protected $casts = [
        'provider' => IntegrationProvider::class,
        'settings' => 'array',
        'last_sync_at' => 'datetime',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
    ];

    public function logs(): HasMany
    {
        return $this->hasMany(IntegrationSyncLog::class);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected' || $this->status === 'syncing';
    }

    public function providerLabel(): string
    {
        return $this->provider instanceof IntegrationProvider
            ? $this->provider->label()
            : IntegrationProvider::tryFrom((string) $this->provider)?->label() ?? (string) $this->provider;
    }
}
