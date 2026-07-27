<x-filament-panels::page>
    <div class="space-y-6">
        
        {{-- Session Selector & Header Actions --}}
        <div style="margin-bottom: 24px;">
            <x-filament::section>
                @if($this->sessions->isEmpty())
                    <div class="py-8 text-center">
                        <div class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">
                            No sessions scheduled for today
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-md mx-auto">
                            Live Queue only lists sessions that run on {{ now()->translatedFormat('l') }}. Add or edit a schedule that includes today, then return here.
                        </p>
                        @if(auth()->user()?->canManageOps())
                            <x-filament::button
                                href="{{ \App\Filament\TenantAdmin\Resources\ScheduleSessions\ScheduleSessionResource::getUrl('index') }}"
                                tag="a"
                                color="primary"
                                icon="heroicon-m-calendar-days"
                            >
                                Manage schedules
                            </x-filament::button>
                        @endif
                    </div>
                @else
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
                    <div class="flex-1 max-w-xl">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            Select Session for Today ({{ now()->translatedFormat('l, j F Y') }})
                        </label>
                        <select wire:model.live="selectedSessionId" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="">-- Choose a Session --</option>
                            @foreach($this->sessions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    @if($this->selectedSessionId && app()->isLocal())
                        <div>
                            <x-filament::button wire:click="addMockPatients" color="gray" icon="heroicon-m-user-plus">
                                + Add Sample Patients
                            </x-filament::button>
                        </div>
                    @endif
                </div>
                @endif
            </x-filament::section>
        </div>

        @if($this->selectedSessionId)
            @php
                $liveSession = $this->activeLiveSession;
                $bookings = $this->bookings;
                $currentBookingId = $liveSession?->current_booking_id;
                $status = $liveSession?->status ?? 'scheduled';
            @endphp

            {{-- Main Control Grid --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6" style="gap: 24px;">
                
                {{-- Left Column: Session Controls & Currently Serving --}}
                <div class="lg:col-span-1 space-y-6" style="display: flex; flex-direction: column; gap: 24px;">
                    
                    {{-- Status & Quick Control Card --}}
                    <x-filament::section>
                        <x-slot name="heading">
                            Session Status
                        </x-slot>
                        <x-slot name="headerEnd">
                            @if($status === 'scheduled')
                                <x-filament::badge color="gray">Scheduled</x-filament::badge>
                            @elseif($status === 'delayed')
                                <x-filament::badge color="warning">Delayed ({{ $liveSession->delay_minutes }}m)</x-filament::badge>
                            @elseif($status === 'active')
                                <x-filament::badge color="success">● Live Active</x-filament::badge>
                            @elseif($status === 'paused')
                                <x-filament::badge color="gray">Paused</x-filament::badge>
                            @elseif($status === 'completed')
                                <x-filament::badge color="info">Completed</x-filament::badge>
                            @elseif($status === 'cancelled')
                                <x-filament::badge color="danger">Cancelled</x-filament::badge>
                            @endif
                        </x-slot>

                        <div class="space-y-4">
                            @if(in_array($status, ['scheduled', 'delayed']))
                                <x-filament::button wire:click="startSession" color="success" icon="heroicon-m-play" size="lg" class="w-full">
                                    Start Live Session
                                </x-filament::button>
                            @endif

                            @if($status === 'active')
                                {{-- Currently Serving Box --}}
                                <div class="p-6 bg-gray-50 dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800">
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mb-4">Currently serving</div>
                                    
                                    @if($liveSession->currentBooking)
                                        <div class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                                            #{{ $liveSession->currentBooking->serial_number }}
                                        </div>
                                        
                                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
                                            {{-- Patient Info & Badge --}}
                                            <div style="display: flex; align-items: center; gap: 1rem;">
                                                <div class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $liveSession->currentBooking->patient_name }}
                                                </div>
                                                @if($liveSession->currentBooking->status === 'called')
                                                    <x-filament::badge color="warning" icon="heroicon-m-bell-alert">
                                                        Called — Waiting for Patient
                                                    </x-filament::badge>
                                                @elseif($liveSession->currentBooking->status === 'in_chamber')
                                                    <x-filament::badge color="success" icon="heroicon-m-check-circle">
                                                        Inside Doctor Chamber
                                                    </x-filament::badge>
                                                @endif
                                            </div>

                                            {{-- Action Buttons --}}
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                @if($liveSession->currentBooking->status === 'called')
                                                    <x-filament::button wire:click="patientArrived" color="success" icon="heroicon-m-check">
                                                        Patient arrived
                                                    </x-filament::button>
                                                    
                                                    @if($liveSession->isCallTimedOut())
                                                        <x-filament::button wire:click="skipPatient" color="danger" icon="heroicon-m-forward">
                                                            Timeout
                                                        </x-filament::button>
                                                    @else
                                                        <x-filament::button wire:click="skipPatient" color="gray" icon="heroicon-m-forward">
                                                            Timeout
                                                        </x-filament::button>
                                                    @endif
                                                @elseif($liveSession->currentBooking->status === 'in_chamber')
                                                    <x-filament::button wire:click="nextPatient" color="primary" icon="heroicon-m-check-badge" class="!text-white shadow-sm">
                                                        Complete & Call Next Patient
                                                    </x-filament::button>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem;">
                                            <div class="text-lg font-medium text-gray-400">No Active Call</div>
                                            <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                <x-filament::button wire:click="nextPatient" color="primary" icon="heroicon-m-megaphone" class="!text-white shadow-sm">
                                                    Call Next Patient (#{{ optional($bookings->where('status', 'waiting')->first())->serial_number ?? 'End' }})
                                                </x-filament::button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </x-filament::section>

                    {{-- TV Screen Link Widget --}}
                    <x-filament::section>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2 font-medium">
                                <x-filament::icon icon="heroicon-m-tv" class="w-5 h-5 text-primary-500" style="width: 1.25rem; height: 1.25rem;" />
                                Outdoor TV Screen
                            </div>
                            <x-filament::button href="{{ route('tenant.screen', ['session' => $this->selectedSessionId, 'date' => now()->format('Y-m-d')]) }}" tag="a" target="_blank" color="gray" icon="heroicon-m-arrow-top-right-on-square" size="sm">
                                Open Screen
                            </x-filament::button>
                        </div>
                    </x-filament::section>
                </div>

                {{-- Right Column: Queue Table --}}
                <div class="lg:col-span-2">
                    {{ $this->table }}
                </div>
            </div>
        @elseif($this->sessions->isNotEmpty())
            <x-filament::section>
                <div class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Choose a session above to open today’s live queue.
                </div>
            </x-filament::section>
        @endif
    </div>

    {{-- Filament Action Modals --}}
    <x-filament-actions::modals />

</x-filament-panels::page>
