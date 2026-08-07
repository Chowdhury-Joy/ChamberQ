{{--
    Solo copy of the pre-Clireo shared section, pinned here on 2026-08-07.

    The solo shell falls back to `tenant.sections.*` for any block it does not
    override, so porting those blades to the clinic design would have changed
    the LOCKED solo homepage. This file is the markup solo rendered before the
    port; `tenant/sections/rich_text.blade.php` is now clinic-only.
    Do not restyle without the owner saying "update patient homepage".
--}}
<section class="w-full bg-white py-10 sm:py-14 lg:py-16">
    <div class="mx-auto max-w-[1320px] px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center text-slate-800 sm:max-w-3xl [&_h1]:mb-3 [&_h1]:text-2xl [&_h1]:font-bold [&_h1]:tracking-tight [&_h1]:text-slate-900 sm:[&_h1]:mb-4 sm:[&_h1]:text-3xl lg:[&_h1]:text-4xl [&_h2]:mb-3 [&_h2]:text-2xl [&_h2]:font-bold [&_h2]:tracking-tight [&_h2]:text-slate-900 sm:[&_h2]:mb-4 sm:[&_h2]:text-3xl lg:[&_h2]:text-4xl [&_h3]:mb-2 [&_h3]:text-xl [&_h3]:font-semibold [&_h3]:text-slate-900 sm:[&_h3]:text-2xl [&_p]:mb-4 [&_p]:text-base [&_p]:leading-relaxed [&_p]:text-slate-600 sm:[&_p]:text-lg [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-5 [&_ul]:text-left [&_ul]:text-slate-600 [&_a]:font-semibold [&_a]:underline-offset-2 hover:[&_a]:underline">
            {!! \App\Support\HtmlSanitizer::clean($data['content'] ?? '') !!}
        </div>
    </div>
</section>
