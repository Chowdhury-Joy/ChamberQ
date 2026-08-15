/**
 * Offline Call next on this computer — local queue walk + replay on reconnect.
 */
(() => {
    const META_KEY = 'queue_snapshot';

    const state = {
        snapshot: null,
        conflict: false,
    };

    function config() {
        return window.ChamberQOfflineConfig || {};
    }

    async function setMeta(value) {
        await window.ChamberQOffline.setMeta(META_KEY, value);
    }

    async function getMeta() {
        return window.ChamberQOffline.getMeta(META_KEY);
    }

    function bookingById(id) {
        return (state.snapshot?.bookings || []).find((b) => b.id === id) || null;
    }

    function currentBooking() {
        const id = state.snapshot?.current_booking_id;
        return id ? bookingById(id) : null;
    }

    function publishedStillActive() {
        if (state.snapshot?.published_still_active != null) {
            return !!state.snapshot.published_still_active;
        }
        return (state.snapshot?.bookings || []).some((b) =>
            !b.is_overflow && ['waiting', 'called', 'skipped', 'in_chamber'].includes(b.status)
        );
    }

    function pickNextBooking() {
        const snap = state.snapshot;
        if (!snap) return null;

        const current = currentBooking();
        const currentSerial = current?.serial_number ?? null;

        const retries = (snap.bookings || [])
            .filter((b) => b.status === 'skipped' && b.retry_queue_position != null)
            .filter((b) => currentSerial === null || b.retry_queue_position <= currentSerial + 1)
            .sort((a, b) => a.retry_queue_position - b.retry_queue_position);

        if (retries.length) {
            return retries[0];
        }

        let waiting = (snap.bookings || [])
            .filter((b) => b.status === 'waiting');

        if (publishedStillActive()) {
            waiting = waiting.filter((b) => !b.is_overflow);
        }

        if (currentSerial != null) {
            waiting = waiting.filter((b) => b.serial_number > currentSerial);
        }

        waiting.sort((a, b) => a.serial_number - b.serial_number);
        if (waiting.length) {
            return waiting[0];
        }

        const anyRetry = (snap.bookings || [])
            .filter((b) => b.status === 'skipped' && b.retry_queue_position != null)
            .sort((a, b) => a.retry_queue_position - b.retry_queue_position);

        return anyRetry[0] || null;
    }

    function rebuildScreenFields() {
        const current = currentBooking();
        const next = (() => {
            const waiting = (state.snapshot?.bookings || [])
                .filter((b) => b.status === 'waiting')
                .filter((b) => !current || b.serial_number > current.serial_number)
                .sort((a, b) => a.serial_number - b.serial_number);
            return waiting[0] || null;
        })();

        state.snapshot.screen = {
            ...(state.snapshot.screen || {}),
            status: state.snapshot.status,
            session_date: state.snapshot.session_date,
            now_serving: current?.serial_number ?? null,
            now_serving_name: current?.patient_name ?? null,
            is_called: current?.status === 'called',
            called_at: current?.status === 'called' ? (current.called_at || new Date().toISOString()) : null,
            next_booking: next?.serial_number ?? null,
            next_estimated_time: state.snapshot.screen?.next_estimated_time ?? null,
        };
    }

    function setCurrent(booking) {
        const now = new Date().toISOString();
        state.snapshot.current_booking_id = booking.id;
        state.snapshot.current_called_at = now;
        booking.status = 'called';
        booking.called_at = now;
        booking.retry_queue_position = null;
        rebuildScreenFields();
        persistSnapshot();
        window.dispatchEvent(new CustomEvent('cq-queue-screen', { detail: state.snapshot.screen }));
        window.dispatchEvent(new CustomEvent('cq-queue-local-change', { detail: state.snapshot }));
    }

    function clearCurrent() {
        state.snapshot.current_booking_id = null;
        state.snapshot.current_called_at = null;
        rebuildScreenFields();
        persistSnapshot();
        window.dispatchEvent(new CustomEvent('cq-queue-screen', { detail: state.snapshot.screen }));
        window.dispatchEvent(new CustomEvent('cq-queue-local-change', { detail: state.snapshot }));
    }

    async function persistSnapshot() {
        await setMeta(state.snapshot);
    }

    async function loadSnapshot() {
        state.snapshot = await getMeta();
        return state.snapshot;
    }

    async function packQueue() {
        const url = config().queueUrl;
        if (!url) throw new Error('Missing queue URL');
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Could not pack the queue');
        const snap = await res.json();
        state.snapshot = snap;
        await persistSnapshot();
        window.dispatchEvent(new CustomEvent('cq-queue-local-change', { detail: snap }));
        return snap;
    }

    function expectedCurrentId() {
        return state.snapshot?.current_booking_id ?? null;
    }

    async function enqueueAction(type, expected) {
        if (!state.snapshot?.live_session_id) return;
        await window.ChamberQOffline.enqueue({
            type,
            live_session_id: state.snapshot.live_session_id,
            expected_current_booking_id: expected,
        });
    }

    async function applyCallNext() {
        if (!state.snapshot || state.snapshot.status === 'paused') return false;
        const expected = expectedCurrentId();
        const next = pickNextBooking();

        await enqueueAction('queue_call_next', expected);

        if (next) {
            setCurrent(next);
            announceIfPossible(next);
        } else {
            clearCurrent();
        }

        return true;
    }

    async function applyPatientArrived() {
        const current = currentBooking();
        if (!current || current.status !== 'called') return false;
        const expected = expectedCurrentId();

        await enqueueAction('queue_patient_arrived', expected);

        current.status = 'in_chamber';
        rebuildScreenFields();
        await persistSnapshot();
        window.dispatchEvent(new CustomEvent('cq-queue-local-change', { detail: state.snapshot }));

        return true;
    }

    async function applySkip() {
        const current = currentBooking();
        if (!current || current.status !== 'called') return false;
        const expected = expectedCurrentId();

        await enqueueAction('queue_skip', expected);

        const skipCount = (current.skip_count || 0) + 1;
        current.skip_count = skipCount;

        if (skipCount > 2) {
            current.status = 'no_show';
        } else {
            current.status = 'skipped';
            current.retry_queue_position = current.serial_number + 2;
        }

        const next = pickNextBooking();
        if (next) {
            setCurrent(next);
            announceIfPossible(next);
        } else {
            clearCurrent();
        }

        return true;
    }

    async function applyCompleteWithoutAdvance() {
        const current = currentBooking();
        if (!current || !['called', 'in_chamber'].includes(current.status)) return false;
        const expected = expectedCurrentId();

        await enqueueAction('queue_complete_without_advance', expected);

        current.status = 'completed';
        rebuildScreenFields();
        await persistSnapshot();
        window.dispatchEvent(new CustomEvent('cq-queue-local-change', { detail: state.snapshot }));

        return true;
    }

    function announceIfPossible(booking) {
        if (!booking) return;
        window.dispatchEvent(new CustomEvent('queue-called', {
            detail: { serial: booking.serial_number, name: booking.patient_name },
        }));
    }

    const handlers = {
        call_next: applyCallNext,
        patient_arrived: applyPatientArrived,
        skip: applySkip,
        complete_without_advance: applyCompleteWithoutAdvance,
    };

    function handleOfflineClick(event) {
        const btn = event.target.closest('[data-cq-queue-action]');
        if (!btn) return;
        if (window.ChamberQOffline?.isLikelyOnline?.()) return;

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        if (state.conflict) {
            alert('Refresh — the line moved elsewhere.');
            return;
        }

        const action = btn.getAttribute('data-cq-queue-action');
        const fn = handlers[action];
        if (!fn) return;

        fn().catch(() => {});
    }

    function onFlushResults(results) {
        const conflict = (results || []).find((r) => r.conflict && r.halt);
        if (conflict) {
            state.conflict = true;
            const el = document.getElementById('cq-offline-banner');
            const title = document.getElementById('cq-offline-banner-title');
            const body = document.getElementById('cq-offline-banner-body');
            if (el && title && body) {
                el.hidden = false;
                title.textContent = 'Queue conflict. ';
                body.textContent = conflict.error || 'Refresh — the line moved elsewhere.';
            }
        } else if (window.ChamberQOffline?.isLikelyOnline?.()) {
            packQueue().catch(() => {});
        }
    }

    async function boot() {
        await loadSnapshot();

        document.addEventListener('click', handleOfflineClick, true);

        window.ChamberQOffline?.onChange?.((snap) => {
            if (snap.online && !state.conflict) {
                packQueue().catch(() => {});
            }
        });

        const originalFlush = window.ChamberQOffline?.flush;
        if (originalFlush) {
            window.ChamberQOffline.flush = async function patchedFlush() {
                const results = await originalFlush.call(window.ChamberQOffline);
                onFlushResults(results);
                return results;
            };
        }

        if (window.ChamberQOffline?.isLikelyOnline?.()) {
            packQueue().catch(() => {});
        }
    }

    window.ChamberQQueueOffline = {
        boot,
        packQueue,
        loadSnapshot,
        getSnapshot: () => state.snapshot,
        getScreenPayload: () => state.snapshot?.screen || null,
        hasConflict: () => state.conflict,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
