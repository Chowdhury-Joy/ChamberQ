<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Medicine voucher') }} {{ $sale->receiptLabel() }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --ink: {{ $ink }}; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: #e8eef4;
            font-family: 'Hind Siliguri', Arial, sans-serif;
        }
        .screen-bar {
            width: 210mm;
            max-width: calc(100% - 32px);
            margin: 16px auto 0;
            display: flex;
            justify-content: flex-end;
        }
        .screen-bar button {
            font: inherit;
            padding: 8px 14px;
            border: 1px solid var(--ink);
            background: var(--ink);
            color: #fff;
            cursor: pointer;
        }
        /* Checkered pad border: small navy/white squares around the whole A4 page. */
        .sheet {
            width: 210mm;
            height: 297mm;
            margin: 10px auto 32px;
            padding: 6px;
            background:
                repeating-conic-gradient(var(--ink) 0% 25%, #fff 0% 50%)
                0 0 / 8px 8px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .voucher {
            background: #fff;
            height: calc(297mm - 12px);
            padding: 10mm 12mm 8mm;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .head {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px 20px;
            align-items: center;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--ink);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .clinic-logo {
            height: 52px;
            width: auto;
            max-width: 240px;
            object-fit: contain;
            display: block;
            background: transparent;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .clinic-en {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
        }
        .badge {
            border: 1.5px solid var(--ink);
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .contact {
            text-align: right;
            font-size: 12px;
            line-height: 1.4;
            max-width: 230px;
        }
        .contact p { margin: 0; }
        .meta {
            display: grid;
            grid-template-columns: 0.7fr 1.6fr 0.8fr;
            gap: 12px;
            margin: 12px 0;
            font-size: 13px;
        }
        .meta .label { font-weight: 700; }
        .meta .value {
            display: block;
            min-height: 1.3em;
            margin-top: 2px;
            border-bottom: 1px dotted var(--ink);
            font-weight: 600;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            flex: 1;
        }
        table.lines th {
            background: var(--ink);
            color: #fff;
            font-weight: 700;
            padding: 6px 8px;
            text-align: left;
        }
        table.lines th.num, table.lines td.num { text-align: center; width: 2.4rem; }
        table.lines th.qty, table.lines td.qty { text-align: center; width: 3.6rem; }
        table.lines th.money, table.lines td.money { text-align: right; width: 6rem; }
        table.lines td {
            border-bottom: 1px dotted color-mix(in srgb, var(--ink) 35%, #fff);
            padding: 5px 8px;
            height: 26px;
            vertical-align: middle;
        }
        table.lines td.money.empty {
            background-image: linear-gradient(to top right, transparent calc(50% - 0.5px), color-mix(in srgb, var(--ink) 28%, #fff), transparent calc(50% + 0.5px));
        }
        .foot {
            display: grid;
            grid-template-columns: 1.2fr 0.9fr;
            gap: 16px;
            margin-top: 12px;
            align-items: start;
        }
        .pay-row {
            display: flex;
            gap: 16px;
            font-weight: 700;
            font-size: 13px;
            margin-bottom: 10px;
        }
        .box {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .box i {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 1.5px solid var(--ink);
            position: relative;
            flex: 0 0 auto;
            background: #fff;
        }
        .box.is-on i::after {
            content: "";
            position: absolute;
            left: 3px;
            top: 0;
            width: 4px;
            height: 8px;
            border: solid var(--ink);
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .dotted {
            display: flex;
            gap: 8px;
            align-items: baseline;
            font-size: 13px;
        }
        .dotted .label { font-weight: 700; white-space: nowrap; }
        .dotted .line {
            flex: 1;
            border-bottom: 1px dotted var(--ink);
            min-height: 1.25em;
        }
        .totals {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .totals th, .totals td {
            border: 1px solid var(--ink);
            padding: 6px 10px;
        }
        .totals th {
            text-align: left;
            font-weight: 700;
            width: 58%;
            background: color-mix(in srgb, var(--ink) 8%, #fff);
        }
        .totals td { text-align: right; font-weight: 700; }
        .sign {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 12px;
            align-items: end;
            margin-top: 18px;
        }
        .sign .slot { text-align: center; }
        .sign .who { min-height: 1.5em; font-weight: 700; font-size: 13px; }
        .sign .caption {
            border-top: 1px solid var(--ink);
            padding-top: 4px;
            font-weight: 700;
            font-size: 12px;
        }
        .thanks {
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            padding-bottom: 6px;
        }
        .voided { position: relative; }
        .voided::after {
            content: "{{ __('RETURNED') }}";
            position: absolute;
            inset: 38% 8%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            font-weight: 700;
            color: #b91c1c;
            opacity: 0.28;
            transform: rotate(-18deg);
            pointer-events: none;
        }
        @media print {
            html, body { height: 297mm; overflow: hidden; background: #fff; }
            .screen-bar { display: none; }
            .sheet {
                margin: 0;
                width: 210mm;
                height: 297mm;
                max-height: 297mm;
                overflow: hidden;
                page-break-inside: avoid;
                break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .voucher { height: calc(297mm - 12px); overflow: hidden; }
            .clinic-logo {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>
    <div class="screen-bar">
        <button type="button" onclick="window.print()">{{ __('Print') }}</button>
    </div>
    <div class="sheet">
        <div class="voucher @if($sale->isVoided()) voided @endif">
            <div class="head">
                <div class="brand">
                    @if($logoUrl)
                        <img class="clinic-logo" src="{{ $logoUrl }}" alt="">
                    @else
                        <p class="clinic-en">{{ $clinicName }}</p>
                    @endif
                    <span class="badge">{{ __('Medicine voucher') }}</span>
                </div>
                <div class="contact">
                    @if($address)
                        <p>{{ $address }}</p>
                    @endif
                    @foreach($phones as $phone)
                        <p>{{ $phone }}</p>
                    @endforeach
                </div>
            </div>

            <div class="meta">
                <div>
                    <span class="label">{{ __('Sl No') }}</span>
                    <span class="value">{{ $sale->receiptLabel() }}</span>
                </div>
                <div>
                    <span class="label">{{ __('Customer Name (Mr/Mrs)') }}</span>
                    <span class="value">{{ $sale->patient_name ?: '' }}</span>
                </div>
                <div>
                    <span class="label">{{ __('Date') }}</span>
                    <span class="value">{{ $sale->occurred_on?->format('d-m-y') }}</span>
                </div>
            </div>

            <table class="lines">
                <thead>
                    <tr>
                        <th class="num">{{ __('Sl') }}</th>
                        <th>{{ __('Medicine Name') }}</th>
                        <th class="qty">{{ __('Qty') }}</th>
                        <th class="money">{{ __('Rate') }}</th>
                        <th class="money">{{ __('Amount') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lines as $i => $line)
                        <tr>
                            <td class="num">{{ $line ? $i + 1 : '' }}</td>
                            <td>{{ $line?->name }}</td>
                            <td class="qty">{{ $line ? str_pad((string) $line->qty, 2, '0', STR_PAD_LEFT) : '' }}</td>
                            <td class="money {{ $line ? '' : 'empty' }}">{{ $line ? \App\Services\PharmacyInvoiceService::formatTaka((int) $line->sell_price_taka) : '' }}</td>
                            <td class="money {{ $line ? '' : 'empty' }}">{{ $line ? \App\Services\PharmacyInvoiceService::formatTaka((int) $line->line_total_taka) : '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="foot">
                <div>
                    @php
                        $ticked = \App\Services\PharmacyInvoiceService::tickedVoucherPayments($sale);
                    @endphp
                    <div class="pay-row">
                        <span class="box {{ in_array(\App\Models\ChamberCashEntry::METHOD_CASH, $ticked, true) ? 'is-on' : '' }}"><i></i> {{ __('Cash') }}</span>
                        <span class="box {{ in_array(\App\Services\PharmacyInvoiceService::VOUCHER_ONLINE, $ticked, true) ? 'is-on' : '' }}"><i></i> {{ __('Online') }}</span>
                    </div>
                    <div class="dotted">
                        <span class="label">{{ __('In Word') }} :</span>
                        <span class="line">{{ $amountInWords }}</span>
                    </div>
                </div>
                <table class="totals">
                    <tr>
                        <th>{{ __('Total Amount') }}</th>
                        <td>{{ \App\Services\PharmacyInvoiceService::formatTaka($catalogueTotal) }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Discount') }}</th>
                        <td>{{ $discount > 0 ? \App\Services\PharmacyInvoiceService::formatTaka($discount) : '' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('Net Payable') }}</th>
                        <td>{{ \App\Services\PharmacyInvoiceService::formatTaka($netPayable) }}</td>
                    </tr>
                </table>
            </div>

            <div class="sign">
                <div class="slot">
                    <div class="who">{{ $receivedBy }}</div>
                    <div class="caption">{{ __('Received By') }}</div>
                </div>
                <div class="thanks">{{ __('Thank You.') }}</div>
                <div class="slot">
                    <div class="who">&nbsp;</div>
                    <div class="caption">{{ __('Customer Signature') }}</div>
                </div>
            </div>
        </div>
    </div>
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</body>
</html>
