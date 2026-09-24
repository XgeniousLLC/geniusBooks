<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    public const TYPES = ['product', 'service'];

    protected $fillable = [
        'company_id',
        'name',
        'sku',
        'description',
        'type',
        'unit_price',
        'tax_rate',
        'category',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'integer',
            'tax_rate' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function unitPriceMoney(): Money
    {
        return Money::of((int) $this->unit_price, $this->company?->currency ?? 'USD');
    }
}
