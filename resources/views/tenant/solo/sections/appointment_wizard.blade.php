{{--
    Solo copy of the pre-Clireo shared section, pinned here on 2026-08-07.

    The solo shell falls back to `tenant.sections.*` for any block it does not
    override, so porting those blades to the clinic design would have changed
    the LOCKED solo homepage. This file is the markup solo rendered before the
    port; `tenant/sections/appointment_wizard.blade.php` is now clinic-only.
    Do not restyle without the owner saying "update patient homepage".
--}}
<section class="w-full py-12 md:py-16 bg-sky-950 text-white relative overflow-hidden">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8 relative z-10 text-center">
        <h2 class="text-2xl sm:text-4xl font-extrabold tracking-tight mb-4">
            {{ $data['heading'] ?? 'Book Your Appointment Online in 60 Seconds' }}
        </h2>
        <p class="text-sky-200 text-base sm:text-lg max-w-xl mx-auto mb-8">
            {{ $data['subheadline'] ?? 'Select specialty, doctor, date and pick your preferred time slot.' }}
        </p>

        <a href="{{ tenant_web_url('/book') }}" class="inline-flex items-center gap-2 px-8 py-4 rounded-full bg-sky-400 text-slate-950 font-bold text-base hover:bg-sky-300 shadow-xl transition-all">
            <span>Start Booking Wizard</span>
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
        </a>
    </div>
</section>
