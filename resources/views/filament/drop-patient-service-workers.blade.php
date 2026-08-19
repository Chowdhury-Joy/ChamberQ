{{-- Patient PWA scope is /{tenant}/, which also covers the staff desk. --}}
<script>
    (() => {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        navigator.serviceWorker.getRegistrations().then((regs) => {
            regs.forEach((reg) => { reg.unregister(); });
        }).catch(() => {});

        if ('caches' in window) {
            caches.keys().then((names) => {
                names.filter((n) => n.startsWith('clinic-shell-')).forEach((n) => caches.delete(n));
            }).catch(() => {});
        }
    })();
</script>
