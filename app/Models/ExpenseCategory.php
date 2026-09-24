<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use Auditable, BelongsToCompany, HasFactory, SoftDeletes;

    /** @var list<string> */
    public const DEFAULTS = [
        'Advertising', 'Software', 'Office', 'Travel', 'Utilities', 'Rent',
        'Payroll', 'Professional Services', 'Equipment', 'Banking Fees',
        'Taxes', 'Other',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
