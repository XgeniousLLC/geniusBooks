<?php

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;

function invoiceLine(array $overrides = []): array
{
    return array_merge(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => '2500', 'tax_rate' => 10], $overrides);
}

it('creates a draft invoice with computed totals and a number', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/invoices', [
        'customer_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(15)->toDateString(),
        'items' => [
            ['description' => 'Website Development', 'quantity' => 1, 'unit_price' => '2500', 'tax_rate' => 10],
            ['description' => 'Hosting', 'quantity' => 1, 'unit_price' => '100', 'tax_rate' => 10],
        ],
    ])->assertRedirect();

    $invoice = Invoice::first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->number)->toBe('INV-0001')
        ->and($invoice->status)->toBe(InvoiceStatus::Draft->value)
        ->and($invoice->subtotal)->toBe(260000)
        ->and($invoice->tax_total)->toBe(26000)
        ->and($invoice->total)->toBe(286000)
        ->and($invoice->items)->toHaveCount(2);
});

it('validates invoice input', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/invoices', [
        'customer_id' => 999999,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->subDay()->toDateString(),
        'items' => [],
    ])->assertSessionHasErrors(['customer_id', 'due_date', 'items']);
});

it('updates a draft invoice and recomputes totals', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = Invoice::factory()->for($company)->create(['total' => 0]);
    $customer = $invoice->customer;

    actingAsCompany($user, $company);

    $this->put("/portal/invoices/{$invoice->id}", [
        'customer_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(10)->toDateString(),
        'items' => [invoiceLine(['unit_price' => '100', 'tax_rate' => 0])],
    ])->assertRedirect(route('portal.invoices.show', $invoice));

    expect($invoice->fresh()->total)->toBe(10000)
        ->and($invoice->fresh()->items)->toHaveCount(1);
});

it('prevents editing a sent invoice', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = Invoice::factory()->for($company)->sent()->create();
    $customer = $invoice->customer;

    actingAsCompany($user, $company);

    $this->get("/portal/invoices/{$invoice->id}/edit")
        ->assertRedirect(route('portal.invoices.show', $invoice))
        ->assertSessionHas('error');

    $this->put("/portal/invoices/{$invoice->id}", [
        'customer_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(10)->toDateString(),
        'items' => [invoiceLine()],
    ])->assertForbidden();
});

it('deletes drafts but not issued invoices', function () {
    [$user, $company] = userWithCompany('owner');
    $draft = Invoice::factory()->for($company)->create();
    $sent = Invoice::factory()->for($company)->sent()->create();

    actingAsCompany($user, $company);

    $this->delete("/portal/invoices/{$draft->id}")->assertRedirect(route('portal.invoices.index'));
    $this->assertDatabaseMissing('invoices', ['id' => $draft->id]);

    $this->delete("/portal/invoices/{$sent->id}")->assertForbidden();
    $this->assertDatabaseHas('invoices', ['id' => $sent->id]);
});

it('marks a draft as sent', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = Invoice::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/send")->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent->value)
        ->and($invoice->fresh()->sent_at)->not->toBeNull();
});

it('cancels an invoice', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = Invoice::factory()->for($company)->sent()->create();

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/cancel", ['cancel_reason' => 'Duplicate'])->assertRedirect();

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled->value)
        ->and($invoice->fresh()->cancel_reason)->toBe('Duplicate');
});

it('duplicates an invoice as a new draft', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = Invoice::factory()->for($company)->sent()->create(['subtotal' => 2600, 'total' => 2860]);
    $invoice->items()->create(['description' => 'Consulting', 'quantity' => 1, 'unit_price' => 2500, 'position' => 0]);

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/duplicate")->assertRedirect();

    $copy = Invoice::where('id', '!=', $invoice->id)->first();

    expect($copy)->not->toBeNull()
        ->and($copy->status)->toBe(InvoiceStatus::Draft->value)
        ->and($copy->number)->not->toBe($invoice->number)
        ->and($copy->amount_paid)->toBe(0)
        ->and($copy->items)->toHaveCount(1);
});

it('filters invoices by status and customer search', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create(['name' => 'Acme Consulting']);
    Invoice::factory()->for($company)->for($customer)->sent()->create(['number' => 'INV-1001']);

    $other = Customer::factory()->for($company)->create(['name' => 'Globex']);
    Invoice::factory()->for($company)->for($other)->create(['number' => 'INV-1002']);

    actingAsCompany($user, $company);

    $this->get('/portal/invoices?search=Acme')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->where('invoices.data', fn ($data) => count($data) === 1));

    $this->get('/portal/invoices?status=draft')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('invoices.data.0.number', 'INV-1002'));
});

it('shows an invoice with formatted totals', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = Invoice::factory()->for($company)->create(['total' => 286000, 'subtotal' => 260000, 'tax_total' => 26000]);

    actingAsCompany($user, $company);

    $this->get("/portal/invoices/{$invoice->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Invoices/Show')
            ->where('invoice.formatted.total', '$2,860.00'));
});
