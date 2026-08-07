<div
    x-data="visitVoiceRecorder({
        uploadUrl: @js(tenant_web_url('/api/visit-media/upload-voice')),
        maxSeconds: 20,
    })"
    class="cs-voice-recorder"
    x-ref="recorderRoot"
>
    <div class="cs-voice-recorder__title">
        {{ __('Voice note') }}
    </div>
    <p class="cs-voice-recorder__hint">
        {{ __('Record up to 20 seconds. The audio is saved with the visit and only you can play it back.') }}
    </p>

    <div class="cs-voice-recorder__controls">
        <button
            type="button"
            class="fi-btn fi-btn-size-sm fi-color-primary cs-voice-recorder__btn"
            x-show="!recording && !uploading"
            x-on:click="startRecording()"
            :disabled="uploading"
        >
            {{ __('Record') }}
        </button>
        <button
            type="button"
            class="fi-btn fi-btn-size-sm fi-color-danger cs-voice-recorder__btn"
            x-show="recording"
            x-on:click="stopRecording()"
        >
            {{ __('Stop') }}
        </button>
        <span class="cs-voice-recorder__status" x-show="recording">
            {{ __('Recording…') }} <span x-text="elapsed"></span>s
        </span>
        <span class="cs-voice-recorder__saved" x-show="uploadedPath && !recording">
            {{ __('Voice saved') }}
        </span>
        <span class="cs-voice-recorder__error" x-show="errorMessage" x-text="errorMessage"></span>
    </div>

    <audio
        x-ref="preview"
        controls
        class="cs-voice-recorder__audio"
        x-show="previewUrl"
        :src="previewUrl"
    ></audio>
</div>

@script
<script>
    Alpine.data('visitVoiceRecorder', (config) => ({
        uploadUrl: config.uploadUrl,
        maxSeconds: config.maxSeconds || 20,
        recording: false,
        uploading: false,
        elapsed: 0,
        previewUrl: null,
        uploadedPath: null,
        errorMessage: null,
        mediaRecorder: null,
        chunks: [],
        timer: null,
        stream: null,

        async startRecording() {
            this.errorMessage = null;
            this.previewUrl = null;
            this.uploadedPath = null;

            if (!navigator.mediaDevices?.getUserMedia) {
                this.errorMessage = @js(__('Microphone not available in this browser.'));
                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.chunks = [];
                this.mediaRecorder = new MediaRecorder(this.stream);
                this.mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        this.chunks.push(event.data);
                    }
                };
                this.mediaRecorder.onstop = () => this.handleRecordingStop();
                this.mediaRecorder.start();
                this.recording = true;
                this.elapsed = 0;
                this.timer = setInterval(() => {
                    this.elapsed += 1;
                    if (this.elapsed >= this.maxSeconds) {
                        this.stopRecording();
                    }
                }, 1000);
            } catch (error) {
                this.errorMessage = @js(__('Could not access microphone.'));
            }
        },

        stopRecording() {
            if (!this.recording) {
                return;
            }

            this.recording = false;
            clearInterval(this.timer);

            if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                this.mediaRecorder.stop();
            }

            if (this.stream) {
                this.stream.getTracks().forEach((track) => track.stop());
            }
        },

        async handleRecordingStop() {
            const blob = new Blob(this.chunks, { type: 'audio/webm' });
            this.previewUrl = URL.createObjectURL(blob);
            this.$refs.preview?.load();

            await this.uploadBlob(blob);
        },

        async uploadBlob(blob) {
            this.uploading = true;
            this.errorMessage = null;

            const formData = new FormData();
            formData.append('voice', blob, 'visit-note.webm');

            try {
                const response = await fetch(this.uploadUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        Accept: 'application/json',
                    },
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    throw new Error(payload.message || @js(__('Upload failed.')));
                }

                const payload = await response.json();
                this.uploadedPath = payload.path;
                this.setVoicePath(payload.path);
            } catch (error) {
                this.errorMessage = error.message || @js(__('Upload failed.'));
            } finally {
                this.uploading = false;
            }
        },

        setVoicePath(path) {
            const root = this.$refs.recorderRoot?.closest('form')
                ?? this.$refs.recorderRoot?.closest('[wire\\:id]')
                ?? document;

            const hidden = root.querySelector('[data-visit-voice-path]');

            if (!hidden) {
                return;
            }

            hidden.value = path;
            hidden.dispatchEvent(new Event('input', { bubbles: true }));
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        },
    }));
</script>
@endscript
