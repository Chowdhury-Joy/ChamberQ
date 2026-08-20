<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('Fee receipt') }} {{ $entry->receiptLabel() }}</title>
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
        @page { size: A4; margin: 12mm; }
    </style>
</head>
<body>
@php
    $doctorName = $doctor?->name ?? ($tenant?->displayName() ?? tenant()?->displayName());
    $doctorLabel = \Illuminate\Support\Str::startsWith(mb_strtolower((string) $doctorName), ['dr.', 'dr ', 'prof'])
        ? $doctorName
        : 'Dr. '.$doctorName;
    $paidCash = $entry->method === \App\Models\ChamberCashEntry::METHOD_CASH
        || $entry->method === \App\Models\ChamberCashEntry::METHOD_MIXED;
    $paidOnline = $entry->method === \App\Models\ChamberCashEntry::METHOD_MIXED
        || array_key_exists($entry->method, \App\Models\ChamberCashEntry::onlineMethods());
@endphp
    <div class="sheet">
        <div class="toolbar no-print">
            <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
        </div>

        <div class="pad">
            <header class="pad-header">
                <div>
                    <p class="doctor-name">{{ $doctorLabel }}</p>
                    @if (filled($doctor?->qualifications))
                        <p class="muted">{{ $doctor->qualifications }}</p>
                    @endif
                    @if (filled($doctor?->registration_number))
                        <p class="muted">{{ bilingual('Reg. No.') }} {{ $doctor->registration_number }}</p>
                    @endif
                </div>
                @if ($chamber)
                    <div class="chamber-block">
                        <p class="chamber-name">{{ $chamber->name }}</p>
                        @if (filled($chamber->address))
                            <p class="muted">{{ $chamber->address }}</p>
                        @endif
                        @if (filled($chamber->contact))
                            <p class="muted">{{ $chamber->contact }}</p>
                        @endif
                    </div>
                @endif
            </header>

            <div class="patient-band">
                <div class="field">
                    <span class="label">{{ bilingual('Patient') }}</span>
                    <span class="value">{{ $patient?->name ?? $booking?->patient_name }}</span>
                </div>
                @if ($patient?->displayAge())
                    <div class="field">
                        <span class="label">{{ bilingual('Age') }}</span>
                        <span class="value">{{ $patient->displayAge() }}</span>
                    </div>
                @endif
                @if ($booking?->serial_number)
                    <div class="field">
                        <span class="label">{{ bilingual('Serial') }}</span>
                        <span class="value">{{ $booking->serial_number }}</span>
                    </div>
                @endif
                <div class="field grow">
                    <span class="label">{{ bilingual('Date') }}</span>
                    <span class="value">{{ $entry->occurred_on?->copy()->locale('bn')->translatedFormat('j F Y') }}</span>
                </div>
            </div>

            <div class="pad-body">
                <aside class="pad-clinical">
                    <div class="clinical-block">
                        <strong>{{ bilingual('Fee receipt') }}</strong>
                        <div class="body">{{ bilingual('Sl No') }} {{ $entry->receiptLabel() }}</div>
                    </div>
                    <div class="clinical-block">
                        <strong>{{ bilingual('Cash') }}</strong>
                        <div class="body">{{ $paidCash ? '✓' : '—' }}</div>
                    </div>
                    <div class="clinical-block">
                        <strong>{{ bilingual('Online') }}</strong>
                        <div class="body">{{ $paidOnline ? '✓' : '—' }}</div>
                    </div>
                    @if ($discount > 0)
                        <div class="clinical-block">
                            <strong>{{ bilingual('Discount') }}</strong>
                            <div class="body">{{ number_format($discount) }}/-</div>
                        </div>
                    @endif
                    <div class="clinical-block">
                        <strong>{{ $entry->isWaived() ? bilingual('Waived') : bilingual('Net Payable') }}</strong>
                        <div class="body">{{ number_format((int) $entry->amount) }}/-</div>
                    </div>
                    <div class="clinical-block">
                        <strong>{{ bilingual('In Word') }}</strong>
                        <div class="body">{{ $amountInWords }}</div>
                    </div>
                </aside>

                <section class="pad-rx">
                    <div class="rx-symbol">℞</div>
                    <ol class="med-list">
                        <li class="med-row">
                            <span class="med-number">1.</span>
                            <div>
                                <div class="medicine-brand">{{ $entry->cashbookSubjectLabel() }}</div>
                            </div>
                            <div class="med-dosing">
                                {{ number_format($gross) }}/-
                            </div>
                        </li>
                    </ol>
                </section>
            </div>

            <footer class="pad-footer">
                <p class="bring-back">{{ bilingual('Thank You.') }}</p>
                <p>{{ bilingual('Received By') }}@if(filled($entry->recorder?->name)) — {{ $entry->recorder->name }}@endif</p>
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
