<section class="w-full py-12 md:py-16 bg-sky-50 border-y border-sky-100">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8 text-center">
        <h2 class="text-2xl sm:text-3xl font-extrabold text-slate-900 mb-3 tracking-tight">
            {{ $data['headline'] ?? 'Need Same-Day Care? Book Online in 60 Seconds' }}
        </h2>
        @if(!empty($data['subheadline']))
            <p class="text-sm sm:text-base text-slate-600 max-w-xl mx-auto mb-6">
                {{ $data['subheadline'] }}
            </p>
        @endif
        <a href="{{ \App\Support\SafeUrl::href($data['cta_link'] ?? '/book', '/book') }}" class="btn btn-primary inline-flex items-center gap-2 shadow-lg shadow-sky-500/25">
            <span>{{ $data['cta_text'] ?? 'Book Appointment Now' }}</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
        </a>
    </div>
</section>
