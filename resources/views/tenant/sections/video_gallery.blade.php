<section class="w-full py-12 md:py-16 bg-white">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        @if(!empty($data['heading']))
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight text-center mb-10">
                {{ $data['heading'] }}
            </h2>
        @endif

        @php $videos = array_slice($data['videos'] ?? [], 0, 10); @endphp

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($videos as $video)
                @php
                    $url = \App\Support\SafeUrl::href($video['video_url'] ?? '', '');
                    $thumbnail = \App\Support\SafeUrl::href($video['thumbnail_url'] ?? '', '');
                    
                    // Auto YouTube thumbnail extraction
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

                <div class="bg-slate-50 rounded-2xl overflow-hidden border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <div class="relative aspect-video bg-slate-900 overflow-hidden group">
                        <img src="{{ $thumbnail }}" alt="{{ $video['title'] ?? 'Video' }}" class="w-full h-full object-cover opacity-90 group-hover:opacity-75 transition-opacity">
                        @if($url !== '')
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="absolute inset-0 flex items-center justify-center">
                            <div class="w-14 h-14 rounded-full bg-sky-500 text-white flex items-center justify-center shadow-lg shadow-sky-500/50 group-hover:scale-110 transition-transform">
                                <svg class="w-6 h-6 fill-current ml-1" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            </div>
                        </a>
                        @endif
                    </div>
                    <div class="p-4">
                        <h3 class="text-base font-bold text-slate-900 line-clamp-2">{{ $video['title'] ?? 'Video Showcase' }}</h3>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
