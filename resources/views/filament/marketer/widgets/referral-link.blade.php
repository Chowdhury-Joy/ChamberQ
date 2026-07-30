<div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Your referral link</h2>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Share this link with doctors. When they contact us on WhatsApp, we attach the referral to their account.
    </p>

    @if ($this->getReferralUrl())
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <code class="flex-1 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-800 dark:bg-gray-800 dark:text-gray-100 break-all">
                {{ $this->getReferralUrl() }}
            </code>
            <button
                type="button"
                x-data="{ copied: false }"
                x-on:click="
                    navigator.clipboard.writeText(@js($this->getReferralUrl()));
                    copied = true;
                    setTimeout(() => copied = false, 2000);
                "
                class="fi-btn fi-btn-size-md fi-color-primary inline-flex items-center justify-center gap-1 rounded-lg px-4 py-2 text-sm font-semibold"
            >
                <span x-show="!copied">Copy link</span>
                <span x-show="copied" x-cloak>Copied!</span>
            </button>
        </div>
        <p class="mt-2 text-xs text-gray-500">Ref code: <strong>{{ $this->getReferralCode() }}</strong></p>
    @else
        <p class="mt-4 text-sm text-amber-600">Your partner profile is not set up yet. Contact the platform admin.</p>
    @endif
</div>
