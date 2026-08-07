# Site Map
Last Updated: 2026-08-07T12:21:37+0600

## Full Site Map

### Central (marketing + Super Admin + Marketer partner)
Hosts: values in `CENTRAL_DOMAINS` (e.g. `localhost`).

| Route | Purpose | Access |
|-------|---------|--------|
| `/` | Sales landing for ChamberQ (Solo/Clinic plans, WhatsApp CTAs); captures `?ref=` and `?code=` into session | public |
| `/admin` | Super Admin Filament login | public login |
| `/admin/*` | Super Admin: Tenants, Marketers, Discount Codes, Commissions; finance dashboard widgets; **Client Health** seller overview (`/admin/seller-overview`); **Research data** aggregate view (`/admin/research`); confirm doctor payments on tenant edit | super_admin only |
| `/partner` | Marketer partner panel login | public login |
| `/partner/*` | Marketer: referral link, owed/paid stats, referred doctors list, commission history | marketer only |
| `/up` | Laravel health check | public |

### Platform tenant (central path tenancy)
Same central host; tenant identified by URL slug (tenant `id`), e.g. `drkarim`.

| Route | Purpose | Access |
|-------|---------|--------|
| `/{slug}/` | Branded website home | public |
| `/{slug}/book` | Booking wizard | public |
| `/{slug}/bookings/{booking}` | Patient ticket | public (UUID) |
| `/{slug}/portal` | Phone lookup | public (throttled) |
| `/{slug}/screen/{session}/{date}` | Outdoor display | public |
| `/{slug}/admin` | Tenant staff Filament panel | staff login |
| `/{slug}/manifest.webmanifest`, `/{slug}/sw.js`, … | PWA | public |

Example: `https://doctorgemini.com/drkarim/book`

### Tenant public (custom domain — optional)
When a doctor connects their own domain (e.g. `drkarim.com`), routes live at the **root** (no `/{slug}` prefix).

| Route | Purpose | Access |
|-------|---------|--------|
| `/{slug?}` | Branded website pages from WebPage builder (home = empty slug) | public |
| `/book` | Online serial booking wizard | public |
| `/bookings/{booking}` | Patient ticket (UUID) | public |
| `/portal` | Phone lookup | public (throttled) |
| `/screen/{session}/{date}` | Outdoor waiting-room display | public (throttled) |
| `/lang/{locale}` | Switch session locale `en` / `bn` | public |
| `/manifest.webmanifest`, `/sw.js`, `/pwa-icon-{192\|512}.svg` | PWA bits | public |
| `/admin` | Tenant staff Filament panel | staff login |

### Tenant public APIs
Available under both platform path (`/{slug}/api/…`) and custom domain (`/api/…`).

| Route | Purpose | Access |
|-------|---------|--------|
| `GET /api/bookings/availability` | Session/lab availability for wizard | public (throttled) |
| `GET /api/patients/by-phone` | Household members on a phone (booking picker) | public (throttled) |
| `GET /api/conditions/search` | Coded condition autocomplete for doctor diagnosis picker | doctor auth (throttled) |
| `POST /api/bookings` | Create booking | public (throttled; blocked if billing closed) |
| `GET /api/queue/{booking}` | Ticket queue poll by booking UUID | public (throttled) |
| `GET /api/screen/{session}/{date}` | Screen poll payload | public (throttled) |

### Tenant doctor-only routes (auth)
Available under both platform path and custom domain. Requires doctor role (`canViewVisitNotes`).

| Route | Purpose | Access |
|-------|---------|--------|
| `GET /prescriptions/{prescription}/print` | Printable prescription (browser print / Save as PDF) | doctor auth |
| `GET /api/medicines/search` | Ranked medicine brand search for prescription picker | doctor auth (throttled) |
| `POST /api/visit-media/upload-voice` | Upload voice note blob from Mark Completed modal | doctor auth (throttled) |
| `POST /api/visit-media/transcribe` | Structured STT draft from uploaded voice (tenant `voice_transcription` feature) | doctor auth (throttled) |
| `GET /visit-records/{visitRecord}/voice` | Stream visit voice note | doctor auth |
| `GET /visit-records/{visitRecord}/photo` | View paper prescription photo | doctor auth |

### Tenant patient prescription copy (no login — signed link)
The one patient-facing route that shows prescription content. Deliberately outside the doctor-auth set above; the expiring signature is the gate.

