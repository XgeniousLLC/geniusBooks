<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        * { font-family: DejaVu Sans, Arial, sans-serif; }
        body { color: #1e293b; font-size: 12px; margin: 0; }
        .wrap { padding: 40px; }
        h1 { font-size: 20px; color: #4f46e5; margin: 0 0 4px; }
        .period { color: #64748b; margin-bottom: 20px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .summary td { padding: 8px 10px; border: 1px solid #e2e8f0; }
        .summary td.value { text-align: right; font-weight: bold; }
        table.section { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table.section th { text-align: left; background: #f1f5f9; padding: 8px; border-bottom: 1px solid #e2e8f0; font-size: 11px; color: #475569; }
        table.section td { padding: 8px; border-bottom: 1px solid #f1f5f9; }
        .right { text-align: right; }
        tr.total td { font-weight: bold; border-top: 2px solid #e2e8f0; }
        .muted { color: #94a3b8; font-size: 11px; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>{{ $report['title'] }}</h1>
    <div class="period">{{ $report['period']['from'] }} – {{ $report['period']['to'] }}</div>

    <table class="summary">
        @foreach($report['summary'] as $stat)
            <tr>
                <td>{{ $stat['label'] }}</td>
                <td class="value">{{ $stat['display'] }}</td>
            </tr>
        @endforeach
    </table>

    @foreach($report['sections'] as $section)
        <table class="section">
            <thead>
                <tr><th colspan="2">{{ $section['heading'] }}</th></tr>
            </thead>
            <tbody>
                @forelse($section['rows'] as $row)
                    <tr>
                        <td>{{ $row['label'] }}@if(!empty($row['meta'])) <span class="muted">· {{ $row['meta'] }}</span>@endif</td>
                        <td class="right">{{ $row['display'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">No data for this period.</td></tr>
                @endforelse
                <tr class="total">
                    <td>Total</td>
                    <td class="right">{{ $section['total_display'] }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach
</div>
</body>
</html>
