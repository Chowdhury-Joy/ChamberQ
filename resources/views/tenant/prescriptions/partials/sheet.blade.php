{{--
    Pad-style prescription body shared by doctor print and the patient copy.

    Expects: $prescription, $visitRecord, $patient, $booking, $doctor, $chamber
    Optional: $showExpiryFootnote (bool) for the SMS/WhatsApp share page.
    Optional: $onMyPaper (bool) — doctor print onto pre-printed pads; hides
    the letterhead and leaves a gap so the clinic's own header is not
    overprinted. Patient share must never set this.
--}}
@php
    $doctorName = $doctor?->name ?? ($tenant?->displayName() ?? tenant()?->displayName());
    $doctorLabel = \Illuminate\Support\Str::startsWith(mb_strtolower((string) $doctorName), ['dr.', 'dr ', 'prof'])
        ? $doctorName
        : 'Dr. '.$doctorName;

    $hasClinical = filled($visitRecord?->diagnosisLabel())
        || filled($visitRecord?->chief_complaint)
        || filled($visitRecord?->history)
        || filled($visitRecord?->on_examination)
        || (filled($visitRecord?->clinical_notes)
            && blank($visitRecord?->chief_complaint)
            && blank($visitRecord?->history)
            && blank($visitRecord?->on_examination))
        || filled($visitRecord?->tests_advised)
        || filled($prescription->advice);

    $dateSource = $booking?->booking_date ?? $prescription->created_at;
@endphp

