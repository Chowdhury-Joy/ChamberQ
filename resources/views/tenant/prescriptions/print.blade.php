<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Prescription') }} — {{ $patient?->name ?? __('Patient') }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Helvetica Neue', Arial, sans-serif;
            color: #111;
            background: #fff;
            font-size: 14px;
            line-height: 1.45;
        }
        .sheet {
            max-width: 720px;
            margin: 0 auto;
            padding: 24px 28px 48px;
        }
        .toolbar {
            margin-bottom: 16px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        .toolbar button, .toolbar a {
            font: inherit;
            padding: 8px 16px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            cursor: pointer;
            text-decoration: none;
            color: #0f172a;
        }
        .toolbar button.primary {
            background: #0284c7;
            border-color: #0369a1;
            color: #fff;
        }
        .warning {
            background: #fff7ed;
            border: 1px solid #fdba74;
            color: #9a3412;
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
        }
        .header {
            border-bottom: 2px solid #111;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .doctor-name {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 4px;
        }
        .sheet-title {
            margin: 0 0 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
        }
        .muted { color: #475569; font-size: 13px; }
        .patient-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
            padding: 10px 0;
            border-bottom: 1px solid #e2e8f0;
        }
        .patient-row strong { font-size: 16px; }
        .rx-symbol {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        table.meds {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
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
        .medicine-brand {
            font-weight: 700;
            text-transform: uppercase;
        }
        .medicine-generic {
            font-size: 12px;
            color: #475569;
            margin-top: 2px;
        }
        .advice {
            margin: 16px 0;
            white-space: pre-wrap;
        }
        .clinical-block {
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .clinical-block strong {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            margin-bottom: 4px;
        }
        .clinical-block .body {
            white-space: pre-wrap;
        }
        @media print {
            .no-print { display: none !important; }
            .sheet { padding: 0; max-width: none; }
            body { font-size: 13px; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar no-print">
            <button type="button" class="primary" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
            <a href="javascript:history.back()">{{ __('Back') }}</a>
        </div>

        @if ($missingRegistration)
            <div class="warning no-print">
                {{ __('BM&DC registration number is missing from the doctor profile. Add it in Doctors before printing a compliant prescription.') }}
            </div>
        @endif

        {{-- Fixed labels print in both languages (see \App\Support\Bilingual):
             the patient's family reads the Bangla, a pharmacist or a referred-to
             consultant may only be handed the English. Names, qualifications and
             anything the doctor typed are stored in one language and pass
             through as written. --}}
        <header class="header">
            <p class="sheet-title">{{ bilingual('Prescription') }}</p>
            <p class="doctor-name">Dr. {{ $doctor?->name ?? $tenant?->displayName() }}</p>
            @if (filled($doctor?->qualifications))
                <p class="muted">{{ $doctor->qualifications }}</p>
            @endif
            @if (filled($doctor?->registration_number))
                <p class="muted">{{ bilingual('Reg. No.') }} {{ $doctor->registration_number }}</p>
            @endif
            @if ($chamber)
                <p class="muted">
                    {{ $chamber->name }}
                    @if (filled($chamber->address))
                        — {{ $chamber->address }}
                    @endif
                    @if (filled($chamber->contact))
                        · {{ $chamber->contact }}
                    @endif
                </p>
            @endif
        </header>

        <div class="patient-row">
            <div>
                <div class="muted">{{ bilingual('Patient') }}</div>
                <strong>{{ $patient?->name ?? $booking?->patient_name }}</strong>
                @if ($patient?->displayAge())
                    <span class="muted"> · {{ bilingual('Age') }} {{ $patient->displayAge() }}</span>
                @endif
                @if ($patient?->displaySex())
                    <span class="muted"> · {{ bilingual(ucfirst($patient->displaySex())) }}</span>
                @endif
                @if ($visitRecord?->weightLabel())
                    <span class="muted"> · {{ bilingual('Wt') }} {{ $visitRecord->weightLabel() }}</span>
                @endif
                @if ($visitRecord?->bloodPressureLabel())
                    <span class="muted"> · {{ bilingual('BP') }} {{ $visitRecord->bloodPressureLabel() }}</span>
                @endif
            </div>
            <div class="muted" style="text-align: right;">
                {{ bilingual('Date') }}<br>
                <strong style="color:#111;">{{ ($booking?->booking_date ?? now())->translatedFormat('j F Y') }}</strong>
            </div>
        </div>

        @if ($visitRecord?->diagnosisLabel())
            <div class="clinical-block">
                <strong>{{ bilingual('Diagnosis') }}</strong>
                <div class="body">{{ $visitRecord->diagnosisLabel() }}</div>
            </div>
        @endif

        @if (filled($visitRecord?->clinical_notes))
            <div class="clinical-block">
                <strong>{{ bilingual('Clinical notes') }}</strong>
                <div class="body">{{ $visitRecord->clinical_notes }}</div>
            </div>
        @endif

        @if (filled($visitRecord?->tests_advised))
            <div class="clinical-block">
                <strong>{{ bilingual('Tests advised') }}</strong>
                <div class="body">{{ $visitRecord->tests_advised }}</div>
            </div>
        @endif

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
            <div>
                <strong>{{ __('Advice') }}</strong>
                <div class="advice">{{ $prescription->advice }}</div>
            </div>
        @endif

        @if ($prescription->followUpLabel())
            <p><strong>{{ __('Follow-up') }}:</strong> {{ $prescription->followUpLabel() }}</p>
        @endif
    </div>
</body>
</html>
