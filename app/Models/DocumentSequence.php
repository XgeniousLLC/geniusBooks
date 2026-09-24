<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSequence extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'type',
        'prefix',
        'next_number',
        'padding',
    ];

    protected function casts(): array
    {
        return [
            'next_number' => 'integer',
            'padding' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
