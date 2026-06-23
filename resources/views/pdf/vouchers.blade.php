<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; background: #fff; }

    .page { padding: 10mm; }

    .grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 4mm;
    }

    /* DomPDF doesn't support CSS grid — use table layout */
    table.grid-table { width: 100%; border-collapse: separate; border-spacing: 3mm; }
    table.grid-table td { width: 33.33%; vertical-align: top; }

    .voucher {
        border: 1.5px dashed #555;
        border-radius: 6px;
        padding: 5mm 4mm;
        page-break-inside: avoid;
        background: #fff;
        position: relative;
        overflow: hidden;
    }

    .voucher::before {
        content: "";
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 5px;
        background: repeating-linear-gradient(90deg, #4f46e5 0, #4f46e5 8px, transparent 8px, transparent 14px);
    }

    .header {
        text-align: center;
        border-bottom: 1px solid #ddd;
        padding-bottom: 3mm;
        margin-bottom: 3mm;
        margin-top: 3mm;
    }

    .business { font-size: 9px; color: #555; text-transform: uppercase; letter-spacing: 0.5px; }
    .plan-name { font-size: 13px; font-weight: bold; color: #1a1a1a; margin: 1mm 0; }
    .duration { font-size: 9px; color: #777; }

    .code-box {
        background: #f3f4f6;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        text-align: center;
        padding: 3mm 2mm;
        margin: 3mm 0;
    }
    .code-label { font-size: 7px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; }
    .code { font-size: 18px; font-weight: bold; color: #4f46e5; letter-spacing: 2px; font-family: "Courier New", monospace; }

    .details { font-size: 8px; color: #555; margin-top: 2mm; }
    .details table { width: 100%; }
    .details td.label { color: #9ca3af; width: 45%; }
    .details td.value { font-weight: bold; color: #374151; }

    .footer {
        border-top: 1px solid #e5e7eb;
        margin-top: 3mm;
        padding-top: 2mm;
        font-size: 7px;
        color: #9ca3af;
        text-align: center;
    }

    .price-badge {
        display: inline-block;
        background: #4f46e5;
        color: #fff;
        border-radius: 10px;
        padding: 1px 6px;
        font-size: 9px;
        font-weight: bold;
        margin-top: 1mm;
    }
</style>
</head>
<body>
<div class="page">
    @php $perRow = 3; $i = 0; @endphp
    <table class="grid-table">
        @foreach($vouchers->chunk($perRow) as $row)
        <tr>
            @foreach($row as $voucher)
            <td>
                <div class="voucher">
                    <div class="header">
                        <div class="business">{{ config('app.business_name', config('app.name')) }}</div>
                        <div class="plan-name">{{ $voucher->plan->name }}</div>
                        <div class="duration">{{ $voucher->plan->duration_label }} &bull; ↓{{ $voucher->plan->bandwidth_down }}</div>
                        <span class="price-badge">KES {{ number_format($voucher->plan->price, 0) }}</span>
                    </div>

                    <div class="code-box">
                        <div class="code-label">Voucher Code</div>
                        <div class="code">{{ $voucher->code }}</div>
                    </div>

                    <div class="details">
                        <table>
                            <tr>
                                <td class="label">Username:</td>
                                <td class="value">{{ $voucher->code }}</td>
                            </tr>
                            <tr>
                                <td class="label">Password:</td>
                                <td class="value">{{ $voucher->code }}</td>
                            </tr>
                            <tr>
                                <td class="label">Status:</td>
                                <td class="value" style="color: {{ $voucher->status === 'unused' ? '#059669' : '#6b7280' }}">
                                    {{ strtoupper($voucher->status) }}
                                </td>
                            </tr>
                            @if($voucher->expires_at)
                            <tr>
                                <td class="label">Expires:</td>
                                <td class="value">{{ $voucher->expires_at->format('d M Y H:i') }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>

                    <div class="footer">
                        Connect to WiFi &bull; Open browser &bull; Enter code
                        @if(config('app.business_phone'))
                        <br>Help: {{ config('app.business_phone') }}
                        @endif
                    </div>
                </div>
            </td>
            @endforeach
            @if($row->count() < $perRow)
                @for($j = $row->count(); $j < $perRow; $j++)
                <td></td>
                @endfor
            @endif
        </tr>
        @endforeach
    </table>
</div>
</body>
</html>