<div class="pad{{ ! empty($onMyPaper) ? ' pad--on-paper' : '' }}">
    @unless (! empty($onMyPaper))
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
    @endunless

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
        @if ($patient?->displaySex())
            <div class="field">
                <span class="label">{{ bilingual(ucfirst($patient->displaySex())) }}</span>
            </div>
        @endif
        @if ($visitRecord?->weightLabel())
            <div class="field">
                <span class="label">{{ bilingual('Wt') }}</span>
                <span class="value">{{ $visitRecord->weightLabel() }}</span>
            </div>
        @endif
        @if ($visitRecord?->bloodPressureLabel())
            <div class="field">
                <span class="label">{{ bilingual('BP') }}</span>
                <span class="value">{{ $visitRecord->bloodPressureLabel() }}</span>
            </div>
        @endif
        @if ($visitRecord?->pulseLabel())
            <div class="field">
                <span class="label">{{ bilingual('Pulse') }}</span>
                <span class="value">{{ $visitRecord->pulseLabel() }}</span>
            </div>
        @endif
        @if ($visitRecord?->spo2Label())
            <div class="field">
                <span class="label">{{ bilingual('SpO₂') }}</span>
                <span class="value">{{ $visitRecord->spo2Label() }}</span>
            </div>
        @endif
        @if ($visitRecord?->temperatureLabel())
            <div class="field">
                <span class="label">{{ bilingual('Temp') }}</span>
                <span class="value">{{ $visitRecord->temperatureLabel() }}</span>
            </div>
        @endif
        <div class="field grow">
            <span class="label">{{ bilingual('Date') }}</span>
            <span class="value">{{ $dateSource?->copy()->locale('bn')->translatedFormat('j F Y') }}</span>
        </div>
    </div>

    <div class="pad-body">
        <aside class="pad-clinical">
            @if ($visitRecord?->diagnosisLabel())
                <div class="clinical-block">
                    <strong>{{ bilingual('Diagnosis') }}</strong>
                    <div class="body">{{ $visitRecord->diagnosisLabel() }}</div>
                </div>
            @endif

            @if (filled($visitRecord?->chief_complaint))
                <div class="clinical-block">
                    <strong>{{ bilingual('C/C') }}</strong>
                    <div class="body">{{ $visitRecord->chief_complaint }}</div>
                </div>
            @endif

            @if (filled($visitRecord?->history))
                <div class="clinical-block">
                    <strong>{{ bilingual('H/O') }}</strong>
                    <div class="body">{{ $visitRecord->history }}</div>
                </div>
            @endif

            @if (filled($visitRecord?->on_examination))
                <div class="clinical-block">
                    <strong>{{ bilingual('O/E') }}</strong>
                    <div class="body">{{ $visitRecord->on_examination }}</div>
                </div>
            @endif

            @if (filled($visitRecord?->clinical_notes)
                && blank($visitRecord?->chief_complaint)
                && blank($visitRecord?->history)
                && blank($visitRecord?->on_examination))
                <div class="clinical-block">
                    <strong>{{ bilingual('Clinical notes') }}</strong>
                    <div class="body">{{ $visitRecord->clinical_notes }}</div>
                </div>
            @endif

            @if (filled($visitRecord?->tests_advised))
                <div class="clinical-block">
                    <strong>{{ bilingual('Inv') }}</strong>
                    <div class="body">{{ $visitRecord->tests_advised }}</div>
                </div>
            @endif

            @if (filled($prescription->advice))
                <div class="clinical-block">
                    <strong>{{ bilingual('Advice') }}</strong>
                    <div class="body">{{ $prescription->advice }}</div>
                </div>
            @endif

            @if ($prescription->followUpLabel())
                <div class="clinical-block">
                    <strong>{{ bilingual('Follow-up') }}</strong>
                    <div class="body">{{ $prescription->followUpLabel() }}</div>
                </div>
            @endif

            @unless ($hasClinical || $prescription->followUpLabel())
                <p class="clinical-empty">{{ bilingual('No clinical notes recorded for this visit.') }}</p>
            @endunless
        </aside>

        <section class="pad-rx">
            <div class="rx-symbol">℞</div>

            @if ($prescription->items->isNotEmpty())
                {{-- Numbered, because "stop number 3" is how a doctor refers
                     back to a line on a follow-up call and how a pharmacist
                     reads a strip back to the counter. --}}
                <ol class="med-list">
                    @foreach ($prescription->items as $item)
                        <li class="med-row">
                            <span class="med-number">{{ $loop->iteration }}.</span>
                            <div>
                                <div class="medicine-brand">
                                    {{ $item->medicine_name }}
                                    @if (filled($item->dose))
                                        <span style="font-weight:600;text-transform:none;"> — {{ $item->dose }}</span>
                                    @endif
                                </div>
                                @if (filled($item->generic_name) || filled($item->indication))
                                    <div class="medicine-meta">
                                        {{ collect([$item->generic_name, $item->indication])->filter()->implode(' · ') }}
                                    </div>
                                @endif
                                @if (filled($item->instructions))
                                    <div class="medicine-meta">{{ $item->instructions }}</div>
                                @endif
                                {{-- Patient copy only. `1+0+1` is prescriber
                                     shorthand: correct on the doctor's paper,
                                     unreadable on the phone of the person who
                                     has to swallow it. Rendered under the brand
                                     so the phone card reads top to bottom. --}}
                                @if (! empty($patientCopy) && ($plainDose = \App\Support\DoseSchedule::sentence($item->frequency)))
                                    <div class="medicine-plain">{{ $plainDose }}</div>
                                @endif
                            </div>
                            <div class="med-dosing">
                                @if (filled($item->frequency))
                                    {{ $item->frequency }}
                                @endif
                                @if (filled($item->duration))
                                    <span class="duration">{{ $item->duration }}</span>
                                @endif
                                @if (filled($item->timing))
                                    <span class="timing">{{ $item->timingBilingualLabel() }}</span>
                                @endif
                                {{-- Frequency × duration, printed only when both
                                     multiply out cleanly. Nothing appears for
                                     SOS or Continue — see PrescriptionQuantity. --}}
                                @if ($item->totalDoses())
                                    <span class="total-doses">
                                        {{ bilingual('Total') }} {{ $item->totalDoses() }}
                                    </span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="clinical-empty">{{ bilingual('No medicines on this prescription.') }}</p>
            @endif
        </section>
    </div>

    <footer class="pad-footer">
        <p class="bring-back">{{ bilingual('Please bring this prescription when you visit again.') }}</p>
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

@if (! empty($showExpiryFootnote))
    <p class="sheet-footnote">
        {{ __('This link expires :hours hours after it was sent. Save or print this page to keep a copy.', ['hours' => \App\Models\Prescription::SHARE_LINK_EXPIRY_HOURS]) }}
    </p>
@endif
