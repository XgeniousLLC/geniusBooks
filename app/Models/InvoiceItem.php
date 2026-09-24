<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'discount_type',
        'discount_value',
        'tax_rate',
        'line_subtotal',
        'line_discount',
        'line_tax',
        'line_total',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'integer',
            'discount_value' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'line_subtotal' => 'integer',
            'line_discount' => 'integer',
            'line_tax' => 'integer',
            'line_total' => 'integer',
            'position' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
