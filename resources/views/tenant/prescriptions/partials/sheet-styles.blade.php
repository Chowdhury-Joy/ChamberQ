{{-- Shared pad-style prescription CSS (doctor print + patient copy). --}}
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: 'Helvetica Neue', Arial, sans-serif;
        color: #111;
        background: #fff;
        font-size: 13px;
        line-height: 1.45;
    }
    .sheet {
        max-width: 794px; /* ~A4 width at 96dpi */
        margin: 0 auto;
        padding: 20px 24px 36px;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    .pad {
        flex: 1;
        display: flex;
        flex-direction: column;
        border: 1px solid #cbd5e1;
        border-radius: 2px;
        overflow: hidden;
        background: #fff;
    }
    .pad-header {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 12px 20px;
        padding: 14px 16px 12px;
        border-bottom: 2px solid #0f172a;
    }
    .doctor-name {
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 4px;
    }
    .muted { color: #475569; font-size: 12px; }
    .pad-header .muted { margin: 0 0 2px; }
    .chamber-block {
        text-align: right;
        max-width: 46%;
    }
    .chamber-block .chamber-name {
        font-weight: 700;
        font-size: 13px;
        margin: 0 0 2px;
    }
    .patient-band {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 18px;
        align-items: baseline;
        padding: 8px 16px;
        background: #f1f5f9;
        border-bottom: 1px solid #cbd5e1;
        font-size: 13px;
    }
    .patient-band .field {
        display: inline-flex;
        gap: 6px;
        align-items: baseline;
        min-width: 0;
    }
    .patient-band .label {
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        white-space: nowrap;
    }
    .patient-band .value {
        font-weight: 600;
        color: #0f172a;
    }
    .patient-band .grow { margin-left: auto; }
    .pad-body {
        display: grid;
        grid-template-columns: minmax(0, 38%) minmax(0, 62%);
        flex: 1;
        min-height: 420px;
    }
    .pad-clinical {
        padding: 12px 14px 16px;
        border-right: 1px solid #e2e8f0;
        background: #fafafa;
    }
    .pad-rx {
        padding: 12px 14px 16px;
    }
    .rx-symbol {
        font-size: 26px;
        font-weight: 700;
        margin: 0 0 10px;
        line-height: 1;
    }
    .clinical-block {
        margin-bottom: 12px;
    }
    .clinical-block:last-child { margin-bottom: 0; }
    .clinical-block strong {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #64748b;
        margin-bottom: 3px;
    }
    .clinical-block .body {
        white-space: pre-wrap;
        font-size: 13px;
    }
    .clinical-empty {
        color: #94a3b8;
        font-size: 12px;
        font-style: italic;
    }
    .med-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .med-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 6px 12px;
        padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
        align-items: start;
    }
    .med-row:last-child { border-bottom: none; }
    .medicine-brand {
        font-weight: 700;
        text-transform: uppercase;
        font-size: 13px;
    }
    .medicine-meta {
        font-size: 11px;
        color: #475569;
        margin-top: 2px;
    }
    .med-dosing {
        text-align: right;
        white-space: nowrap;
        font-size: 13px;
        font-weight: 600;
        font-variant-numeric: tabular-nums;
    }
    .med-dosing .duration,
    .med-dosing .timing {
        display: block;
        font-size: 11px;
        font-weight: 500;
        color: #475569;
        margin-top: 2px;
        white-space: normal;
        max-width: 11rem;
    }
    .pad-footer {
        margin-top: auto;
        padding: 10px 16px;
        border-top: 1px solid #cbd5e1;
        background: #f8fafc;
        font-size: 12px;
        color: #334155;
    }
    .pad-footer .bring-back {
        font-weight: 600;
        margin: 0 0 4px;
    }
    .pad-footer .chamber-line {
        margin: 0;
        color: #64748b;
        font-size: 11px;
    }
    .sheet-footnote {
        margin-top: 14px;
        font-size: 12px;
        color: #64748b;
    }
    @media print {
        .no-print { display: none !important; }
        body { font-size: 12px; background: #fff; }
        .sheet { padding: 0; max-width: none; min-height: auto; }
        .pad { border: none; border-radius: 0; }
        .pad-body { min-height: 0; }
    }
    @media (max-width: 640px) {
        .pad-header { grid-template-columns: 1fr; }
        .chamber-block { text-align: left; max-width: none; }
        .pad-body { grid-template-columns: 1fr; }
        .pad-clinical {
            border-right: none;
            border-bottom: 1px solid #e2e8f0;
        }
        .patient-band .grow { margin-left: 0; }
    }
</style>
