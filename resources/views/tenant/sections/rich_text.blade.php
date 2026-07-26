@if(tenant()?->isClinic())
<section class="w-full py-12 md:py-16 bg-white">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        <div class="prose max-w-none text-slate-800 text-sm md:text-base leading-relaxed">
            {!! clean($data['content'] ?? '') !!}
        </div>
    </div>
</section>
@endif
