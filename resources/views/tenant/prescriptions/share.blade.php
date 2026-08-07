{{--
    The patient's own copy of ONE prescription, opened from a signed WhatsApp
    link with no login.

    Scope is deliberate and must not grow: medicines, the dosing advice and
    follow-up date written on this prescription, the prescriber's name and
    registration, the patient's name, and the date. Nothing from `visit_records`
    (diagnosis, tests advised, reports seen, voice, photos) is loaded by
    `PrescriptionShareController`, and nothing here links onward into the
    practice's records. See the 2026-08-07 decision in `decisions.md`.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>{{ __('Prescription') }} — {{ $patient?->name ?? __('Patient') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #111;
            background: #f1f5f9;
            font-size: 15px;
            line-height: 1.5;
        }
        .sheet {
            max-width: 640px;
            margin: 0 auto;
            padding: 16px;
        }
        .card {
            background: #fff;
            border-radius: 12px;
            padding: 24px 20px 32px;
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.08);
        }
        .toolbar { margin-bottom: 14px; }
        .toolbar button {
            font: inherit;
            width: 100%;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #0369a1;
            background: #0284c7;
            color: #fff;
            cursor: pointer;
        }
        .header {
            border-bottom: 2px solid #111;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .doctor-name { font-size: 19px; font-weight: 700; margin: 0 0 4px; }
        .muted { color: #475569; font-size: 13px; }
        .patient-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .patient-row strong { font-size: 16px; }
        .rx-symbol { font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        table.meds { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.meds th, table.meds td {
            text-align: left;
            padding: 8px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        table.meds th {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
        }
        .medicine-brand { font-weight: 700; text-transform: uppercase; }
        .medicine-generic { font-size: 12px; color: #475569; margin-top: 2px; }
        .advice { margin: 6px 0 0; white-space: pre-wrap; }
        .block { margin-bottom: 14px; }
        .footnote {
            margin-top: 22px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #64748b;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; font-size: 13px; }
            .sheet { padding: 0; max-width: none; }
            .card { box-shadow: none; border-radius: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar no-print">
            <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
        </div>

        <div class="card">
            @php
                // Doctor profiles are often saved with the title already in the
                // name ("Dr. Mahfuzur Rahman"), so only add it when it is absent.
                $doctorName = $doctor?->name ?? tenant()?->displayName();
                $doctorLabel = \Illuminate\Support\Str::startsWith(mb_strtolower((string) $doctorName), ['dr.', 'dr ', 'prof'])
                    ? $doctorName
                    : 'Dr. '.$doctorName;
            @endphp

            <header class="header">
                <p class="doctor-name">{{ $doctorLabel }}</p>
                @if (filled($doctor?->registration_number))
                    <p class="muted">{{ __('Reg. No.') }} {{ $doctor->registration_number }}</p>
                @endif
            </header>

            <div class="patient-row">
                <div>
                    <div class="muted">{{ __('Patient') }}</div>
                    <strong>{{ $patient?->name ?? $booking?->patient_name }}</strong>
                </div>
                <div class="muted" style="text-align: right;">
                    {{ __('Date') }}<br>
                    <strong style="color:#111;">
                        {{ ($booking?->booking_date ?? $prescription->created_at)->translatedFormat('j F Y') }}
                    </strong>
                </div>
            </div>

            <div class="rx-symbol">℞</div>

            @if ($prescription->items->isNotEmpty())
                <table class="meds">
                    <thead>
                        <tr>
                            <th>{{ __('Medicine') }}</th>
                            <th>{{ __('Dose') }}</th>
                            <th>{{ __('Frequency') }}</th>
                            <th>{{ __('Duration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($prescription->items as $item)
                            <tr>
                                <td>
                                    <div class="medicine-brand">{{ $item->medicine_name }}</div>
                                    @if (filled($item->generic_name))
                                        <div class="medicine-generic">{{ $item->generic_name }}</div>
                                    @endif
                                </td>
                                <td>{{ $item->dose }}</td>
                                <td>{{ $item->frequency }}</td>
                                <td>{{ $item->duration }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @if (filled($prescription->advice))
                <div class="block">
                    <strong>{{ __('Advice') }}</strong>
                    <div class="advice">{{ $prescription->advice }}</div>
                </div>
            @endif

            @if ($prescription->followUpLabel())
                <div class="block">
                    <strong>{{ __('Follow-up') }}:</strong>
                    {{ $prescription->followUpLabel() }}
                </div>
            @endif

            <p class="footnote">
                {{ __('This link expires :hours hours after it was sent. Save or print this page to keep a copy.', ['hours' => \App\Models\Prescription::SHARE_LINK_EXPIRY_HOURS]) }}
            </p>
        </div>
    </div>
</body>
</html>
