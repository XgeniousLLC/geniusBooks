<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => null,
            'description' => fake()->sentence(3),
            'quantity' => 1,
            'unit_price' => fake()->numberBetween(1000, 100000),
            'discount_type' => null,
            'discount_value' => null,
            'tax_rate' => 0,
            'line_subtotal' => 0,
            'line_discount' => 0,
            'line_tax' => 0,
            'line_total' => 0,
            'position' => 0,
        ];
    }
}
