<?php

use App\Models\Customer;
use App\Models\Invoice;

function recurringInvoice(array $attributes = []): Invoice
{
    $invoice = Invoice::factory()->create(array_merge([
        'status' => 'draft',
        'subtotal' => 50000,
        'total' => 50000,
        'is_recurring' => true,
        'recurrence_interval' => 'monthly',
        'next_recurrence_on' => now()->subDay()->toDateString(),
    ], $attributes));

    $invoice->items()->create([
        'description' => 'Retainer', 'quantity' => 1, 'unit_price' => 50000, 'position' => 0,
    ]);

    return $invoice;
}

it('generates a draft invoice for a due recurring template', function () {
    $template = recurringInvoice();

    $this->artisan('invoices:generate-recurring')->assertExitCode(0);

    $child = Invoice::where('recurrence_parent_id', $template->id)->first();

    expect($child)->not->toBeNull()
        ->and($child->status)->toBe('draft')
        ->and($child->total)->toBe(50000)
        ->and($child->items)->toHaveCount(1)
        ->and($template->fresh()->next_recurrence_on->isFuture())->toBeTrue()
        ->and($template->fresh()->last_generated_at)->not->toBeNull();
});

it('does not generate recurring invoices that are not due', function () {
    recurringInvoice(['next_recurrence_on' => now()->addMonth()->toDateString()]);

    $this->artisan('invoices:generate-recurring')->assertExitCode(0);

    expect(Invoice::count())->toBe(1);
});

it('does not generate for cancelled recurring templates', function () {
    $template = recurringInvoice(['status' => 'cancelled', 'cancelled_at' => now()]);

    $this->artisan('invoices:generate-recurring')->assertExitCode(0);

    expect(Invoice::where('recurrence_parent_id', $template->id)->count())->toBe(0);
});

it('creates a recurring template via the invoice form', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/invoices', [
        'customer_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'is_recurring' => true,
        'recurrence_interval' => 'monthly',
        'items' => [['description' => 'Retainer', 'quantity' => 1, 'unit_price' => '500', 'tax_rate' => 0]],
    ])->assertRedirect();

    $invoice = Invoice::first();

    expect($invoice->is_recurring)->toBeTrue()
        ->and($invoice->recurrence_interval)->toBe('monthly')
        ->and($invoice->next_recurrence_on)->not->toBeNull();
});
