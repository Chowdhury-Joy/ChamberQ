<section class="w-full bg-slate-50 py-10 sm:py-14 lg:py-16">
    <div class="mx-auto max-w-[1320px] px-4 sm:px-6 lg:px-8">
        <div class="mx-auto mb-8 max-w-2xl text-center sm:mb-10">
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl lg:text-4xl">
                {{ $data['heading'] ?? __('Meet Our Medical Team') }}
            </h2>
            @if(!empty($data['subheadline']))
                <p class="mt-2 text-sm leading-relaxed text-slate-600 sm:mt-3 sm:text-base lg:text-lg">{{ $data['subheadline'] }}</p>
            @endif
        </div>

        <div @class([
            'grid gap-5 sm:gap-6 lg:gap-8',
            'mx-auto max-w-md grid-cols-1' => $doctors->count() === 1,
            'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3' => $doctors->count() !== 1,
        ])>
            @forelse($doctors as $doctor)
                <div class="flex flex-col justify-between rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm sm:p-6 lg:p-7">
                    <div>
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold" style="background-color: color-mix(in srgb, var(--color-primary) 14%, white); color: var(--color-primary);">
                            {{ mb_strtoupper(mb_substr($doctor->name, 0, 1)) }}
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 sm:text-xl">{{ $doctor->name }}</h3>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wider sm:text-sm" style="color: var(--color-primary);">
                            {{ $doctor->specialty ?? __('Specialist Physician') }}
                        </p>
                        @if(filled($doctor->qualification ?? null))
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $doctor->qualification }}</p>
                        @endif
                    </div>

                    <a href="{{ tenant_web_url('/book?doctor='.$doctor->id) }}" class="mt-6 inline-flex w-full items-center justify-center rounded-xl px-4 py-3 text-sm font-semibold text-white transition hover:opacity-90" style="background-color: var(--color-primary);">
                        {{ __('Book Consultation') }}
                    </a>
                </div>
            @empty
                <p class="col-span-full text-center text-slate-500">{{ __('No doctors listed yet.') }}</p>
            @endforelse
        </div>
    </div>
</section>
