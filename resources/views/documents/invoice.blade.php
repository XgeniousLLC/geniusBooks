<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { color: #1e293b; font-size: 12px; margin: 0; }
        .wrap { padding: 40px; }
        .header { width: 100%; border-bottom: 2px solid #4f46e5; padding-bottom: 16px; margin-bottom: 24px; }
        .header td { vertical-align: top; }
        .company-name { font-size: 20px; font-weight: bold; color: #1e293b; }
        .muted { color: #64748b; }
        .invoice-title { font-size: 26px; font-weight: bold; color: #4f46e5; text-align: right; letter-spacing: 2px; }
        .meta { text-align: right; font-size: 12px; }
        .section { margin-bottom: 24px; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th { text-align: left; background: #f1f5f9; color: #475569; font-size: 11px; padding: 8px; border-bottom: 1px solid #e2e8f0; }
        table.items td { padding: 8px; border-bottom: 1px solid #f1f5f9; }
        .right { text-align: right; }
        table.totals { width: 240px; margin-left: auto; margin-top: 16px; border-collapse: collapse; }
        table.totals td { padding: 6px 8px; }
        table.totals .grand td { border-top: 2px solid #e2e8f0; font-weight: bold; font-size: 14px; }
        .box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 6px; }
        .footer { margin-top: 32px; border-top: 1px solid #e2e8f0; padding-top: 12px; color: #64748b; font-size: 11px; }
    </style>
</head>
<body>
<div class="wrap">
    <table class="header">
        <tr>
            <td width="60%">
                @if($logo)
                    <img src="{{ $logo }}" alt="Logo" style="max-height: 60px; margin-bottom: 8px;"><br>
                @endif
                <div class="company-name">{{ $company->name }}</div>
                <div class="muted">
                    @if($company->address){{ $company->address }}<br>@endif
                    @if($company->email){{ $company->email }}<br>@endif
                    @if($company->phone){{ $company->phone }}<br>@endif
                    @if($company->tax_registration_number)Tax ID: {{ $company->tax_registration_number }}@endif
                </div>
            </td>
            <td width="40%">
                <div class="invoice-title">INVOICE</div>
                <div class="meta">
                    <strong>{{ $invoice->number }}</strong><br>
                    Issued: {{ $invoice->issue_date->toFormattedDateString() }}<br>
                    Due: {{ $invoice->due_date->toFormattedDateString() }}
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <span class="label">Bill to</span><br>
        <strong>{{ $customer->name }}</strong>
        @if($customer->company_name)<br>{{ $customer->company_name }}@endif
        @if($customer->email)<br>{{ $customer->email }}@endif
        @if($customer->billing_address)<br>{{ $customer->billing_address }}@endif
    </div>

    <table class="items">
        <thead>
            <tr>
                <th width="45%">Description</th>
                <th class="right" width="12%">Qty</th>
                <th class="right" width="18%">Unit price</th>
                <th class="right" width="10%">Tax</th>
                <th class="right" width="15%">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $item->quantity, 4, '.', ''), '0'), '.') }}</td>
                    <td class="right">{{ $money((int) $item->unit_price) }}</td>
                    <td class="right">{{ $item->tax_rate !== null ? rtrim(rtrim(number_format((float) $item->tax_rate, 2, '.', ''), '0'), '.').'%' : '—' }}</td>
                    <td class="right">{{ $money((int) $item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ $money((int) $invoice->subtotal) }}</td></tr>
        <tr><td>Discount</td><td class="right">-{{ $money((int) $invoice->discount_total) }}</td></tr>
        <tr><td>{{ $invoice->tax_inclusive ? 'Tax (included)' : 'Tax' }}</td><td class="right">{{ $money((int) $invoice->tax_total) }}</td></tr>
        <tr class="grand"><td>Total</td><td class="right">{{ $money((int) $invoice->total) }}</td></tr>
        <tr><td>Paid</td><td class="right">{{ $money((int) $invoice->amount_paid) }}</td></tr>
        <tr class="grand"><td>Balance due</td><td class="right">{{ $money($invoice->balance()) }}</td></tr>
    </table>

    @if($company->payment_instructions)
        <div class="section" style="margin-top: 24px;">
            <span class="label">Payment instructions</span><br>
            <div class="box">{!! nl2br(e($company->payment_instructions)) !!}</div>
        </div>
    @endif

    @if($invoice->notes)
        <div class="section">
            <span class="label">Notes</span><br>
            {!! nl2br(e($invoice->notes)) !!}
        </div>
    @endif

    <div class="footer">
        @if($invoice->terms){{ $invoice->terms }}<br>@endif
        @if($company->invoice_footer){{ $company->invoice_footer }}@endif
    </div>
</div>
</body>
</html>
