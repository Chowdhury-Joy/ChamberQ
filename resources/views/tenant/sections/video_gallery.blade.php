@php
    $heading = $data['heading'] ?? __('From our clinic');
    $videos = array_slice($data['videos'] ?? [], 0, 10);
@endphp

<section class="solo-section w-full bg-white">
    <div class="mx-auto max-w-[1280px] px-3 sm:px-10">
        @if(filled($heading))
            <h2 class="font-display text-3xl tracking-tight text-slate-900 sm:text-4xl lg:text-[2.75rem]">
                {{ $heading }}
            </h2>
        @endif

        <x-card-grid :count="count($videos)" class="mt-8 gap-5 sm:gap-6 lg:mt-12">
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

                <article class="flex flex-col overflow-hidden rounded-2xl border" style="background-color: #FAFAFA; border-color: #E0E0E0;">
                    <div class="relative aspect-video overflow-hidden bg-slate-900 group">
                        <img src="{{ $thumbnail }}" alt="{{ $video['title'] ?? 'Video' }}" class="h-full w-full object-cover opacity-90 transition-opacity group-hover:opacity-75">
                        @if($url !== '')
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="absolute inset-0 flex items-center justify-center">
                            <div class="flex h-14 w-14 items-center justify-center rounded-full text-white shadow-lg transition-transform group-hover:scale-110" style="background-color: var(--color-primary);">
                                <svg class="ml-1 h-6 w-6 fill-current" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </div>
                        </a>
                        @endif
                    </div>
                    <div class="p-4">
                        <h3 class="text-base font-semibold text-slate-900 line-clamp-2">{{ $video['title'] ?? __('Video') }}</h3>
                    </div>
                </article>
            @endforeach
        </x-card-grid>
    </div>
</section>