| Route | Purpose | Access |
|-------|---------|--------|
| `GET /prescriptions/{prescription}/share` | The patient's own copy of **one** prescription — medicines, that prescription's advice/follow-up, prescriber name + registration, patient name, date. No diagnosis, no other visit, no chamber contact, no link onward into the record. Doctor sends it via a human-tapped `wa.me` link. | public, **signed URL, expires 48h**, throttled |

## Customer Journeys

### New visitor → interested lead (central sales)
1. Land on `/` (or partner link `/?ref=joy20`) — understand Solo vs Clinic and pricing. Goal: trust + clarity.
2. Tap WhatsApp CTA (message includes Ref/Code if captured). Goal: start sales chat.
3. Human sales/onboarding creates tenant in Super Admin with URL slug, attaches marketer/discount. Goal: go-live at `/{slug}`.

### Marketer partner → refer a doctor
1. Log in at `/partner` — copy referral link `/?ref={code}`.
2. Share link with doctor prospect.
3. Doctor chats WhatsApp; Super Admin creates tenant with marketer attached.
4. When doctor pays setup/monthly, Super Admin confirms payment → marketer sees owed commission → Super Admin marks payout paid.

### Patient → book serial → ticket
1. Open `/{slug}/` or custom domain home — see doctor brand + Book CTA.
2. Book flow — pick session/date, enter phone; if the number is known, choose **Who is this appointment for?** inline (or enter a new person).
3. Submit → ticket at `…/bookings/{uuid}`. Goal: proof of serial; share via WhatsApp/copy, or Print / Save as PDF for a paper or file copy.
4. Optional: PWA install scoped to tenant path or custom domain.

### Patient → check status later
1. Open `/portal` or ticket link.
2. Portal: enter BD phone → see matching bookings.

### Patient → waiting room
1. Watch outdoor screen URL for today's session.
2. Hear call chime when serial is called.

## Admin/Staff Journeys

### New tenant → go live (Super Admin)
- **Trigger:** Sales closes a doctor/clinic.
- **Steps:** Create Tenant with URL **slug** (e.g. `drkarim`; rejected if already taken or if it matches a reserved path prefix such as `admin` / `book`) → optional custom **domain** → set `plan_tier`, attach **marketer** / **discount code**, snapshot pricing → set SMS, `billing_status` → **doctor login email** (required; creates doctor user) → hand off admin + doctor logins.
- **URLs:** Platform `/{slug}/…`; after custom domain DNS, also `drkarim.com/…` at root.
- **Success:** `/{slug}/book` works; admin at `/{slug}/admin` (or `/admin` on custom domain).

### Confirm doctor payment & pay marketer (Super Admin)
- **Trigger:** bKash/bank payment received from doctor.
- **Steps:** Tenant edit → **Confirm setup paid** or **Confirm monthly paid** (period YYYY-MM) → Commissions list shows **owed** → **Mark payout paid** with bKash trx note.
- **Success:** Platform finance widget reflects collected cash, owed, paid, and net revenue.

### Sunday client-health review (Super Admin)
- **Trigger:** Weekly check on whether clients are still using the product (usage predicts churn earlier than payment).
- **Steps:** Super Admin → **Client Health** (`/admin/seller-overview`) — review **Quiet clients** (worst first: days since last live session, booking drop vs their own baseline, schedule set but never started), **Go-live funnel** (recent signups and where onboarding stalls), **SMS credit warnings** (balance ≤ 5 — confirmations stop silently at zero), **Overdue payments** (who owes and for how long).
- **Data/systems touched:** `tenants`, aggregate counts from `live_sessions`, `bookings`, `schedule_sessions`, `web_pages`, `billing_payments` — **never** patient names, diagnoses, prescriptions, or visit contents.
- **Success:** Actionable call list of tenant/clinic names; no clinical data crosses the central-panel boundary.

### Research data review (Super Admin)
- **Trigger:** Platform owner wants anonymous disease-pattern statistics across all practices.
- **Steps:** Super Admin → **Research data** (`/admin/research`) — set date range and optional plan tier filter → review coded diagnosis counts (groups of 10+ only). Widen filters if rows are hidden for k-anonymity.
- **Data/systems touched:** `visit_records` (coded `condition_id` only), `conditions`, `tenants.plan_tier` — **never** individual patient rows, names, phones, or uncoded free-text diagnoses.
- **Success:** Useful aggregate counts without re-identification risk; suppressed groups prompt filter widening.

