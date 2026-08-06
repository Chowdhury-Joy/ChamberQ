<section id="doctors" class="solo-section w-full bg-white">
    <div class="mx-auto max-w-[1280px] px-3 sm:px-10">
        <div class="mx-auto mb-8 max-w-2xl text-center sm:mb-10">
            <h2 class="font-display text-3xl tracking-tight text-slate-900 sm:text-4xl lg:text-[2.75rem]">
                {{ $data['heading'] ?? __('Meet Our Medical Team') }}
            </h2>
            @if(!empty($data['subheadline']))
                <p class="mt-3 text-sm leading-relaxed text-slate-600 sm:text-base lg:text-lg">{{ $data['subheadline'] }}</p>
            @endif
        </div>

        <x-card-grid
            :count="$doctors->count()"
            @class([
                'gap-5 sm:gap-6',
                'mx-auto max-w-md' => $doctors->count() === 1,
            ])
        >
            @forelse($doctors as $doctor)
                <article class="flex flex-col justify-between rounded-2xl border p-5 sm:p-6" style="background-color: #FAFAFA; border-color: #E0E0E0;">
                    <div>
                        <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold" style="background-color: color-mix(in srgb, var(--color-primary) 14%, white); color: var(--color-primary);">
                            {{ mb_strtoupper(mb_substr($doctor->name, 0, 1)) }}
                        </div>
                        <h3 class="font-display text-2xl text-slate-900 sm:text-[1.75rem]">{{ $doctor->name }}</h3>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wider sm:text-sm" style="color: var(--color-primary);">
                            {{ $doctor->specialty ?? __('Specialist Physician') }}
                        </p>
                        @if(filled($doctor->qualification ?? null))
                            <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ $doctor->qualification }}</p>
                        @endif
                    </div>

                    <a href="{{ tenant_web_url('/book?doctor='.$doctor->id) }}" class="solo-cta mt-6 w-full">
                        {{ __('Book Consultation') }}
                    </a>
                </article>
            @empty
                <p class="col-span-full text-center text-slate-500">{{ __('No doctors listed yet.') }}</p>
            @endforelse
        </x-card-grid>
    </div>
</section>
