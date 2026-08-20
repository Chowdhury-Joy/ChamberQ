<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Medicine voucher') }} {{ $sale->receiptLabel() }}</title>
    @include('tenant.prescriptions.partials.sheet-styles')
    <style>
        .toolbar {
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .toolbar button {
            font: inherit;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #0284c7;
            border-color: #0369a1;
            color: #fff;
            cursor: pointer;
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
        @page { size: A4; margin: 12mm; }
        .voucher-pad .pad-header {
            grid-template-columns: minmax(0, 34%) minmax(0, 66%);
            align-items: start;
        }
        .voucher-pad .chamber-block {
            max-width: none;
            width: 100%;
        }
        .voucher-pad .pad-body {
            grid-template-columns: minmax(0, 30%) minmax(0, 70%);
        }
        .clinic-logo {
            height: 56px;
            width: auto;
            max-width: 220px;
            object-fit: contain;
            display: block;
            background: transparent;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .logo-fallback {
            margin: 0;
        }
        .logo-doctor { margin-top: 8px; }
        .med-sheet { width: 100%; }
        .med-sheet-head,
        .med-sheet-row {
            display: grid;
            grid-template-columns: 1.6rem minmax(0, 1fr) 2.8rem 4.4rem 4.8rem;
            gap: 8px 10px;
            align-items: baseline;
        }
        .med-sheet-head {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            padding: 0 0 6px;
            border-bottom: 1px solid #cbd5e1;
            background: none;
        }
        .med-sheet-row {
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .med-sheet-row:last-child { border-bottom: none; }
        .med-sheet .name {
            font-weight: 700;
            text-transform: uppercase;
            font-size: 13px;
        }
        .med-sheet .num { font-variant-numeric: tabular-nums; color: #475569; }
        .med-sheet .qty { text-align: center; font-variant-numeric: tabular-nums; }
        .med-sheet .money {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-weight: 600;
        }
        .med-sheet-head .qty,
        .med-sheet-head .money { font-weight: 600; }
    </style>
</head>
<body>
@php
    $doctorName = $doctor?->name ?? ($tenant?->displayName() ?? tenant()?->displayName());
    $doctorLabel = \Illuminate\Support\Str::startsWith(mb_strtolower((string) $doctorName), ['dr.', 'dr ', 'prof'])
        ? $doctorName
        : 'Dr. '.$doctorName;
    $ticked = \App\Services\PharmacyInvoiceService::tickedVoucherPayments($sale);
    $paidCash = in_array(\App\Models\ChamberCashEntry::METHOD_CASH, $ticked, true);
    $paidOnline = in_array(\App\Services\PharmacyInvoiceService::VOUCHER_ONLINE, $ticked, true);
    $dateSource = $sale->occurred_on;
@endphp
    <div class="sheet">
        <div class="toolbar no-print">
            <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
        </div>

        <div class="pad voucher-pad{{ $sale->isVoided() ? ' voided' : '' }}">
            <header class="pad-header">
                <div>
                    @if($logoUrl)
                        <img class="clinic-logo" src="{{ $logoUrl }}" alt="">
                    @else
                        <p class="doctor-name logo-fallback">{{ $doctorLabel }}</p>
                    @endif
                    @if ($logoUrl && filled($doctorLabel))
                        <p class="muted logo-doctor">{{ $doctorLabel }}</p>
                    @endif
                    @if (filled($doctor?->qualifications))
                        <p class="muted">{{ $doctor->qualifications }}</p>
                    @endif
                    @if (filled($doctor?->registration_number))
                        <p class="muted">{{ bilingual('Reg. No.') }} {{ $doctor->registration_number }}</p>
                    @endif
                </div>
                <div class="chamber-block">
                    @if ($chamber)
                        <p class="chamber-name">{{ $chamber->name }}</p>
                        @if (filled($chamber->address))
                            <p class="muted">{{ $chamber->address }}</p>
                        @endif
                        @if (filled($chamber->contact))
                            <p class="muted">{{ $chamber->contact }}</p>
                        @endif
                    @endif
                </div>
            </header>

            <div class="patient-band">
                <div class="field">
                    <span class="label">{{ bilingual('Patient') }}</span>
                    <span class="value">{{ $patient?->name ?? $sale->patient_name }}</span>
                </div>
                @if ($patient?->displayAge())
                    <div class="field">
                        <span class="label">{{ bilingual('Age') }}</span>
                        <span class="value">{{ $patient->displayAge() }}</span>
                    </div>
                @endif
                <div class="field">
                    <span class="label">{{ bilingual('Sl No') }}</span>
                    <span class="value">{{ $sale->receiptLabel() }}</span>
                </div>
                <div class="field grow">
                    <span class="label">{{ bilingual('Date') }}</span>
                    <span class="value">{{ $dateSource?->copy()->locale('bn')->translatedFormat('j F Y') }}</span>
                </div>
            </div>

            <div class="pad-body">
                <aside class="pad-clinical">
                    <div class="clinical-block">
                        <strong>{{ bilingual('Medicine voucher') }}</strong>
                    </div>
                    <div class="clinical-block">
                        <strong>{{ bilingual('Cash') }}</strong>
                        <div class="body">{{ $paidCash ? '✓' : '—' }}</div>
                    </div>
                    <div class="clinical-block">
                        <strong>{{ bilingual('Online') }}</strong>
                        <div class="body">{{ $paidOnline ? '✓' : '—' }}</div>
                    </div>
                    <div class="clinical-block">
                        <strong>{{ bilingual('Total Amount') }}</strong>
                        <div class="body">{{ \App\Services\PharmacyInvoiceService::formatTaka($catalogueTotal) }}</div>
                    </div>
                    @if ($discount > 0)
                        <div class="clinical-block">
                            <strong>{{ bilingual('Discount') }}</strong>
                            <div class="body">{{ \App\Services\PharmacyInvoiceService::formatTaka($discount) }}</div>
                        </div>
                    @endif
                    <div class="clinical-block">
                        <strong>{{ bilingual('Net Payable') }}</strong>
                        <div class="body">{{ \App\Services\PharmacyInvoiceService::formatTaka($netPayable) }}</div>
                    </div>
                    <div class="clinical-block">
                        <strong>{{ bilingual('In Word') }}</strong>
                        <div class="body">{{ $amountInWords }}</div>
                    </div>
                </aside>

                <section class="pad-rx">
                    <div class="rx-symbol">℞</div>
                    @if (count($lines) > 0)
                        <div class="med-sheet">
                            <div class="med-sheet-head">
                                <span class="num">{{ bilingual('Sl') }}</span>
                                <span>{{ bilingual('Medicine Name') }}</span>
                                <span class="qty">{{ bilingual('Qty') }}</span>
                                <span class="money">{{ bilingual('Rate') }}</span>
                                <span class="money">{{ bilingual('Amount') }}</span>
                            </div>
                            @foreach ($lines as $i => $line)
                                <div class="med-sheet-row">
                                    <span class="num">{{ $i + 1 }}.</span>
                                    <span class="name">{{ $line->name }}</span>
                                    <span class="qty">{{ str_pad((string) $line->qty, 2, '0', STR_PAD_LEFT) }}</span>
                                    <span class="money">{{ \App\Services\PharmacyInvoiceService::formatTaka((int) $line->sell_price_taka) }}</span>
                                    <span class="money">{{ \App\Services\PharmacyInvoiceService::formatTaka((int) $line->line_total_taka) }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="clinical-empty">{{ bilingual('No medicines on this prescription.') }}</p>
                    @endif
                </section>
            </div>

            <footer class="pad-footer">
                <p class="bring-back">{{ bilingual('Thank You.') }}</p>
                @if (filled($receivedBy))
                    <p>{{ bilingual('Received By') }} — {{ $receivedBy }}</p>
                @else
                    <p>{{ bilingual('Received By') }}</p>
                @endif
                @if ($chamber)
                    <p class="chamber-line">
                        {{ $chamber->name }}
                        @if (filled($chamber->address))
                            — {{ $chamber->address }}
                        @endif
                        @if (filled($chamber->contact))
                            · {{ $chamber->contact }}
                        @endif
                    </p>
                @endif
            </footer>
        </div>
    </div>
    <script>
        window.addEventListener('load', function () { window.print(); });
    </script>
</body>
</html>
