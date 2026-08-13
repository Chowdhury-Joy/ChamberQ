{{--
    The patient's own copy of ONE prescription — SMS/WhatsApp /p/{token} or
    phone-gated portal view. Full clinical pad (diagnosis, notes, meds, chamber).
    Voice notes and prescription photos stay off this page.

    This link arrives by SMS or WhatsApp, so it is read on a phone essentially
    always. The A4 sheet is still the truth — same partial, same data as the
    doctor's print — but below 640px the medicine list becomes one card per
    drug, big enough to read at arm's length, with the dose written out.
--}}
<!DOCTYPE html>
<html lang="bn">
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
        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 14px;
        }
        .toolbar button,
        .toolbar a {
            font: inherit;
            flex: 1 1 12rem;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #0369a1;
            background: #0284c7;
            color: #fff;
            text-decoration: none;
            cursor: pointer;
        }
        /* Most patients here have no printer. Print stays first because it is
           what a pharmacy counter asks for, but the two that actually get used
           on a phone sit beside it rather than being absent. */
        .toolbar .secondary {
            background: #fff;
            color: #0f172a;
            border-color: #cbd5e1;
        }
        .toolbar .whatsapp {
            background: #16a34a;
            border-color: #15803d;
        }
        .pad {
            box-shadow: 0 1px 3px rgb(0 0 0 / 0.08);
            border-radius: 8px;
        }
        .save-hint {
            margin: 10px 0 0;
            font-size: 12px;
            color: #475569;
        }
        @media print {
            body { background: #fff; }
            .sheet { padding: 0; }
            .pad { box-shadow: none; border-radius: 0; }
        }

        /* ---- Phone: the medicine list stops being a table ---- */
        @media (max-width: 640px) {
            .sheet { padding: 10px; }
            .pad-rx { padding: 12px 10px 16px; }
            .med-row {
                display: block;
                position: relative;
                padding: 12px 12px 12px 40px;
                margin-bottom: 10px;
                border: 1px solid #cbd5e1;
                border-bottom: 1px solid #cbd5e1;
                border-radius: 10px;
                background: #fff;
            }
            .med-row:last-child { border-bottom: 1px solid #cbd5e1; margin-bottom: 0; }
            .med-number {
                position: absolute;
                top: 12px;
                left: 12px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 20px;
                font-size: 14px;
                color: #0f172a;
            }
            .medicine-brand { font-size: 16px; }
            .medicine-meta { font-size: 12px; }
            /* The line the patient is actually here for. */
            .medicine-plain {
                margin-top: 6px;
                font-size: 15px;
                font-weight: 600;
                color: #0f172a;
            }
            .med-dosing {
                text-align: left;
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px dashed #e2e8f0;
                display: flex;
                flex-wrap: wrap;
                gap: 4px 12px;
                white-space: normal;
                font-size: 14px;
            }
            .med-dosing .duration,
            .med-dosing .timing,
            .med-dosing .total-doses {
                display: inline;
                margin-top: 0;
                max-width: none;
                font-size: 13px;
            }
            .patient-band { font-size: 14px; }
            .clinical-block .body { font-size: 14px; }
        }
    </style>
</head>
<body>
    @php
        // Only the share-link routes are forwardable. The portal route reaches
        // this same view with the patient's phone number in the query string,
        // and handing that to WhatsApp would forward their number along with
        // their prescription. `showExpiryFootnote` is already the flag the
        // controller sets for exactly the link-based paths.
        $shareUrl = ($showExpiryFootnote ?? false) ? url()->current() : null;
    @endphp

    <div class="sheet">
        <div class="toolbar no-print">
            <button type="button" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
            @if (filled($shareUrl))
                <a
                    class="whatsapp"
                    href="https://wa.me/?text={{ rawurlencode(__('My prescription: :link', ['link' => $shareUrl])) }}"
                    target="_blank"
                    rel="noopener noreferrer"
                >{{ __('Send on WhatsApp') }}</a>
            @endif
        </div>

        @include('tenant.prescriptions.partials.sheet', [
            'showExpiryFootnote' => $showExpiryFootnote ?? false,
            'patientCopy' => true,
        ])

        {{-- Deliberately an instruction, not a button. Capturing the page as an
             image needs a canvas library we do not ship, and every phone here
             already has a screenshot gesture the patient knows. Telling them
             beats a button that produces a blurry crop. --}}
        <p class="save-hint no-print">{{ __('No printer? Take a screenshot of this page to keep it on your phone.') }}</p>
    </div>
</body>
</html>
