<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Prescription') }} — {{ $patient?->name ?? __('Patient') }}</title>
    @include('tenant.prescriptions.partials.sheet-styles')
    <style>
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

        @if (! empty($onMyPaper))
            <p class="warning no-print">
                {{ __('Letterhead hidden — printing onto your own pads.') }}
            </p>
        @endif

        {{-- Fixed labels print Bangla-first with English quieter (see
             \App\Support\Bilingual). Names, qualifications and anything the
             doctor typed pass through as written. --}}
        @include('tenant.prescriptions.partials.sheet')
    </div>
</body>
</html>
