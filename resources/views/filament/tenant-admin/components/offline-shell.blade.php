{{--
    Shared offline banner + JS config for every tenant-admin page.
    The pad and Visiting / camp read ChamberQOffline; queue screens allow
    Call next offline on this computer via chamberq-queue-offline.js.
--}}
@php
    $user = auth()->user();
    $canRx = $user?->canRecordVisitNotes() ?? false;
    $canQueue = $user?->canOperateQueueControls() ?? false;
    $enabled = $canRx || $canQueue;
    $visitingUrl = null;
    $queueUrl = null;
    if ($enabled) {
        try {
            $visitingUrl = \App\Filament\TenantAdmin\Pages\VisitingDay::getUrl();
        } catch (\Throwable $e) {
            $visitingUrl = null;
        }
    }
    if ($canQueue) {
        $live = \App\Models\LiveSession::query()
            ->where('session_date', now()->toDateString())
            ->orderByDesc('started_at')
            ->first();
        $sessionId = $live?->schedule_session_id
            ?? \App\Models\ScheduleSession::query()
                ->where('day_of_week', now()->dayOfWeek)
                ->orderBy('start_time')
                ->value('id');
        if ($sessionId) {
            $queueUrl = tenant_web_url('/api/offline/queue/'.$sessionId);
        }
    }
@endphp

@if ($enabled)
    <div
        id="cq-offline-banner"
        hidden
        role="status"
        style="margin: 0 1rem 0.75rem; padding: 0.75rem 1rem; border-radius: 0.625rem; border: 1px solid var(--warning-300, #fcd34d); background: color-mix(in srgb, var(--warning-50, #fffbeb) 88%, transparent); font-size: 0.875rem; color: var(--warning-950, #78350f);"
    >
        <strong id="cq-offline-banner-title"></strong>
        <span id="cq-offline-banner-body"></span>
        <button
            type="button"
            id="cq-offline-sync-btn"
            hidden
            style="margin-left: 0.75rem; font-weight: 600; text-decoration: underline; background: none; border: 0; cursor: pointer; color: inherit;"
        >{{ __('Upload now') }}</button>
    </div>
    <script>
        window.ChamberQOfflineConfig = {
            bagUrl: @json($canRx ? tenant_web_url('/api/offline/bag') : null),
            syncUrl: @json(tenant_web_url('/api/offline/sync')),
            queueUrl: @json($queueUrl),
            visitingUrl: @json($visitingUrl),
        };
    </script>
    <script src="{{ asset('js/chamberq-offline.js') }}"></script>
    @if ($canQueue)
        <script src="{{ asset('js/chamberq-queue-offline.js') }}"></script>
    @endif
    <style>
        body.cq-is-offline .cq-freeze-queue .fi-btn:not(.cq-offline-queue-allowed),
        body.cq-is-offline .cq-needs-network {
            pointer-events: none !important;
            opacity: 0.45 !important;
        }
    </style>
@endif
