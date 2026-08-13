{{--
    The patient's own copy of ONE prescription — SMS/WhatsApp /p/{token} or
    phone-gated portal view. Full clinical pad (diagnosis, notes, meds, chamber).
    Voice notes and prescription photos stay off this page.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    {{-- The share token lives in this page's URL. Nothing here loads or links
         off-origin today, so nothing can leak it via Referer — this keeps that
         true if a logo or font is ever added. --}}
    <meta name="referrer" content="no-referrer">
    <title>{{ __('Prescription') }} — {{ $patient?->name ?? __('Patient') }}</title>
    @include('tenant.prescriptions.partials.sheet-styles')
    <style>
        body { background: #f1f5f9; }
        .sheet { padding: 16px; }
        .toolbar { margin-bottom: 14px; }
        .toolbar button {
            font: inherit;
            width: 100%;
            max-width: 420px;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #0369a1;
            background: #0284c7;
            color: #fff;
            cursor: pointer;
        }
        .pad {
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.08);
            border-radius: 8px;
        }
        @media print {
            body { background: #fff; }
            .sheet { padding: 0; }
            .pad { box-shadow: none; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="toolbar no-print">
            <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
        </div>

        @include('tenant.prescriptions.partials.sheet', [
            'showExpiryFootnote' => $showExpiryFootnote ?? false,
        ])
    </div>
</body>
</html>
