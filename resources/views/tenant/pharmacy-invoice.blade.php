<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Medicine voucher') }} {{ $sale->receiptLabel() }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Hind+Siliguri:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: {{ $ink }};
            --ink-deep: color-mix(in srgb, {{ $ink }} 82%, #020617);
            --paper: #fbfaf6;
            --rule: color-mix(in srgb, {{ $ink }} 18%, #e7e2d6);
            --gold: #b8893a;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #1c1914;
            background: #d7d2c8;
            font-family: 'Hind Siliguri', 'Noto Sans Bengali', Arial, sans-serif;
        }
        .screen-bar {
            width: 210mm;
            max-width: calc(100% - 32px);
            margin: 18px auto 0;
            display: flex;
            justify-content: flex-end;
        }
        .screen-bar button {
            font: inherit;
            padding: 9px 18px;
            border: 0;
            background: var(--ink);
            color: #fff;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 700;
        }
        /* Checkered pad border: small navy/white squares around the whole A4 page. */
        .sheet {
            width: 210mm;
            min-height: 297mm;
            margin: 12px auto 36px;
            padding: 8px;
            box-sizing: border-box;
            background:
                repeating-conic-gradient(var(--ink) 0% 25%, #fff 0% 50%)
                0 0 / 8px 8px;
            box-shadow: 0 18px 50px rgba(15, 35, 70, 0.18);
        }
        .voucher {
            background: var(--paper);
            min-height: calc(297mm - 16px);
            padding: 0 0 14mm;
            display: flex;
            flex-direction: column;
        }
        .head {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px 28px;
            align-items: center;
            padding: 14mm 15mm 12mm;
            background: color-mix(in srgb, var(--ink) 9%, #f4f8fd);
            border-bottom: 2px solid var(--ink);
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }
        .clinic-logo {
            height: 68px;
            width: auto;
            max-width: 220px;
            object-fit: contain;
            display: block;
            flex: 0 0 auto;
            background: #020617;
            padding: 6px 8px;
            border-radius: 8px;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .brand:has(.clinic-logo) .clinic-en { font-size: 22px; }
        .kicker {
            margin: 0 0 6px;
            font-size: 11px;
            letter-spacing: 0.22em;
            text-transform: uppercase;
            color: var(--gold);
            font-weight: 700;
        }
        .clinic-en {
            margin: 0;
            font-family: Fraunces, Georgia, serif;
            font-size: 28px;
            font-weight: 700;
            line-height: 1.15;
            color: var(--ink-deep);
        }
        .badge {
            display: inline-block;
            margin-top: 10px;
            border: 1.5px solid var(--gold);
            color: var(--ink-deep);
            border-radius: 999px;
            padding: 5px 16px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            background: #fff8ea;
        }
        .contact {
            text-align: right;
            font-size: 12.5px;
            line-height: 1.55;
            max-width: 230px;
            color: #3f3a33;
        }
        .contact .addr { margin: 0 0 8px; font-weight: 600; }
        .contact .tel { margin: 0; }
        .meta {
            display: grid;
            grid-template-columns: 1fr 1.6fr 1fr;
            gap: 12px;
            margin: 18px 15mm 16px;
        }
        .meta .field {
            background: #fff;
            border: 1px solid var(--rule);
            border-radius: 10px;
            padding: 10px 12px 8px;
        }
        .meta .label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #6b6458;
            margin-bottom: 4px;
        }
        .meta .value {
            display: block;
            min-height: 1.3em;
            font-size: 16px;
            font-weight: 700;
            color: var(--ink-deep);
        }
        table.lines {
            width: calc(100% - 30mm);
            margin: 0 15mm;
            border-collapse: collapse;
            font-size: 14px;
            flex: 1;
            background: #fff;
        }
        table.lines th {
            background: var(--ink);
            color: #fff;
            font-weight: 700;
            padding: 10px 12px;
            text-align: left;
        }
        table.lines th:first-child { border-radius: 10px 0 0 0; }
        table.lines th:last-child { border-radius: 0 10px 0 0; }
        table.lines th.num, table.lines td.num { text-align: center; width: 2.6rem; }
        table.lines th.qty, table.lines td.qty { text-align: center; width: 4rem; }
        table.lines th.money, table.lines td.money { text-align: right; width: 6.5rem; }
        table.lines td {
            border-bottom: 1px solid var(--rule);
            padding: 11px 12px;
            height: 38px;
            vertical-align: middle;
        }
        table.lines tbody tr:nth-child(even) td { background: #f6f3ec; }
        table.lines td.money.empty {
            background-image: linear-gradient(to top right, transparent calc(50% - 0.6px), #c4b8a4, transparent calc(50% + 0.6px));
        }
        .foot {
            display: grid;
            grid-template-columns: 1.3fr 0.9fr;
            gap: 18px 22px;
            margin: 18px 15mm 0;
            align-items: stretch;
        }
        .pay {
            background: #fff;
            border: 1px solid var(--rule);
            border-radius: 12px;
            padding: 14px 16px 12px;
        }
        .pay-title {
            margin: 0 0 10px;
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            font-weight: 800;
            color: var(--gold);
        }
        .pay-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 10px;
            margin: 0 0 14px;
            font-weight: 700;
        }
        .box {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 6px 10px;
            border: 1px solid var(--rule);
            border-radius: 999px;
            font-size: 13px;
        }
        .box.is-on {
            border-color: var(--ink);
            background: color-mix(in srgb, var(--ink) 8%, #fff);
        }
        .box i {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1.5px solid var(--ink);
            border-radius: 3px;
            position: relative;
            flex: 0 0 auto;
            background: #fff;
        }
        .box.is-on i {
            background: var(--ink);
        }
        .box.is-on i::after {
            content: "";
            position: absolute;
            left: 3px;
            top: 0;
            width: 4px;
            height: 8px;
            border: solid #fff;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .dotted {
            display: flex;
            gap: 8px;
            align-items: baseline;
            margin: 8px 0 0;
            font-size: 13px;
        }
        .dotted .label { font-weight: 800; white-space: nowrap; color: #4a453d; }
        .dotted .line {
            flex: 1;
            border-bottom: 1px dotted #8a8174;
            min-height: 1.25em;
            font-weight: 600;
        }
        .totals {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: #fff;
            overflow: hidden;
            border-radius: 12px;
        }
        .totals th, .totals td {
            border: 1px solid var(--rule);
            padding: 11px 14px;
        }
        .totals th {
            text-align: left;
            font-weight: 700;
            background: #f6f3ec;
            width: 58%;
        }
        .totals td { text-align: right; font-weight: 700; }
        .totals tr:last-child th,
        .totals tr:last-child td {
            background: var(--ink);
            color: #fff;
            border-color: var(--ink);
            font-size: 15px;
        }
        .sign {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 16px;
            align-items: end;
            margin: 28px 15mm 0;
            padding-top: 8px;
        }
        .sign .slot { text-align: center; }
        .sign .who {
            min-height: 2.2em;
            font-weight: 700;
            font-size: 14px;
        }
        .sign .caption {
            border-top: 1.5px solid var(--ink);
            padding-top: 6px;
            font-weight: 800;
            font-size: 12px;
            letter-spacing: 0.04em;
        }
        .thanks {
            text-align: center;
            font-family: Fraunces, Georgia, serif;
            font-weight: 700;
            font-size: 16px;
            color: var(--ink-deep);
            padding-bottom: 10px;
        }
        .voided { position: relative; }
        .voided::after {
            content: "{{ __('RETURNED') }}";
            position: absolute;
            inset: 38% 8%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 52px;
            font-weight: 800;
            color: #b91c1c;
            opacity: 0.28;
            transform: rotate(-18deg);
            pointer-events: none;
            letter-spacing: 0.14em;
        }
        @media print {
            body { background: #fff; }
            .screen-bar { display: none; }
            .sheet {
                margin: 0;
                width: auto;
                min-height: 0;
                box-shadow: none;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .voucher { min-height: 0; }
            .clinic-logo {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page { size: A4; margin: 8mm; }
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
                        <img class="clinic-logo" src="{{ $logoUrl }}" alt="{{ $clinicName }}">
                    @endif
                    <div>
                        <p class="kicker">{{ $thankYouBrand }}</p>
                        <p class="clinic-en">{{ $clinicName }}</p>
                        <span class="badge">{{ __('Medicine voucher') }}</span>
                    </div>
                </div>
                <div class="contact">
                    @if($address)
                        <p class="addr">{{ $address }}</p>
                    @endif
                    @foreach($phones as $phone)
                        <p class="tel">{{ $phone }}</p>
                    @endforeach
                </div>
            </div>

            <div class="meta">
                <div class="field">
                    <span class="label">{{ __('Sl No') }}</span>
                    <span class="value">{{ $sale->receiptLabel() }}</span>
                </div>
                <div class="field">
                    <span class="label">{{ __('Customer Name (Mr/Mrs)') }}</span>
                    <span class="value">{{ $sale->patient_name ?: '' }}</span>
                </div>
                <div class="field">
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
                <div class="pay">
                    @php
                        $ticked = \App\Services\PharmacyInvoiceService::tickedMethods($sale);
                    @endphp
                    <p class="pay-title">{{ __('Paid how') }}</p>
                    <div class="pay-row">
                        <span class="box {{ in_array(\App\Models\ChamberCashEntry::METHOD_CASH, $ticked, true) ? 'is-on' : '' }}"><i></i> {{ __('Cash') }}</span>
                        <span class="box {{ in_array(\App\Models\ChamberCashEntry::METHOD_CARD, $ticked, true) ? 'is-on' : '' }}"><i></i> {{ __('Card') }}</span>
                        <span class="box {{ in_array(\App\Models\ChamberCashEntry::METHOD_BKASH, $ticked, true) ? 'is-on' : '' }}"><i></i> {{ __('Bkash') }}</span>
                        <span class="box {{ in_array(\App\Models\ChamberCashEntry::METHOD_NAGAD, $ticked, true) ? 'is-on' : '' }}"><i></i> {{ __('Nagad') }}</span>
                    </div>
                    <div class="dotted">
                        <span class="label">{{ __('Txn ID') }} :</span>
                        <span class="line"></span>
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
                <div class="thanks">{{ __('Thank You.') }} {{ $thankYouBrand }}</div>
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
