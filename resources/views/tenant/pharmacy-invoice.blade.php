<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Medicine voucher') }} {{ $sale->receiptLabel() }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink: {{ $ink }}; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: #e8eef4;
            font-family: 'Hind Siliguri', 'Noto Sans Bengali', Arial, sans-serif;
        }
        .screen-bar {
            max-width: 210mm;
            margin: 16px auto 0;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        .screen-bar button {
            font: inherit;
            padding: 8px 16px;
            border: 1px solid #94a3b8;
            background: #fff;
            border-radius: 8px;
            cursor: pointer;
        }
        .sheet {
            width: 210mm;
            min-height: 148mm;
            margin: 12px auto 32px;
            padding: 7px;
            background:
                repeating-conic-gradient(var(--ink) 0% 25%, #fff 0% 50%)
                0 0 / 8px 8px;
        }
        .voucher {
            min-height: calc(148mm - 14px);
            background: #fff;
            padding: 10px 14px 12px;
            display: flex;
            flex-direction: column;
        }
        .head {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 8px 12px;
            align-items: start;
            background: #e7f1fb;
            margin: -10px -14px 10px;
            padding: 10px 14px 12px;
            border-bottom: 1px solid color-mix(in srgb, var(--ink) 25%, #fff);
        }
        .clinic-en {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            line-height: 1.15;
            color: var(--ink);
            letter-spacing: 0.01em;
        }
        .badge-wrap {
            text-align: center;
            padding-top: 6px;
        }
        .badge {
            display: inline-block;
            border: 2px solid var(--ink);
            border-radius: 999px;
            padding: 3px 18px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            background: #fff;
        }
        .contact {
            text-align: right;
            font-size: 11px;
            line-height: 1.45;
            max-width: 220px;
            justify-self: end;
        }
        .contact .addr { margin: 0 0 4px; }
        .contact .tel { margin: 0; }
        .meta {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 8px 18px;
            align-items: end;
            margin: 4px 0 8px;
            font-size: 13px;
        }
        .meta .field {
            display: flex;
            gap: 8px;
            align-items: baseline;
        }
        .meta .label { font-weight: 700; white-space: nowrap; }
        .meta .value {
            border-bottom: 1px dotted var(--ink);
            min-width: 3.5rem;
            flex: 1;
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
        table.lines th.qty, table.lines td.qty { text-align: center; width: 3.2rem; }
        table.lines th.money, table.lines td.money { text-align: right; width: 5.5rem; }
        table.lines td {
            border-bottom: 1px dotted #7a93b0;
            padding: 7px 8px;
            height: 28px;
            vertical-align: middle;
        }
        table.lines td.money.empty {
            background: linear-gradient(to top right, transparent calc(50% - 0.6px), #94a3b8, transparent calc(50% + 0.6px));
        }
        .foot {
            display: grid;
            grid-template-columns: 1fr 170px;
            gap: 10px 16px;
            margin-top: 8px;
            align-items: start;
        }
        .pay { font-size: 13px; }
        .pay-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 16px;
            margin: 2px 0 8px;
            font-weight: 700;
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
        }
        .box.is-on i::after {
            content: "";
            position: absolute;
            left: 2px;
            top: -1px;
            width: 5px;
            height: 9px;
            border: solid var(--ink);
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }
        .dotted {
            display: flex;
            gap: 8px;
            align-items: baseline;
            margin: 4px 0;
            font-size: 12px;
        }
        .dotted .label { font-weight: 700; white-space: nowrap; }
        .dotted .line {
            flex: 1;
            border-bottom: 1px dotted var(--ink);
            min-height: 1.15em;
            font-weight: 600;
        }
        .totals {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        .totals th, .totals td {
            border: 1px solid var(--ink);
            padding: 5px 8px;
        }
        .totals th {
            text-align: left;
            font-weight: 700;
            background: #e7f1fb;
            width: 58%;
        }
        .totals td { text-align: right; font-weight: 700; }
        .sign {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 12px;
            align-items: end;
            margin-top: 18px;
            font-size: 12px;
        }
        .sign .slot { text-align: center; }
        .sign .who { min-height: 1.4em; font-weight: 700; }
        .sign .caption {
            border-top: 1px solid var(--ink);
            padding-top: 4px;
            font-weight: 700;
        }
        .thanks {
            text-align: center;
            font-weight: 800;
            font-size: 13px;
            padding-bottom: 8px;
        }
        .voided {
            position: relative;
        }
        .voided::after {
            content: "{{ __('RETURNED') }}";
            position: absolute;
            inset: 35% 10%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            font-weight: 800;
            color: #b91c1c;
            opacity: 0.35;
            transform: rotate(-18deg);
            pointer-events: none;
            letter-spacing: 0.12em;
        }
        @media print {
            body { background: #fff; }
            .screen-bar { display: none; }
            .sheet { margin: 0; width: auto; min-height: 0; }
            @page { size: A5 landscape; margin: 6mm; }
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
                <div>
                    <p class="clinic-en">{{ $clinicName }}</p>
                </div>
                <div class="badge-wrap">
                    <span class="badge">{{ __('Medicine voucher') }}</span>
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
                    <span class="label">{{ __('Sl No') }}:</span>
                    <span class="value">{{ $sale->receiptLabel() }}</span>
                </div>
                <div class="field">
                    <span class="label">{{ __('Customer Name (Mr/Mrs)') }}:</span>
                    <span class="value">{{ $sale->patient_name ?: '' }}</span>
                </div>
                <div class="field">
                    <span class="label">{{ __('Date') }}:</span>
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
