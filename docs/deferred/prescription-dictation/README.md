# Deferred: consult-pad voice → prescription draft

**Deferred on 2026-08-13.** Nothing in this folder is loaded by the application.
The `.stash` extension is deliberate — it keeps these files out of Composer
autoloading, out of `config()`, and out of the test runner.

## What was deferred

The desktop Rx pad **Mic**: Chrome/Edge listened in the browser, then
`POST /api/prescriptions/dictate` sent the transcript to Groq
(`openai/gpt-oss-120b`) which filled catalogue-matched medicine rows.

**Plain voice notes were NOT deferred.** Doctors still record up to 20 seconds
on Complete visit, the audio still saves with the visit, and playback still
works. Only “speak the medicines and the pad fills itself” is gone.

## Why

Owner asked to stash voice-to-writing for now and to make sure Groq is not
called in the meantime. The pad still works by typing. Restoring this later
is a product decision, not a missing file.

This supersedes the 2026-08-13T13:09:33+0600 “ship mic-to-prescription”
decision. It does **not** restore the older Whisper-1 audio pipeline in
`docs/deferred/voice-transcription/`.

## Files here

| File | Was at |
|---|---|
| `PrescriptionDictationService.php.stash` | `app/Services/PrescriptionDictationService.php` |
| `PrescriptionDictationController.php.stash` | `app/Http/Controllers/PrescriptionDictationController.php` |
| `groq.php.stash` | `config/groq.php` |
| `PrescriptionDictationTest.php.stash` | `tests/Feature/PrescriptionDictationTest.php` |
| `rx-desk-dictation.blade.stash` | Mic button, language toggle, and Alpine dictation methods on `rx-desk.blade.php` |

## What was removed from live code

- `POST /api/prescriptions/dictate` in `routes/tenant.php` and the controller import.
- Mic / বাং-EN buttons and `webkitSpeechRecognition` / `fetch(dictateUrl)` on the desktop pad.
- `.cs-rx-desk__mic` / `__lang` / `__dictate-status` CSS.
- `GROQ_API_KEY` / `GROQ_DRIVER` from `.env.example` and `phpunit.xml`.
- Groq items on `ProductionReadiness` / `app:production-check`.

`tests/Feature/PrescriptionDictationDeferredTest.php` fails the build if any of
that comes back without an explicit restore.

## Kept on purpose

- 20-second visit voice notes (`VisitMediaService`, upload-voice, playback).
- `MedicineService::vocabularyHints()` — still the restore contract for both
  this stash and `docs/deferred/voice-transcription/`.
- Yellow `uncertain` row chrome on the pad, unused until Mic returns.

## To restore

1. Move the four PHP/config `.stash` files back to the paths in the table,
   dropping the `.stash` suffix.
2. Re-add the route and `PrescriptionDictationController` import in
   `routes/tenant.php`.
3. Put the Mic markup and Alpine methods from `rx-desk-dictation.blade.stash`
   back onto the pad, plus the mic CSS.
4. Restore `GROQ_*` in `.env.example` and `phpunit.xml` (`GROQ_DRIVER=array`
   in tests). Put `GROQ_API_KEY` and `GROQ_DRIVER=groq` in the live `.env`.
5. Restore the Groq checks on `ProductionReadiness`.
6. Move `PrescriptionDictationTest.php.stash` back to `tests/Feature/` and
   drop or rewrite `PrescriptionDictationDeferredTest` — that file exists to
   keep the feature off.

Before restoring, check whether Groq still offers `openai/gpt-oss-120b`.
Do not restore the Whisper-1 pipeline from `docs/deferred/voice-transcription/`
as a substitute.
