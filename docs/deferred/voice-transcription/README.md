# Deferred: voice → field auto-fill (speech-to-text)

**Deferred on 2026-08-07.** Nothing in this folder is loaded by the application.
The `.stash` extension is deliberate — it keeps these files out of Composer
autoloading, out of `config()`, and out of the test runner.

## What was deferred

The AI pipeline that listened to a doctor's voice note and pre-filled the visit
notes form (diagnosis, advice, tests advised, reports seen, medicine rows).

**Plain voice notes were NOT deferred.** Doctors still record up to 20 seconds,
the audio still saves with the visit, and playback still works. Only the
"fields fill themselves" layer is gone.

## Why

1. **No free option for the whole pipeline.** Speech-to-text has free-ish paths
   (browser Web Speech API, self-hosted Whisper, Groq's free tier), but turning
   the transcript into structured medicine rows needs an LLM, and there is no
   free, reliable, offline option for that yet.
2. **Accuracy, not cost, was the real blocker.** Doctors dictate mixed
   Bangla-English with Bangladeshi brand names. That is close to a worst case
   for Whisper, and a misheard drug name on a document printed under a BM&DC
   number is a patient-safety problem, not a UX annoyance.

Revisit when a model handles Bangla-English medical dictation well enough to
trust, or when running one locally is cheap.

## Files here

| File | Was at |
|---|---|
| `VisitTranscriptionService.php.stash` | `app/Services/Transcription/VisitTranscriptionService.php` |
| `transcription.php.stash` | `config/transcription.php` |
| `VoiceAutofillTest.php.stash` | `tests/Feature/VoiceAutofillTest.php` |
| `mergeDraftIntoState.php.stash` | method on `App\Filament\TenantAdmin\Support\VisitNotesFormSchema` |

## What was removed from live code

- `POST /api/visit-media/transcribe` route (`routes/tenant.php`) and
  `VisitMediaController::transcribe()`.
- `AppliesVisitNotesDrafts::applyVisitNotesDraft()` — the `visit-notes-draft`
  Livewire listener. The trait's other listener, `copy-last-prescription`,
  is unrelated and stays.
- `VisitNotesFormSchema::mergeDraftIntoState()` and the `_machine_filled`
  hidden field / state key. The `_prefilled` and `_uncertain` flags on
  medicine rows stay — they are used by the medicine picker and
  "Same as last visit", not by transcription.
- Recorder blade: `transcribeUrl`, `transcriptionEnabled`, the `transcribing`
  state, the "Transcribing…" label and `requestTranscription()`.
- The `voice_transcription` tenant feature flag from `Tenant::hasFeature()`
  tier defaults. A tenant row that still carries the flag in its
  `feature_flags` JSON is harmless — nothing reads it now.
- `TRANSCRIPTION_DRIVER` / `OPENAI_API_KEY` are no longer read anywhere.

## Kept on purpose

The `visit_records.voice_transcript` column and its form field. Per
`decisions.md` (2026-08-06) this was always a **manual, optional** field the
doctor types themselves; transcription only ever offered to pre-fill it. The
label is now "Voice note summary" so it no longer implies a machine draft.

## To restore

1. Move the four `.stash` files back to the paths in the table above, dropping
   the `.stash` suffix, and re-add `mergeDraftIntoState()` to
   `VisitNotesFormSchema`.
2. Re-add the route, the controller method, the Livewire listener, the
   `_machine_filled` hidden field, and the blade's transcription branch.
3. Re-add `voice_transcription` to the tier defaults in `Tenant::hasFeature()`.
4. Set `TRANSCRIPTION_DRIVER` and `OPENAI_API_KEY` in `.env`.

Before restoring as-is, note the stashed service pins `whisper-1` and
`gpt-4o-mini`, both dated. Check current models first. Also fix the latent bug
at `VisitTranscriptionService.php.stash:171`: the medicine lookup uses
`auth()->user()` instead of the `$doctor` argument it was given, which breaks
if transcription is ever moved to a queued job.
