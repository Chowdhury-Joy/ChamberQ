<x-filament-panels::page>
    <div class="space-y-6">
        
        {{-- Session Selector --}}
        <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
            <h3 class="text-lg font-medium mb-4">Select Session for Today</h3>
            <div class="flex gap-4 items-end">
                <div class="flex-1">
                    <select wire:model.live="selectedSessionId" class="w-full rounded-lg border-gray-300 dark:bg-gray-900 dark:border-gray-600">
                        <option value="">-- Choose a Session --</option>
                        @foreach($this->sessions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if($this->selectedSessionId)
            @php
                $liveSession = $this->activeLiveSession;
                $bookings = $this->bookings;
                $currentBookingId = $liveSession?->current_booking_id;
                $status = $liveSession?->status ?? 'scheduled';
            @endphp

            {{-- Main Control Panel --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                {{-- Status & Quick Actions --}}
                <div class="md:col-span-1 space-y-4">
                    <div class="p-6 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700">
                        <h3 class="text-lg font-medium mb-2">Session Status</h3>
                        
                        <div class="mb-6">
                            @if($status === 'scheduled')
                                <span class="px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 text-gray-800">Scheduled</span>
                            @elseif($status === 'delayed')
                                <span class="px-3 py-1 rounded-full text-sm font-semibold bg-warning-100 text-warning-800">Delayed ({{ $liveSession->delay_minutes }}m)</span>
                            @elseif($status === 'active')
                                <span class="px-3 py-1 rounded-full text-sm font-semibold bg-success-100 text-success-800 animate-pulse">Live</span>
                            @elseif($status === 'paused')
                                <span class="px-3 py-1 rounded-full text-sm font-semibold bg-gray-100 text-gray-800">Paused</span>
                            @elseif($status === 'completed')
                                <span class="px-3 py-1 rounded-full text-sm font-semibold bg-primary-100 text-primary-800">Completed</span>
                            @elseif($status === 'cancelled')
                                <span class="px-3 py-1 rounded-full text-sm font-semibold bg-danger-100 text-danger-800">Cancelled</span>
                            @endif
                        </div>

                        <div class="space-y-3">
                            @if(in_array($status, ['scheduled', 'delayed']))
                                <button wire:click="startSession" class="w-full flex justify-center items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg font-medium">
                                    <x-heroicon-o-play class="w-5 h-5"/> Start Session
                                </button>
                            @endif

                            @if($status === 'active')
                                <div class="p-4 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 mb-4">
                                    <div class="text-sm text-gray-500">Currently Serving</div>
                                    @if($liveSession->currentBooking)
                                        <div class="text-2xl font-bold">#{{ $liveSession->currentBooking->serial_number }}</div>
                                        <div class="font-medium mt-1">{{ $liveSession->currentBooking->patient_name }}</div>
                                        
                                        @if($liveSession->currentBooking->status === 'called')
                                            <div class="mt-2 text-warning-600 text-sm flex items-center gap-1">
                                                <x-heroicon-m-bell-alert class="w-4 h-4 animate-bounce" /> Waiting for patient...
                                            </div>
                                        @elseif($liveSession->currentBooking->status === 'in_chamber')
                                            <div class="mt-2 text-success-600 text-sm flex items-center gap-1">
                                                <x-heroicon-m-check-circle class="w-4 h-4" /> In Chamber
                                            </div>
                                        @endif
                                    @else
                                        <div class="text-lg text-gray-400">None</div>
                                    @endif
                                </div>

                                @if($liveSession->currentBooking)
                                    @if($liveSession->currentBooking->status === 'called')
                                        <button wire:click="patientArrived" class="w-full flex justify-center items-center gap-2 px-4 py-2 bg-success-600 hover:bg-success-500 text-white rounded-lg font-medium">
                                            <x-heroicon-o-check class="w-5 h-5"/> Patient Arrived
                                        </button>
                                        
                                        @if($liveSession->isCallTimedOut())
                                            <button wire:click="skipPatient" class="w-full flex justify-center items-center gap-2 px-4 py-2 bg-danger-600 hover:bg-danger-500 text-white rounded-lg font-medium mt-2 animate-pulse shadow-[0_0_15px_rgba(220,38,38,0.5)]">
                                                <x-heroicon-o-forward class="w-5 h-5"/> Skip (No Show)
                                            </button>
                                        @else
                                            <button wire:click="skipPatient" class="w-full flex justify-center items-center gap-2 px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg font-medium mt-2">
                                                <x-heroicon-o-forward class="w-5 h-5"/> Skip (No Show)
                                            </button>
                                        @endif
                                    @elseif($liveSession->currentBooking->status === 'in_chamber')
                                        <button wire:click="nextPatient" class="w-full flex justify-center items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg font-medium">
                                            <x-heroicon-o-forward class="w-5 h-5"/> Complete & Next
                                        </button>
                                    @endif
                                @else
                                    <button wire:click="nextPatient" class="w-full flex justify-center items-center gap-2 px-4 py-2 bg-primary-600 hover:bg-primary-500 text-white rounded-lg font-medium">
                                        <x-heroicon-o-forward class="w-5 h-5"/> Call Next Patient
                                    </button>
                                @endif
                                
                            @endif

                            <div class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-2">
                                {{ $this->markLateAction }}
                                {{ $this->pauseSessionAction }}
                                {{ $this->resumeSessionAction }}
                                {{ $this->markAbsentAction }}
                                
                                @if(in_array($status, ['active', 'paused']))
                                    <button wire:click="endSession" class="w-full flex justify-center items-center gap-2 px-4 py-2 border-2 border-danger-600 text-danger-600 hover:bg-danger-50 rounded-lg font-medium mt-4">
                                        <x-heroicon-o-flag class="w-5 h-5"/> End Session
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 text-sm">
                        <div class="flex justify-between mb-2">
                            <span class="text-gray-500">Screen URL:</span>
                            <a href="{{ route('tenant.screen', ['session' => $this->selectedSessionId, 'date' => now()->format('Y-m-d')]) }}" target="_blank" class="text-primary-600 hover:underline flex items-center gap-1">
                                Open Screen <x-heroicon-m-arrow-top-right-on-square class="w-4 h-4"/>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Queue List --}}
                <div class="md:col-span-2">
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 dark:bg-gray-800 dark:border-gray-700 overflow-hidden">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="px-4 py-3 font-medium text-gray-500">Serial</th>
                                    <th class="px-4 py-3 font-medium text-gray-500">Patient</th>
                                    <th class="px-4 py-3 font-medium text-gray-500">Status</th>
                                    <th class="px-4 py-3 font-medium text-gray-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($bookings as $booking)
                                    <tr class="@if($currentBookingId === $booking->id) bg-primary-50 dark:bg-primary-900/20 @endif">
                                        <td class="px-4 py-3 font-medium">#{{ $booking->serial_number }}</td>
                                        <td class="px-4 py-3">
                                            {{ $booking->patient_name }}
                                            <div class="text-xs text-gray-500">{{ $booking->patient_phone }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($booking->status === 'waiting')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">Waiting</span>
                                            @elseif($booking->status === 'called')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-warning-100 text-warning-800">Called</span>
                                            @elseif($booking->status === 'in_chamber')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-success-100 text-success-800">In Chamber</span>
                                            @elseif($booking->status === 'completed')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800">Completed</span>
                                            @elseif($booking->status === 'skipped')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-800">Skipped ({{ $booking->skip_count }}/2)</span>
                                            @elseif($booking->status === 'no_show')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-danger-100 text-danger-800">No Show</span>
                                            @elseif($booking->status === 'cancelled')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-danger-100 text-danger-800">Cancelled</span>
                                            @endif
                                            
                                            @if($booking->retry_queue_position)
                                                <div class="text-xs text-orange-600 mt-1">Retrying after #{{ $booking->retry_queue_position - 1 }}</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($booking->status === 'no_show')
                                                <button wire:click="reinstatePatient('{{ $booking->id }}')" class="text-sm text-primary-600 hover:underline">Reinstate</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-500">No bookings for this session today.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
    
    <div wire:poll.3s></div>
</x-filament-panels::page>
