# Site Map
Last Updated: 2026-08-15T22:37:54+0600

## Full Site Map

### Central (marketing + Super Admin + Marketer partner + patient Find)
Hosts: values in `CENTRAL_DOMAINS` (e.g. `localhost`).

| Route | Purpose | Access |
|-------|---------|--------|
| `/` | Sales landing for ChamberQ (**Maestro** + **Clinic** cards; modules table under pricing; WhatsApp CTAs); **Find a doctor** + **Patient login** in nav; captures `?ref=` and `?code=` into session | public |
| `/find` | Directory of every Front door doctor who currently accepts online serials (search by name / specialty / area) | public |
| `/me/login` | Patient phone OTP login (optional; not required to book) | public |
| `POST /me/login/otp` | Send 6-digit SMS code (throttled; ChamberQ-paid SMS) | public |
| `POST /me/login/verify` | Verify code and sign in on the `patient` guard | public |
| `/me` | Upcoming serials across every ChamberQ clinic for this phone | patient login |
| `/me/history` | Past visits and prescriptions for this phone (own records; no share-flag gate; no voice/photo) | patient login |
| `/me/prescriptions/{id}` | Full patient pad for one prescription belonging to this phone | patient login |
| `/admin` | Super Admin Filament login | public login |
| `/admin/*` | Super Admin: Tenants under **Platform** (incl. **Product modules** checkboxes: Front door / Live queue / Prescription; **Maestro**/Clinic label; launch-offer ticks; live list/due + partner commission preview), Marketers, Discount Codes, Commissions; finance dashboard then platform totals then latest 8 tenants; **Client Health** seller overview (`/admin/seller-overview`, names link to tenant edit); **Research data** aggregate view (`/admin/research`); **Platform data backup** (`/admin/data-backup`, restore defaults to dry-run and confirms before a live replace); Tenants list row actions (Edit / Download chamber backup) sit in a **⋮** menu with finance columns behind the column manager; per-tenant chamber backup download plus Restore/Delete behind **Dangerous** on tenant edit; confirm doctor setup/monthly/**12 months prepaid** on tenant edit | super_admin only |
| `/partner` | Marketer partner panel login | public login |
| `/partner/*` | Marketer: referral link, owed/paid stats, referred doctors list, commission history | marketer only |
| `/up` | Laravel health check | public |

Branded HTML error pages (`resources/views/errors/{403,404,419,429,500,503}.blade.php`) replace Laravel’s grey Forbidden / Not Found screens. JSON API errors stay JSON.

### Platform tenant (central path tenancy)
Same central host; tenant identified by URL slug (tenant `id`), e.g. `drkarim`.

| Route | Purpose | Access |
|-------|---------|--------|
| `/{slug}/` | Branded website home | public (**Front door** module) |
| `/{slug}/book` | Booking wizard | public (**Front door**) |
| `/{slug}/bookings/{booking}` | Patient ticket (sitting window always; live queue / come-around only with **Live queue**) | public (UUID) |
| `/{slug}/portal` | Phone lookup — bookings + every prescription with medicines (Rx list needs **Prescription**) | public (throttled, **Front door**) |
| `/{slug}/screen/{session}` | Outdoor TV (always today for that schedule session — bookmark once) | public (**Live queue**) |
| `/{slug}/screen/{session}/{date}` | Outdoor display for a specific date (legacy / deep link) | public (**Live queue**) |
| `/{slug}/lang/{locale}` | Switch session locale `en` / `bn` (same-host Referer; signed-in staff without Referer return to `/{slug}/admin`) | public |
| `/{slug}/departments` | Clinic departments listing (clinic tier only) | public |
| `/{slug}/departments/{slug}` | Single department page | public |
| `/{slug}/blog` | Clinic health articles listing (clinic tier only) | public |
| `/{slug}/blog/{slug}` | Single blog article | public |
| `/{slug}/doctors` | Clinic doctor profiles listing (clinic tier only) | public |
| `/{slug}/doctors/{slug}` | Single doctor public profile | public |
| `/{slug}/admin` | Tenant staff Filament panel | staff login |
| `/{slug}/admin/cashbook` | Desk khata: income, expense, net, waived (day/week/month) | staff / doctor / admin |
| `/{slug}/admin/waiting-for-earlier-date` | Staff list of patients who opted in for an earlier date (WhatsApp per row) | staff login (ops) |
| `/{slug}/admin/data-backup` | Chamber disaster-recovery backup download + restore (Admin only) | admin only |
| `/{slug}/manifest.webmanifest`, `/{slug}/sw.js`, … | PWA | public |

Example: `https://doctorgemini.com/drkarim/book`

### Tenant public (custom domain — optional)
When a doctor connects their own domain (e.g. `drkarim.com`), routes live at the **root** (no `/{slug}` prefix).

| Route | Purpose | Access |
|-------|---------|--------|
| `/{slug?}` | Branded website pages from WebPage builder (home = empty slug) | public |
| `/departments` | Clinic departments listing (clinic tier only) | public |
| `/departments/{slug}` | Single department page | public |
| `/blog` | Clinic health articles listing (clinic tier only) | public |
| `/blog/{slug}` | Single blog article | public |
| `/doctors` | Clinic doctor profiles listing (clinic tier only) | public |
| `/doctors/{slug}` | Single doctor public profile | public |
| `/book` | Online serial booking wizard | public |
| `POST /book` | Homepage hero form target — flashes name/phone to session, redirects to the wizard so patient details never enter the URL | public (throttled) |
| `/bookings/{booking}` | Patient ticket (UUID) | public |
| `/portal` | Phone lookup — bookings + every prescription with medicines | public (throttled) |
| `/screen/{session}` | Outdoor waiting-room TV (always today — bookmark once per schedule session) | public (throttled) |
| `/screen/{session}/{date}` | Outdoor waiting-room display for a specific date (legacy) | public (throttled) |
| `/lang/{locale}` | Switch session locale `en` / `bn`. Same-host Referer only (off-site Referer is ignored). Signed-in chamber staff with no Referer return to `/admin`; guests stay on the public site | public |
| `/manifest.webmanifest`, `/sw.js`, `/pwa-icon-{192\|512}.svg` | PWA bits | public |
| `/admin` | Tenant staff Filament panel | staff login |
| `/admin/cashbook` | Desk khata: income, expense, net, waived (day/week/month) | staff / doctor / admin |
| `/admin/waiting-for-earlier-date` | Staff list of patients who opted in for an earlier date (WhatsApp per row) | staff login (ops) |
| `/admin/visiting-day` | Pack bag / write prescriptions away from the chamber (bad internet, camps) | doctor login (**Prescription**) |
| `/admin/data-backup` | Chamber disaster-recovery backup download + restore (Admin only) | admin only |

### Tenant public APIs
Available under both platform path (`/{slug}/api/…`) and custom domain (`/api/…`).

| Route | Purpose | Access |
|-------|---------|--------|
| `GET /api/bookings/availability` | Session/lab availability for wizard (re-check on identity step) | public (throttled) |
| `GET /api/bookings/open-dates` | Open bookable+date options for wizard "When can you come?" step (60-day window, soonest first) | public (throttled) |
| `GET /api/patients/by-phone` | Household members on a phone (booking picker) — returns **masked initials + age only**, never names | public (throttled 10/min) |
| `GET /api/conditions/search` | Coded condition autocomplete for doctor diagnosis picker | doctor auth, same tenant (throttled) |
| `POST /api/bookings` | Create booking | public (throttled; blocked if billing closed) |
| `GET /api/queue/{booking}` | Ticket queue poll by booking UUID | public (throttled) |
| `POST /api/queue/{booking}/push` | Store a Web Push subscription for pocket buzz on this ticket (live queue only; no SMS) | public (throttled) |
| `POST /api/staff/push` | Store staff Web Push subscription for sitting sticky-note buzz (live queue only; auth) | staff login (throttled) |
| `GET /api/screen/{session}` | Outdoor TV poll (always today) | public (throttled) |
| `GET /api/screen/{session}/{date}` | Screen poll for a specific date (legacy) | public (throttled) |
| `POST /api/bookings/{booking}/sms/cancellation` | Staff-tapped cancellation SMS (prepaid; gated by doctor `cancellation` SMS pref) | auth, same tenant (ops/queue/visit-notes), throttled |
| `POST /api/prescriptions/{prescription}/sms` | Staff-tapped prescription-link SMS (prepaid; gated by doctor `prescription` SMS pref) | auth, same tenant (ops/queue/visit-notes), throttled |

### Tenant doctor-only routes (auth)
Available under both platform path and custom domain. Requires doctor role (`canViewVisitNotes`) **and** membership of the tenant being served (`User::belongsToCurrentTenant()`) — panels share one session cookie across every tenant on the host, so the role check alone is not authorisation here.

| Route | Purpose | Access |
|-------|---------|--------|
| `GET /prescriptions/{prescription}/print` | Printable prescription (browser print / Save as PDF). Optional `?paper=1` skips the ChamberQ letterhead for pre-printed pads — doctor print only; share/portal ignore it | doctor auth |
| `GET /api/medicines/search` | Ranked medicine brand search for prescription picker | doctor auth (throttled) |
| `GET /api/medicines/doses` | Strengths one brand actually ships in, for the Rx desk dose chips | doctor auth (throttled) |
| `GET /api/offline/bag` | Travel bag: packs, My medicines, known patients, letterhead | doctor auth (throttled, **Prescription**) |
| `GET /api/offline/queue/{session}` | Queue snapshot for offline Call next on this computer | queue-runner auth (throttled, **Live queue**) |
| `POST /api/offline/sync` | Upload pad saves, visiting visits, and queued Call next events | doctor or queue-runner auth (throttled) |
| `POST /api/visit-media/upload-voice` | Upload voice note blob from Mark Completed modal | doctor auth (throttled) |
| `POST /api/visit-media/upload-report-photo` | Upload a photo of a report the patient brought | doctor auth (throttled) |
| `GET /visit-records/{visitRecord}/voice` | Stream visit voice note | doctor auth |
| `GET /visit-records/{visitRecord}/photo` | View paper prescription photo | doctor auth |
| `GET /visit-records/{visitRecord}/report-photos/{index}` | Stream one report photo | doctor auth |

### Tenant patient prescription copy (no login)
Full clinical pad (diagnosis, notes, Inv, medicines, advice, follow-up, chamber letterhead). Voice notes and prescription photos stay off. Shared Blade sheet with the doctor print.

**Phone-first.** Below 640px each medicine is a card rather than an A4 row, and every line carries the dose written out in Bangla ("সকাল ১টি · রাত ১টি") alongside the doctor's `1+0+1`. CTAs: **Print / Save as PDF** (always) and **Send on WhatsApp** — the latter only on `/p/{token}` and the legacy signed link, never on the portal route, whose URL carries the patient's phone number.

| Route | Purpose | Access |
|-------|---------|--------|
| `GET /p/{token}` | SMS/WhatsApp copy of **one** prescription. Short token so the SMS fits one billable segment. Unknown or expired token → 404. | public, **10-char token, expires 48h**, throttled 30/min |
| `GET /portal/prescriptions/{prescription}?phone=` | Durable portal backup when staff forget to send `/p/{token}`. Phone must match the booking that owns the visit. | public, phone-gated, throttled 30/min |
| `GET /prescriptions/{prescription}/share` | **Superseded by `/p/{token}`.** Kept only so links already delivered keep working; every one expires within 48h. | public, **signed URL, expires 48h**, throttled |

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
1. Open `/{slug}/` or custom domain home — see doctor brand + Book CTA. On clinic-tier sites the Book Appointment CTA now also sits in the header nav (desktop) and the mobile drawer, per the Clireo design port; solo keeps its locked layout.
2. Book flow — chamber/doctor when needed, then **When can you come?** (only dates with seats left, soonest first; earliest option highlighted). **Your details** under the booking summary strip (Name / Phone / NID optional / Different WhatsApp / Who for?; **Share with other ChamberQ doctors**); **Change date** on the summary strip (or Back). If the number is known, choose **Who for?** inline — masked initials (`F. R., 34`); picking one stands the name field down. Clinic hero form can POST name/phone into the session first. A ChamberQ patient login on the same host prefills name/phone.
3. Submit → ticket at `…/bookings/{uuid}`. Goal: proof of serial; share via WhatsApp/copy, or Print / Save as PDF for a paper or file copy. With **Live queue**, the ticket also offers **জানাতে দিন** (Bangla): Allow once so the phone can buzz when the serial is two away / next / called, even if the ticket is closed. If Allow is blocked, the copy says to come at ticket time or sit by the TV.
4. Optional: PWA install scoped to tenant path or custom domain.

### Patient → Find a doctor (ChamberQ directory)
1. Open `/find` from the marketing nav or directly — browse every bookable ChamberQ doctor. Goal: pick a doctor without hunting their website.
2. Optional: **Patient login** at `/me/login` (phone + SMS code) to see every serial and prescription in one place. Booking does not require this.
3. Tap **Book serial** → that doctor's existing wizard at `/{slug}/book` (same BookingService). Ticket still lives on the doctor's site and also appears under `/me` after login.

### Patient → check status later
1. Open `/portal` or ticket link — or, with a ChamberQ login, `/me`.
2. Portal: enter BD phone → see matching bookings **and**, when present, every prescription with medicines (newest first). `/me` lists every clinic for that phone without re-typing it.
3. Tap **View prescription** → full pad (phone must still match, or the logged-in account owns it). Goal: get medicines/diagnosis even if staff forgot the SMS/WhatsApp link.

### Patient → waiting room
1. Watch the outdoor TV (staff bookmark `/screen/{session}` once — always shows today for that Morning/Evening slot).
2. Hear call chime when serial is called.

## Admin/Staff Journeys

### New tenant → go live (Super Admin)
- **Trigger:** Sales closes a doctor/clinic.
- **Steps:** Create Tenant with URL **slug** (e.g. `drkarim`; rejected if already taken or if it matches a reserved path prefix such as `admin` / `book` / `find` / `me`) and **doctor login email** (required; creates doctor user). Plan Tier starts as **Maestro**, billing as trial, SMS as 0, all three modules on, theme and locale filled — change them only when the deal is not the default. Optional custom **domain** (repeater starts empty). Tick launch offers if honouring them (Prescription free for life / prepaid-year 50% setup) → attach **marketer** / **discount code**, read the labeled list/due + partner preview → Create → hand off admin + doctor logins.
- **URLs:** Platform `/{slug}/…`; after custom domain DNS, also `drkarim.com/…` at root.
- **Modules:** Front door alone = website + book + day list (no outdoor TV / Call next / come-around). Live queue adds TV + live ticket. Prescription adds consult/Rx. Booking confirmation SMS is optional (credits + doctor toggle).
- **Success:** Enabled module routes work; disabled ones 404. Admin at `/{slug}/admin` (or `/admin` on custom domain).

### Confirm doctor payment & pay marketer (Super Admin)
- **Trigger:** bKash/bank payment received from doctor.
- **Steps:** Tenant edit → tick modules and launch offers (Prescription free for life / prepaid-year 50% setup) so due amounts and pending commission match the quote → **Confirm setup paid**, **Confirm monthly paid** (period YYYY-MM), or **Confirm 12 months prepaid** after setup is paid → Commissions list shows **owed** → **Mark payout paid** with bKash trx note.
- **Success:** Platform finance widget reflects collected cash, owed, paid, and net revenue.

### Sunday client-health review (Super Admin)
- **Trigger:** Weekly check on whether clients are still using the product (usage predicts churn earlier than payment).
- **Steps:** Super Admin → **Client Health** (`/admin/seller-overview`) — review **Quiet clients** (worst first: days since last live session, booking drop vs their own baseline, schedule set but never started), **Go-live funnel** (recent signups and where onboarding stalls), **SMS credit warnings** (balance ≤ 5 — confirmations stop silently at zero), **Overdue payments** (who owes and for how long). Tap a clinic name to open tenant edit; call the listed contact phone.
- **Data/systems touched:** `tenants`, aggregate counts from `live_sessions`, `bookings`, `schedule_sessions`, `web_pages`, `billing_payments` — **never** patient names, diagnoses, prescriptions, or visit contents.
- **Success:** Actionable call list of tenant/clinic names with a tap-through to that tenant; no clinical data crosses the central-panel boundary.

### Research data review (Super Admin)
- **Trigger:** Platform owner wants anonymous disease-pattern statistics across all practices.
- **Steps:** Super Admin → **Research data** (`/admin/research`) — set date range and optional plan tier filter → review coded diagnosis counts (groups of 10+ only). Widen filters if rows are hidden for k-anonymity.
- **Data/systems touched:** `visit_records` (coded `condition_id` only), `conditions`, `tenants.plan_tier` — **never** individual patient rows, names, phones, or uncoded free-text diagnoses.
- **Success:** Useful aggregate counts without re-identification risk; suppressed groups prompt filter widening.

### Open clinic day → run queue
- **Trigger:** Session day starts.
- **Steps:** Queue runner (staff or doctor per Branding **Who runs the queue**) → Live Queue Control → optional **Buzz this phone** (staff pocket alert when a sticky note appears) → session auto-selected when today has only one, else pick from the dropdown/session cards → **Open screen / Copy link** once onto the waiting-room TV (stable URL, no date — bookmark and reuse every day for that session) → Start → Call → Patient arrived → Complete. If the line drops, the TV keeps the last number on screen and Call next still works **on this computer** (HDMI laptop or signed-in browser on the TV); replay uploads when the line returns. **After 10 minutes past sitting time**, if patients are waiting and nobody has Started, Daily Roster, Live Queue Control, and Consult Screen show a sticky amber note counting minutes late and waiting patients — Mark Late or Start (no patient SMS from this note). **After Start**, if nobody has been called for 10+ minutes, the same surfaces show an idle-after-start note (“Is the doctor in the chair?”) — clears on Call next, in chamber, **Doctor stepped out**, or End. **Start** after sitting time asks Mark Late / Just start / Cancel; if they already marked late and arrive before the announced time, Start now / Wait until that time / Cancel. **Doctor stepped out** blocks Call next and Call now until **He's back**. A no-response patient is skipped from the current-call card (twice, then no-show); any waiting or skipped patient can be called out of turn via **Call now** on their row (including overflow stools before the published line finishes). Desk walk-ins after the published cap use **Extra walk-in seats** on the sitting (staff only; online stays “full”). Booking SMS and the wizard confirm flash include a published **come around** time when live queue is on (unavailable while someone is in the chamber). Mark Late, Pause, Resume, Cancel session and Finish/End session all live behind the header's **Session actions** menu; **New Walk-In** is the standalone header action. **Mark Late** is also on **Daily Roster** (table header) so staff can warn waiting patients before opening Live Queue Control or pressing Start — same delay SMS / WhatsApp hand-off; on a `delayed` sitting it becomes **Add time** and only accepts a **larger** total delay. **Cancel session (doctor absent)** and **Finish/End session** behave identically toward patients: both name the count and the patients in their confirmation, both leave a patient already in the chamber as *completed* rather than cancelled, and both surface **Tell cancelled patients** afterwards for a per-patient WhatsApp/SMS hand-off. Amber **patients today without notes** banner at the top when completed patients lack notes (**Fill in now** opens the catch-up list). Doctor opens **Consult Screen** for auto-updating patient context (no search). After the visit, **Daily Roster → Collect fee** records cash/bKash/Nagad/card (or waive); **Operations → Cashbook** is where rent/tea/salary go out.
- **Success:** Outdoor screen matches control panel; consult screen shows the patient in chamber; the summary strip's waiting count and projected finish time match the table. Patients who tapped **জানাতে দিন** get a Bangla pocket buzz at two away / next / called without staff sending SMS or WhatsApp.

### Tell waiting patients the doctor is late
- **Trigger:** Doctor will arrive late (call from traffic, emergency, etc.) and patients are already booked for today.
- **Steps:** Queue runner → **Daily Roster** → **Mark Late** → pick session (auto-filled when only one) → pick delay (15–120 min) → confirm (shows SMS credit cost when late SMS is on). Or the same action under Live Queue Control → **Session actions** → **Mark Late** (no need to press Start). On a `delayed` sitting, **Add time** only accepts a **larger** total (30 → 60, not 15). If late WhatsApp is on, **Tell waiting patients** appears for tap-to-send `wa.me` links.
- **Data/systems touched:** `live_sessions` (status `delayed`, `delay_minutes`), waiting/called/skipped `bookings`, optional `SendDoctorLateNotices` SMS, patient ticket delay banner.
- **Success:** Patients see/hear the delay; session is marked delayed until Start clears it; SMS wallet only charged when late SMS is enabled.

### Doctor consult (doctor role)
- **Trigger:** Patient called into chamber (`live_sessions.current_booking_id` set).
- **Steps:** Tenant admin → **Consult Screen** — screen updates automatically (poll). From **768px up** (tablet as well as desktop) while the patient is in the chamber, the page *is* the prescription pad — below 1024px its two columns stack, prescription first, with touch-sized chips: sticky bar pinned below the page content header (name · age · sex · serial, allergy, **Preview / My paper tick / Save & print**; **Complete visit** is on that same header), left column C/C mini-table · H/O toggles (More includes COPD / Allergy) · O/E compact vitals line (Wt / BP / P / SpO₂ / T °F on one wrapping row, finding chips into Other findings, **weight/BP trend charts** when past data exists) · Dx pill · Inv list · **Reports** (typed note + photos of papers the patient brought) (plus last-visit copy), right column medicine spreadsheet (search, **Why?** typeahead under the brand, dose/frequency/duration chips, timing, drag handle + ↑↓, shorthand line, **warn-only duplicate/allergy checks**), advice chips then the box, and follow-up underneath. How much of that is already filled when he arrives: H/O is seeded from the patient's stored conditions and medicines; C/C is a row list (~40 bilingual chips): each tap adds a complaint line with its own duration; a previous visit offers **Same medicines / Same Dx / Same Inv / Same advice / Repeat whole visit**; picking a diagnosis surfaces his own **packs** for it, **Add investigations**, and a starter-advice chip in the **Advice** box (the actual sentence; tap to copy; a save does not hide it) plus five common advice chips (Bangla lines) and ★ save-as-mine on this computer; and typing a brand brings its dose, frequency, duration and timing with it — his own saved default first, the catalogue's per-drug default otherwise, blank when neither knows. **Why?** under the brand suggests as he types. **Vitals are never pre-filled**; last visit's shows grey beside each box. **★** on a row saves that line to My medicines; **Packs** applies one the doctor built earlier on **My medicines** — packs cannot be created or renamed from here. **The pad saves itself** — a second after he stops typing and the instant he clicks anything outside it — and says so in the bar (Unsaved / Saving… / Saved), so Complete visit can no longer close a visit on an unwritten script. Follow-up offers 1 week / 2 weeks / 1 month / 3 months / As needed / Pick a date. Only on **phones (<768px)** does the older layout stay: review history, then **Write prescription** modal. The consult ends in **two steps**: **Complete visit** (summary when notes exist) closes the visit *without* advancing the queue; Print / Send via WhatsApp while the patient is still on screen; then **Call next patient**. Staff completing from the queue skip the modal. Catch-up for patients completed without notes lives on **Live Queue Control** (amber banner + **Fill in now**), not here.
- **Data/systems touched:** `live_sessions`, `bookings`, `patients`, `visit_records` (including `chief_complaint` / `history` / `on_examination` / `temperature_f` / `report_photo_paths`), `prescriptions`, `prescription_items` (including `timing` / `indication` / `instructions`); reads `medicines` dosing defaults and `conditions` advice/investigation presets; writes `medicine_usages` (★); reads `prescription_templates` but never writes them (packs are built on My medicines).
- **Key CTA:** Save (desk) or Write prescription (phone) → Complete visit → Print / Send via WhatsApp → Call next patient.
- **Note:** Everything the pad fills is a proposal on a document the doctor signs — visible, editable, cleared in one keystroke, and never committed until Save. Nothing is learned from what he prescribes: packs and My medicines entries only ever come from an explicit tap. If the internet drops mid-consult, Save & print still work on this computer; Call next stays frozen until the line is back.
- **Success:** Doctor sees correct person and honest history state; visit notes saved when provided; the prescription can be printed or sent before the next patient is called; the shared link and portal show the full clinical pad for that prescription (voice/photo stay doctor-only); ticket itself still shows no clinical data.

### Visiting day / camp (doctor — bad internet away from the chamber)
- **Trigger:** Doctor will sit somewhere with unstable internet (village camp, second chamber, outreach), or the main chamber line is expected to drop.
- **Steps:** On good internet → **Operations → Visiting / camp** → **Pack bag** (copies packs, My medicines, known patients, letterhead onto this laptop). At the remote room → add name + phone or pick a packed patient → write medicines from the bag → **Save & print** (the printed sheet is what the patient takes home). When signal returns → **Upload pending visits** (or the yellow banner's Upload now) so the visit is stored in ChamberQ. This page has no WhatsApp/SMS send. This is not Live Queue — no Call next, no outdoor TV.
- **Data/systems touched:** IndexedDB on the laptop; `GET /api/offline/bag`; `POST /api/offline/sync` → `bookings` + `visit_records` (`offline_sync_id`) + `prescriptions`. Does not mutate the live queue.
- **Success:** Patient leaves with a printed pad; the same visit appears in ChamberQ when the line is back, without calling the next serial at the main chamber.

### Type up a paper prescription (staff — only for doctors who opted in)
- **Trigger:** A doctor who prescribes by hand has finished a consult, and `staff_may_enter_prescriptions` is on for that doctor (Doctors resource; **off by default**).
- **Steps:** Admin or doctor first turns on **Staff may type this doctor's prescriptions** on the Doctors record. Then staff → **Daily Roster** → the patient's *completed* row → **Enter prescription** (**Edit prescription** once one exists) → photograph the paper slip → photograph any lab reports / X-rays the patient brought → pick medicines from the grouped dropdown with dose/frequency/duration chips → set follow-up → Save. Booking status is unchanged and there is no doctor approval step. The form shows medicines, follow-up, the slip photo and report photos only — staff see no diagnosis, advice, typed reports note, voice note, allergy strip or past visits, and cannot open Consult Screen or the prescription print route.
- **Data/systems touched:** `visit_records` (prescription/follow-up/paper-photo/`report_photo_paths` columns only, with `recorded_by` = the staff user), `prescriptions`, `prescription_items`. Any diagnosis or voice note the doctor already recorded is left intact. Typed `reports_seen` stays doctor-only. Nothing is added to any doctor's medicine list — prescribing never writes there, for staff or doctors alike.
- **Success:** The visit gains a searchable prescription that feeds **Same as last visit** next time, the paper slip and any report photos are attached for checking, and the printed copy still carries the doctor's name and BM&DC registration from the booking's session.

### Manage personal medicine list (doctor)
- **Trigger:** Doctor wants to fix default dose/frequency/duration or hide a brand from their picker.
- **Steps:** Tenant admin → **My medicines** (Operations group) → edit defaults including **Timing**, hide from search, or add a manual entry. The other way in is **★** on any Rx desk row mid-consult, which saves that line here. The same page carries **Rx packs**: create, edit and delete named sets of medicines (with optional diagnosis, advice, tests and follow-up) that the Rx desk then applies in one tap.
- **Data/systems touched:** `medicine_usages` only — never the shared `medicines` catalogue.
- **Note:** There is no booking on this page, so the catalogue offered follows the doctor's own `practice_type` — which requires their login to be paired with their Doctors record (below). An unpaired clinic account sees the general-physician list.
- **Success:** Next prescription search prefills from the doctor's corrected defaults, field by field — anything he left blank still falls back to the catalogue's per-drug default, then to empty. The list is shown A–Z and only changes when the doctor changes it.

### Pair a doctor with their login (admin — clinics)
- **Trigger:** A clinic adds a doctor, or an existing clinic has doctors whose accounts were never matched to their Doctors record (the 2026-08-07 migration only auto-paired tenants with exactly one doctor and one doctor login).
- **Steps:** Tenant admin → **Doctors** → the doctor's row → **Login account** → pick their user account → Save. One account per doctor; picking one already used gives a validation error.
- **Data/systems touched:** `doctors.user_id`.
- **Success:** That doctor's **My medicines** page and medicine search show their own practice type's catalogue instead of the general-physician list. Prescriptions written inside a consult were already correct (they resolve from the booking's session) and are unchanged.

### Content update (staff)
- **Trigger:** Doctor wants copy/photo change.
- **Steps:** Staff edits Web Page blocks in tenant admin. Hero photo and **Latest Educational Videos** cover/video are file uploads (or a YouTube link). Clinic tier can also add/edit **Departments**, **Blog posts**, and doctor **Website profile** fields under **Website**.

### Clinic website content (admin/staff — clinic tier)
- **Trigger:** Clinic needs a new department, blog article, or public doctor profile.
- **Steps:** Tenant admin → **Website** → **Departments** / **Blog posts** (create, publish) or **Doctors** → enable **Show on website**, photo, bio, slug → homepage sections (`service_matrix`, `health_insights`, `doctor_grid`) pull published rows automatically.
- **Data/systems touched:** `departments`, `blog_posts`, `doctors` website columns; public routes `/departments`, `/blog`, `/doctors`.
- **Success:** List + detail pages live; homepage teasers match without duplicating cards in the page builder.

### Follow-up reminders (staff / doctor — ops)
- **Trigger:** A visit has a follow-up date set and the prescribing doctor has follow-up notifications enabled.
- **Steps:** **SMS (automatic):** daily job texts patients **3 days before** the follow-up date when the doctor's follow-up SMS toggle is on (1 credit; empty wallet skips). **WhatsApp (confirm first):** when the doctor's follow-up WhatsApp toggle is on, staff (or doctor if no staff) get a panel notification → **Operations → Follow-up reminders** → tap **Confirm WhatsApp** per row to open the prepared message.
- **Data/systems touched:** `visit_records.follow_up_date`, reminder timestamp columns, `SmsMessage` (`purpose=follow_up`), doctor `notify_channels.follow_up`.
- **Success:** Patient is reminded before the follow-up; WhatsApp never sends without a human tap.

### Chamber cashbook (staff / doctor / admin — ops)
- **Trigger:** A patient pays at the desk, or the chamber spends money (rent, tea, salary).
- **Steps:** Set the doctor's **Default consultation fee** on Doctors. On **Daily Roster**, **Collect fee** (amount, cash/bKash/Nagad/card, or waive). On **Operations → Cashbook**, **Add expense** or **Add income**, then read day/week/month income, expense, net, and waived ৳ (not collected — not an expense).
- **Data/systems touched:** `chamber_cash_entries`, `doctors.default_fee_taka`, `ChamberCashService`. Patients still pay at the chamber — no booking gateway.
- **Success:** End of day the khata shows what came in, what went out, and what is left.

### Earlier-date waiting list (staff — ops)
- **Trigger:** Legacy bookings still flagged `wants_earlier_date` (the public wizard no longer offers the opt-in), or staff want to contact those patients when a seat frees up.
- **Steps:** Tenant admin → **Operations** → **Waiting for earlier date** — review future flagged bookings (soonest booked date first) → tap **WhatsApp** per row to message that patient (staff-tapped; no automatic SMS).
- **Data/systems touched:** `bookings.wants_earlier_date`, `bookings.booking_date`, patient name/phone on the booking row.
- **Success:** Staff can reach patients who previously asked to move earlier without hunting through the full roster.

### Block a date — vacation / holiday / doctor away (admin/doctor)
- **Trigger:** The clinic, a chamber, or one doctor will not sit on a given date.
- **Steps:** Slot Blocks → New → pick date, optionally chamber and/or doctor → the form shows how many bookings this will cancel and requires the confirmation checkbox → Save. Saving cancels those bookings (waiting/called/in-chamber only — completed visits are left alone) and reports the count. Then **Notify patients** on that block row → tap each patient to open WhatsApp with a prepared message.
- **Data/systems touched:** `slot_blocks`, `bookings` (`status`, `cancelled_at`, `cancellation_reason`, `slot_block_id`) via `SlotBlockService`.
- **Success:** No patient still holds a serial for a closed date, and every cancelled patient appears in the notify list.

### Disaster-recovery backup (chamber Admin)
- **Trigger:** Weekly off-server copy, cyber attack, or rebuild from empty database.
- **Steps:** Tenant admin → **Settings → Data backup** → **Download chamber backup** (save ZIP to Drive/USB) → after wipe/redeploy, **Upload and restore** (replace mode + type chamber ID to confirm; or merge / dry-run). Staff use **Forgot password** after restore.
- **Data/systems touched:** All tenant-owned tables (patients, bookings, visit records, prescriptions, schedules, staff users, etc.) — not shared medicine/condition catalogues; not voice/photo files (paths only in CSV).
- **Success:** ZIP round-trips; chamber data readable in Excel; clinical media still needs separate disk backup.

### Disaster-recovery backup (Super Admin)
- **Trigger:** Platform wipe or per-chamber restore without logging into that chamber.
- **Steps:** **Platform data backup** (`/admin/data-backup`) — download first; restore starts as **Check ZIP without writing** (dry-run on). Uncheck dry-run only when ready to write — the button turns red, a warning callout appears, and submitting asks a confirmation naming what gets wiped; replace still requires typing `REPLACE`. Per-chamber: Tenants → row **⋮** → **Download chamber backup**, or tenant edit → **Dangerous** → Restore / Delete.
- **Success:** ZIP checked without writing unless Super Admin deliberately opts into a live restore; passwords reset via Forgot password.

### Ops review (admin/doctor)
- **Trigger:** End of day/week.
- **Steps:** Operational Reports → day/week/month KPIs.

### Patient records — lookup and corrections (admin/doctor)
- **Trigger:** Staff need to see who has visited, fix a duplicate person, or move a visit to the right household member.
- **Steps:** Tenant admin → **Patients** (search by name/phone) → edit demographics, or use **Join two records** / **Move a visit** actions. Run `php artisan patients:backfill` once per environment to link legacy bookings (use `--dry-run` first).
- **Data/systems touched:** `patients`, `bookings.patient_id`.
- **Success:** Each person has one record; family members on one phone can each book the same day; staff can merge mistaken duplicates.
