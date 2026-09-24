<?php

use App\Enums\QuoteStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;

function quoteLine(array $overrides = []): array
{
    return array_merge(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => '2500', 'tax_rate' => 10], $overrides);
}

it('creates a quote with computed totals and a number', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/quotes', [
        'customer_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'items' => [quoteLine()],
    ])->assertRedirect();

    $quote = Quote::first();

    expect($quote->number)->toBe('QT-0001')
        ->and($quote->status)->toBe(QuoteStatus::Draft->value)
        ->and($quote->subtotal)->toBe(250000)
        ->and($quote->tax_total)->toBe(25000)
        ->and($quote->total)->toBe(275000)
        ->and($quote->items)->toHaveCount(1);
});

it('validates quote input', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/quotes', [
        'customer_id' => 999999,
        'items' => [],
    ])->assertSessionHasErrors(['customer_id', 'issue_date', 'items']);
});

it('converts a quote into a draft invoice', function () {
    [$user, $company] = userWithCompany('owner');
    $quote = Quote::factory()->for($company)->accepted()->create(['subtotal' => 250000, 'total' => 275000]);
    $quote->items()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 250000, 'tax_rate' => 10, 'position' => 0]);

    actingAsCompany($user, $company);

    $this->post("/portal/quotes/{$quote->id}/convert")->assertRedirect();

    $invoice = Invoice::first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->total)->toBe(275000)
        ->and($invoice->items)->toHaveCount(1)
        ->and($quote->fresh()->status)->toBe(QuoteStatus::Converted->value)
        ->and($quote->fresh()->converted_invoice_id)->toBe($invoice->id);
});

it('tracks the quote status lifecycle', function () {
    [$user, $company] = userWithCompany('owner');
    $quote = Quote::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post("/portal/quotes/{$quote->id}/send")->assertRedirect();
    expect($quote->fresh()->status)->toBe(QuoteStatus::Sent->value);

    $this->post("/portal/quotes/{$quote->id}/accept")->assertRedirect();
    expect($quote->fresh()->status)->toBe(QuoteStatus::Accepted->value);
});

it('prevents editing an accepted quote', function () {
    [$user, $company] = userWithCompany('owner');
    $quote = Quote::factory()->for($company)->accepted()->create();
    $customer = $quote->customer;

    actingAsCompany($user, $company);

    $this->get("/portal/quotes/{$quote->id}/edit")
        ->assertRedirect(route('portal.quotes.show', $quote))
        ->assertSessionHas('error');

    $this->put("/portal/quotes/{$quote->id}", [
        'customer_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'items' => [quoteLine()],
    ])->assertForbidden();
});

it('filters quotes by status', function () {
    [$user, $company] = userWithCompany('owner');
    Quote::factory()->for($company)->create(['number' => 'QT-1001']);
    Quote::factory()->for($company)->sent()->create(['number' => 'QT-1002']);

    actingAsCompany($user, $company);

    $this->get('/portal/quotes?status=sent')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Quotes/Index')->where('quotes.data.0.number', 'QT-1002'));
});
