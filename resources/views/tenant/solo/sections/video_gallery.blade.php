@php
    $heading = $data['heading'] ?? __('Latest Educational Videos');
    $followText = $data['follow_text'] ?? __('Follow for More');
    $followUrl = \App\Support\SafeUrl::href($data['follow_url'] ?? '', '');
    $videos = array_slice($data['videos'] ?? [], 0, 10);
@endphp

<section
    class="solo-section w-full bg-white"
    x-data="{
        canPrev: false,
        canNext: true,
        init() {
            this.$nextTick(() => this.update());
            this.$refs.track?.addEventListener('scroll', () => this.update(), { passive: true });
            window.addEventListener('resize', () => this.update());
        },
        update() {
            const el = this.$refs.track;
            if (!el) return;
            this.canPrev = el.scrollLeft > 4;
            this.canNext = el.scrollLeft + el.clientWidth < el.scrollWidth - 4;
        },
        step(dir) {
            const el = this.$refs.track;
            if (!el) return;
            const card = el.querySelector('[data-video-card]');
            const styles = getComputedStyle(el);
            const gap = parseFloat(styles.columnGap || styles.gap) || 24;
            const amount = card ? card.getBoundingClientRect().width + gap : el.clientWidth * 0.28;
            el.scrollBy({ left: dir * amount, behavior: 'smooth' });
        }
    }"
>
    <div class="mx-auto max-w-[1280px] px-3 sm:px-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <h2 class="font-display text-3xl tracking-tight text-slate-900 sm:text-4xl lg:text-[2.75rem]">
                {{ $heading }}
            </h2>
            <div class="flex items-center gap-3">
                @if($followUrl !== '')
                    <a href="{{ $followUrl }}" target="_blank" rel="noopener noreferrer" class="solo-cta-outline hidden sm:inline-flex">
                        {{ $followText }}
                    </a>
                @endif
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-800 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-35"
                        @click="step(-1)"
                        :disabled="!canPrev"
                        aria-label="{{ __('Previous videos') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-800 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-35"
                        @click="step(1)"
                        :disabled="!canNext"
                        aria-label="{{ __('Next videos') }}"
                    >
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div
            x-ref="track"
            class="solo-video-track mt-8 flex gap-4 overflow-x-auto pb-1 sm:mt-10 lg:gap-6"
        >
            @foreach($videos as $video)
                @php
                    $url = \App\Support\SafeUrl::href($video['video_url'] ?? '', '');
                    $thumbnail = \App\Support\SafeUrl::href($video['thumbnail_url'] ?? '', '');

                    if ($thumbnail === '' && $url !== '' && (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be'))) {
                        preg_match('/(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))([\w-]{11})/', $url, $matches);
                        if (!empty($matches[1])) {
                            $thumbnail = "https://img.youtube.com/vi/{$matches[1]}/hqdefault.jpg";
                        }
                    }

                    if ($thumbnail === '') {
                        $thumbnail = 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=600&q=80';
                    }
                @endphp

                <a
                    data-video-card
                    href="{{ $url !== '' ? $url : '#' }}"
                    @if($url !== '') target="_blank" rel="noopener noreferrer" @endif
                    class="group relative block w-[80%] shrink-0 overflow-hidden rounded-2xl bg-slate-200 sm:w-[45%] lg:w-[calc((100%-4.5rem)/3.5)]"
                    @if($url === '') aria-disabled="true" @endif
                >
                    <div class="aspect-[9/11] overflow-hidden">
                        <img
                            src="{{ $thumbnail }}"
                            alt="{{ $video['title'] ?? __('Educational video') }}"
                            class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.03]"
                        >
                        <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white/95 text-slate-900 shadow-lg transition group-hover:scale-110">
                                <svg class="ml-0.5 h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                            </span>
                        </div>
                        @if(!empty($video['title']))
                            <p class="absolute inset-x-0 bottom-0 p-4 text-sm font-semibold text-white line-clamp-2">
                                {{ $video['title'] }}
                            </p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>

        @if($followUrl !== '')
            <div class="mt-6 sm:hidden">
                <a href="{{ $followUrl }}" target="_blank" rel="noopener noreferrer" class="solo-cta-outline w-full">
                    {{ $followText }}
                </a>
            </div>
        @endif
    </div>
</section>

<style>
    .solo-video-track {
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .solo-video-track::-webkit-scrollbar {
        display: none;
    }
</style>