### Open clinic day → run queue
- **Trigger:** Session day starts.
- **Steps:** Queue runner (staff or doctor per Branding **Who runs the queue**) → Live Queue Control → session auto-selected when today has only one, else pick from the dropdown/session cards → Start → Call → Patient arrived → Complete. A no-response patient is skipped from the current-call card (twice, then no-show); any waiting or skipped patient can be called out of turn via **Call now** on their row (unavailable while someone is in the chamber). Mark Late, Pause, Resume, Cancel session and Finish/End session all live behind the header's **Session actions** menu; **New Walk-In** is the standalone header action. Doctor opens **Consult Screen** for auto-updating patient context (no search).
- **Success:** Outdoor screen matches control panel; consult screen shows the patient in chamber; the summary strip's waiting count and projected finish time match the table.

### Doctor consult (doctor role)
- **Trigger:** Patient called into chamber (`live_sessions.current_booking_id` set).
- **Steps:** Tenant admin → **Consult Screen** — screen updates automatically (poll). Review visit count, warnings (above the write section on mobile), last visit diagnosis/advice/voice/photo/transcript, past visits with reprint, voice playback, and photo links. Amber catch-up banner when today's session is active and completed patients lack notes — tap to fill in. While the patient is in the chamber the card carries **Write prescription** (then **Edit prescription**) — prescription-first modal with searchable medicine picker (prefills dose/frequency/duration from the doctor's history), quick-pick frequency/duration chips, **Same as last visit**, relative follow-up chips, voice note with optional STT draft when `voice_transcription` is enabled; saving does not end the visit. The consult ends in **two steps**: **Complete visit** shows a read-only summary when notes already exist (Edit to reopen the full form), then closes the visit *without* advancing the queue; the patient stays on screen under "Visit completed — ready for next patient" with **Print prescription** and **Send via WhatsApp**; sticky bottom actions on phones mirror the header queue controls. Then **Call next patient** advances the queue. Staff completing from the queue skip the modal. Ending the session from Live Queue Control warns if notes are still missing.
- **Data/systems touched:** `live_sessions`, `bookings`, `patients`, `visit_records`, `prescriptions`.
- **Key CTA:** Complete visit → Print / Send via WhatsApp → Call next patient.
- **Success:** Doctor sees correct person and honest history state; visit notes saved when provided; the prescription can be printed or sent before the next patient is called; patient ticket/portal never show clinical data, and the shared link exposes only that one prescription.

### Manage personal medicine list (doctor)
- **Trigger:** Doctor wants to fix default dose/frequency/duration or hide a brand from their picker.
- **Steps:** Tenant admin → **My medicines** (Operations group) → edit defaults, hide from search, or add a manual entry.
- **Data/systems touched:** `medicine_usages` only — never the shared `medicines` catalogue.
- **Success:** Next prescription search ranks and prefills from the doctor's corrected defaults.

### Content update (staff)
- **Trigger:** Doctor wants copy/photo change.
- **Steps:** Staff edits Web Page blocks in tenant admin.

### Block a date — vacation / holiday / doctor away (admin/doctor)
- **Trigger:** The clinic, a chamber, or one doctor will not sit on a given date.
- **Steps:** Slot Blocks → New → pick date, optionally chamber and/or doctor → the form shows how many bookings this will cancel and requires the confirmation checkbox → Save. Saving cancels those bookings (waiting/called/in-chamber only — completed visits are left alone) and reports the count. Then **Notify patients** on that block row → tap each patient to open WhatsApp with a prepared message.
- **Data/systems touched:** `slot_blocks`, `bookings` (`status`, `cancelled_at`, `cancellation_reason`, `slot_block_id`) via `SlotBlockService`.
- **Success:** No patient still holds a serial for a closed date, and every cancelled patient appears in the notify list.

### Ops review (admin/doctor)
- **Trigger:** End of day/week.
- **Steps:** Operational Reports → day/week/month KPIs.

### Patient records — lookup and corrections (admin/doctor)
- **Trigger:** Staff need to see who has visited, fix a duplicate person, or move a visit to the right household member.
- **Steps:** Tenant admin → **Patients** (search by name/phone) → edit demographics, or use **Join two records** / **Move a visit** actions. Run `php artisan patients:backfill` once per environment to link legacy bookings (use `--dry-run` first).
- **Data/systems touched:** `patients`, `bookings.patient_id`.
- **Success:** Each person has one record; family members on one phone can each book the same day; staff can merge mistaken duplicates.
