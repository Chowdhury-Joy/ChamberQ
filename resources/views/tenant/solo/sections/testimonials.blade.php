@php
    $heading = $data['heading'] ?? __('What My Patients Say About My Treatments');
    $items = $data['items'] ?? [];
@endphp

<section class="solo-section w-full bg-black text-white">
    <div class="mx-auto max-w-[1280px] px-3 sm:px-10">
        <h2 class="solo-h2 max-w-3xl">
            {{ $heading }}
        </h2>

        <x-card-grid :count="count($items)" class="mt-8 gap-6 lg:mt-12">
            @foreach($items as $item)
                <article class="flex min-h-[18rem] flex-col justify-between rounded-2xl bg-[#1a1a1a] p-6 sm:min-h-[22rem] sm:p-8">
                    <p class="solo-body text-white/90">{{ $item['quote'] ?? '' }}</p>
                    <div class="mt-8">
                        <p class="solo-label text-white">{{ $item['name'] ?? '' }}</p>
                        <p class="solo-body-sm mt-1 text-white/50">{{ $item['label'] ?? __('Verified Patient') }}</p>
                    </div>
                </article>
            @endforeach
        </x-card-grid>
    </div>
</section>
