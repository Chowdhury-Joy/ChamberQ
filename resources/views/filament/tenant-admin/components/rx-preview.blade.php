{{--
    Prescription preview, framed inside the Consult Screen modal.

    The frame loads the same `prescriptions.print` route the printer gets, so
    the preview cannot drift from the printed script.
--}}
<div class="cs-rx-preview" x-data>
    @if ($url)
        <iframe
            x-ref="frame"
            src="{{ $url }}"
            class="cs-rx-preview__frame"
            title="{{ __('Prescription preview') }}"
            loading="lazy"
        ></iframe>

        <div class="cs-rx-preview__actions">
            <x-filament::button
                type="button"
                color="primary"
                icon="heroicon-o-printer"
                x-on:click="$refs.frame.contentWindow.focus(); $refs.frame.contentWindow.print();"
            >
                {{ __('Print') }}
            </x-filament::button>
        </div>
    @else
        <p class="cs-rx-preview__empty">
            {{ __('Add a medicine first — there is nothing to print yet.') }}
        </p>
    @endif
</div>
