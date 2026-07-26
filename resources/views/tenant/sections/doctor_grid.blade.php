@if(tenant()?->isClinic())
<section class="w-full py-12 md:py-16 bg-white">
    <div class="max-w-[1320px] mx-auto px-4 md:px-6 xl:px-8">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <h2 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight mb-3">
                {{ $data['heading'] ?? 'Meet Our Medical Team' }}
            </h2>
            @if(!empty($data['subheadline']))
                <p class="text-sm sm:text-base text-slate-600">{{ $data['subheadline'] }}</p>
            @endif
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($doctors as $doctor)
                <div class="bg-slate-50 rounded-2xl p-6 border border-slate-200/80 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="w-16 h-16 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center font-bold text-xl mb-4">
                            👨‍⚕️
                        </div>
                        <h3 class="text-lg font-bold text-slate-900">{{ $doctor->name }}</h3>
                        <p class="text-xs font-semibold text-sky-600 uppercase tracking-wider mb-2">
                            {{ $doctor->specialty ?? 'Specialist Physician' }}
                        </p>
                        <p class="text-sm text-slate-600 mb-4">
                            {{ $doctor->qualification ?? 'MD / Medical Specialist' }}
                        </p>
                    </div>

                    <a href="/book?doctor_id={{ $doctor->id }}" class="btn btn-primary w-full text-center text-sm">
                        Book Consultation
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
