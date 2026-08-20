# Site Map
Last Updated: 2026-08-20T18:05:38+0600

## Full Site Map

### Central (marketing + Super Admin + Marketer partner + patient Find)
Hosts: values in `CENTRAL_DOMAINS` (e.g. `localhost`).

| Route | Purpose | Access |
|-------|---------|--------|
| `/` | Sales landing for ChamberQ (**Maestro** + **Clinic** cards; modules table under pricing; WhatsApp CTAs); **Find a doctor** + **Patient login** in nav; captures `?ref=` and `?code=` into session | public |
| `/robots.txt` | Crawler rules: allow the sales site and Find; hide Super Admin, partner, `/me`, and tenant private paths (`/*/portal`, `/*/bookings`, `/*/screen`) | public |
| `/sitemap.xml` | XML list of `/`, `/find`, and Front-door path-tenant public pages (custom-domain clinics are omitted here — they publish their own sitemap) | public |
| `/find` | Directory of every Front door doctor who currently accepts online serials (search by name / specialty / area) | public |
| `/me/login` | Patient phone OTP login (optional; not required to book) | public |
| `POST /me/login/otp` | Send 6-digit SMS code (throttled; ChamberQ-paid SMS) | public |
| `POST /me/login/verify` | Verify code and sign in on the `patient` guard | public |
| `/me` | Upcoming serials across every ChamberQ clinic for this phone | patient login |
| `/me/history` | Past visits and prescriptions for this phone (own records; no share-flag gate; no voice/photo) | patient login |
| `/me/prescriptions/{id}` | Full patient pad for one prescription belonging to this phone | patient login |
| `/admin` | Super Admin Filament login | public login |
| `/admin/*` | Super Admin: Tenants under **Platform** (incl. **Product modules**; **Maestro**/Clinic label; Prescription-free-for-life tick; **paying** one-time/monthly; MR + marketer; per-deal % overrides; live list → paying → cuts preview; opt-in **Stations / Referrals / HR / Pharmacy** ticks, default off), Marketers, **Medical representatives**, Discount Codes, Commissions; finance dashboard then platform totals then latest 8 tenants; **Client Health** seller overview (`/admin/seller-overview`, names link to tenant edit); **Research data** aggregate view (`/admin/research`); **Booking window** (`/admin/booking-window`, platform default days ahead; a chamber may set its own shorter window); **Platform data backup** (`/admin/data-backup`, restore defaults to dry-run and confirms before a live replace); Tenants list row actions (Edit / Download chamber backup) sit in a **⋮** menu with finance columns behind the column manager; per-tenant chamber backup download plus Restore/Delete behind **Dangerous** on tenant edit; confirm doctor setup/monthly/**12 months prepaid** on tenant edit | super_admin only |
| `/partner` | Marketer partner panel login | public login |
| `/partner/*` | Marketer: referral link, owed/paid stats, referred doctors list, commission history | marketer only |
| `/up` | Laravel health check | public |

Branded HTML error pages (`resources/views/errors/{403,404,419,429,500,503}.blade.php`) replace Laravel’s grey Forbidden / Not Found screens. JSON API errors stay JSON.

### Platform tenant (central path tenancy)
Same central host; tenant identified by URL slug (tenant `id`), e.g. `drkarim`.

| Route | Purpose | Access |
|-------|---------|--------|
| `/{slug}/` | Branded website home | public (**Front door** module) |
| `/{slug}/robots.txt` | Clinic crawler rules (hide admin, portal, tickets, TV). Google’s official file is still host-root `/robots.txt`; this copy is for custom-domain and path bookmarks | public |
| `/{slug}/sitemap.xml` | Published pages, `/book`, and clinic departments/doctors/blog | public (**Front door**) |
| `POST /{slug}/` | Safety net: same as `POST /{slug}/book` if the homepage form posts to the page it is sitting on | public (throttled, **Front door**) |
| `/{slug}/book` | Booking wizard | public (**Front door**) |
| `POST /{slug}/book` | Homepage hero form target — flashes name/phone to session, redirects to the wizard | public (throttled, **Front door**) |
| `/{slug}/bookings/{booking}` | Patient ticket (sitting window always; live queue / come-around only with **Live queue**) | public (UUID, 60/min throttle) |
| `/{slug}/portal` | Phone lookup — bookings and prescriptions stay open; optional password after the first completed visit (**Prescription**) | public (throttled, **Front door**) |
| `/{slug}/screen/{session}` | Outdoor TV (always today for that schedule session — bookmark once) | public (**Live queue**) |
| `/{slug}/screen/chamber/{chamber}` | Combined waiting-room TV for every live sitting in that chamber today | public (**Live queue**) |
| `/{slug}/screen/{session}/{date}` | Outdoor display for a specific date (legacy / deep link) | public (**Live queue**) |
| `/{slug}/lang/{locale}` | Switch session locale `en` / `bn` (same-host Referer; signed-in staff without Referer return to `/{slug}/admin`) | public |
| `/{slug}/departments` | Clinic departments listing (clinic tier only) | public |
| `/{slug}/departments/{slug}` | Single department page | public |
| `/{slug}/blog` | Clinic health articles listing (clinic tier only) | public |
| `/{slug}/blog/{slug}` | Single blog article | public |
| `/{slug}/doctors` | Clinic doctor profiles listing (clinic tier only) | public |
| `/{slug}/doctors/{slug}` | Single doctor public profile | public |
| `/{slug}/admin` | Tenant staff Filament panel (owner and undivided staff land on the dashboard; a doctor with **Prescription** is sent to Consult Screen; queue-only / money-only / prep-only staff land on Live Queue / Cashbook / Daily Roster) | staff / doctor / admin login |
| `/{slug}/admin/consult-screen` | Doctor's working pad — auto-follows the patient in the chamber | doctor login (**Prescription**) |
| `/{slug}/admin/book-serial` | Book a patient onto a chosen date (same seats as the website) | staff / admin (`canWorkDesk` or owner; **Front door**) |
| `/{slug}/admin/cashbook` | Desk khata: income, expense, net, waived (day/week/month) | staff / doctor / admin |
| `/{slug}/admin/missed-procedures` | Unfinished past-dated intervention rows (WhatsApp + Move; Stations only) | staff / doctor (`canWorkDesk`, **Stations**) |
| `/{slug}/admin/cash-categories` | Income/expense category labels for the cashbook (add, hide, rename custom) | admin only |
| `/{slug}/admin/pharmacy-counter` | Pharmacy till: sell from Rx or walk-in, receipt, same-day void | staff with money job / admin (**Pharmacy**) |
| `/{slug}/admin/pharmacy-items` | Shop stock: add SKU, receive box, return unsold | staff with money job / admin (**Pharmacy**) |
| `/{slug}/admin/pharmacy-physical-count` | Physical count vs system qty (inventory history, not cashbook) | staff with money job / admin (**Pharmacy**) |
| `/{slug}/admin/pharmacy-pay-supplier` | Pay what is owed to the company, or record a supplier refund | staff with money job / admin (**Pharmacy**) |
| `/{slug}/admin/pharmacy-doctor-commissions` | Pending doctor pharmacy cuts → mark paid | staff with money job / admin (**Pharmacy**) |
| `/{slug}/admin/operational-reports` | Day / week / month booking counts | admin / doctor |
| `/{slug}/admin/chambers` | Rooms / locations (sidebar only when multiple chambers) | staff (or doctor if no staff login; admin) |
| `/{slug}/admin/doctors` | Doctor profiles, fees, and per-doctor patient notifications (SMS / WhatsApp) | admin / doctor (`canManageOps`) |
| `/{slug}/admin/schedule-sessions` | Sitting days and hours | staff (or doctor if no staff login; admin) |
| `/{slug}/admin/waiting-for-earlier-date` | Staff list of patients who opted in for an earlier date (WhatsApp per row) | staff (or doctor if no staff login) |
| `/{slug}/admin/data-backup` | Chamber disaster-recovery backup download + restore (Admin only) | admin only |
| `/{slug}/manifest.webmanifest`, `/{slug}/sw.js`, … | PWA | public |

Example: `https://doctorgemini.com/drkarim/book`

### Tenant public (custom domain — optional)
When a doctor connects their own domain (e.g. `drkarim.com`), routes live at the **root** (no `/{slug}` prefix).

| Route | Purpose | Access |
|-------|---------|--------|
| `/{slug?}` | Branded website pages from WebPage builder (home = empty slug) | public |
| `/robots.txt` | Clinic crawler rules on this host | public |
| `/sitemap.xml` | Published pages, `/book`, clinic departments/doctors/blog | public |
| `/departments` | Clinic departments listing (clinic tier only) | public |
| `/departments/{slug}` | Single department page | public |
| `/blog` | Clinic health articles listing (clinic tier only) | public |
| `/blog/{slug}` | Single blog article | public |
| `/doctors` | Clinic doctor profiles listing (clinic tier only) | public |
| `/doctors/{slug}` | Single doctor public profile | public |
| `/book` | Online serial booking wizard | public |
| `POST /book` | Homepage hero form target — flashes name/phone (and chosen centre) to session, redirects to the wizard so patient details never enter the URL | public (throttled) |
| `POST /` | Safety net for the same hero submit if the browser posts the homepage URL; same flash + redirect as `POST /book`. Does not render the homepage | public (throttled) |
| `/bookings/{booking}` | Patient ticket (UUID) | public (60/min throttle) |
| `/portal` | Phone lookup — bookings and prescriptions stay open; optional password after the first completed visit | public (throttled) |
| `/screen/{session}` | Outdoor waiting-room TV (always today — bookmark once per schedule session) | public (throttled) |
| `/screen/chamber/{chamber}` | Combined waiting-room TV for every live sitting in that chamber today | public (throttled) |
| `/screen/{session}/{date}` | Outdoor waiting-room display for a specific date (legacy) | public (throttled) |
| `/lang/{locale}` | Switch session locale `en` / `bn`. Same-host Referer only (off-site Referer is ignored). Signed-in chamber staff with no Referer return to `/admin`; guests stay on the public site | public |
| `/manifest.webmanifest`, `/sw.js`, `/pwa-icon-{192\|512}.svg` | PWA bits (`sw.js` does not intercept `/admin` or `/livewire/`) | public |
| `/admin` | Tenant staff Filament panel (owner and undivided staff land on the dashboard; a doctor with **Prescription** is sent to Consult Screen; queue-only / money-only / prep-only staff land on Live Queue / Cashbook / Daily Roster) | staff / doctor / admin login |
| `/admin/consult-screen` | Doctor's working pad — auto-follows the patient in the chamber | doctor login (**Prescription**) |
| `/admin/book-serial` | Book a patient onto a chosen date (same seats as the website) | staff / admin (`canWorkDesk` or owner; **Front door**) |
| `/admin/cashbook` | Desk khata: income, expense, net, waived (day/week/month) | staff / doctor / admin |
| `/admin/missed-procedures` | Unfinished past-dated intervention rows (WhatsApp + Move; Stations only) | staff / doctor (`canWorkDesk`, **Stations**) |
| `/admin/cash-categories` | Income/expense category labels for the cashbook (add, hide, rename custom) | admin only |
| `/admin/pharmacy-counter` | Pharmacy till: sell from Rx or walk-in, receipt, same-day void | staff with money job / admin (**Pharmacy**) |
| `/admin/pharmacy-items` | Shop stock: add SKU, receive box, return unsold | staff with money job / admin (**Pharmacy**) |
| `/admin/pharmacy-physical-count` | Physical count vs system qty (inventory history, not cashbook) | staff with money job / admin (**Pharmacy**) |
| `/admin/pharmacy-pay-supplier` | Pay what is owed to the company, or record a supplier refund | staff with money job / admin (**Pharmacy**) |
| `/admin/pharmacy-doctor-commissions` | Pending doctor pharmacy cuts → mark paid | staff with money job / admin (**Pharmacy**) |
| `/admin/referring-doctors` | Outside GP registry (Referrals module) | staff / doctor / admin |
| `/admin/referral-commissions` | Referral commission ledger — pending/paid, bulk payout | staff / doctor / admin |
| `/admin/employees` | Staff roster (HR module) | admin |
| `/admin/attendance-records` | Daily attendance | admin |
| `/admin/leave-requests` | Leave requests — approve/reject | admin |
| `/admin/payroll-payments` | Monthly salary payments (posts to cashbook) | admin |
| `/admin/operational-reports` | Day / week / month booking counts | admin / doctor |
| `/admin/chambers` | Rooms / locations (sidebar only when multiple chambers) | staff (or doctor if no staff login; admin) |
| `/admin/doctors` | Doctor profiles, fees, and per-doctor patient notifications (SMS / WhatsApp) | admin / doctor (`canManageOps`) |
| `/admin/schedule-sessions` | Sitting days and hours | staff (or doctor if no staff login; admin) |
| `/admin/waiting-for-earlier-date` | Staff list of patients who opted in for an earlier date (WhatsApp per row) | staff (or doctor if no staff login) |
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
| `GET /api/screen/chamber/{chamber}` | Combined chamber TV poll (always today) | public (throttled) |
| `GET /api/screen/{session}/{date}` | Screen poll for a specific date (legacy) | public (throttled) |
| `POST /api/bookings/{booking}/sms/cancellation` | Staff-tapped cancellation SMS (prepaid; gated by doctor `cancellation` Push SMS) | auth, same tenant (ops/queue/visit-notes/desk), throttled |
| `POST /api/bookings/{booking}/sms/confirmation` | Staff-tapped booking confirmation SMS (prepaid; gated by doctor booking Push SMS) | auth, same tenant (ops/queue/visit-notes/desk), throttled |
| `POST /api/bookings/{booking}/sms/late` | Staff-tapped doctor-late SMS (prepaid; gated by doctor late Push SMS) | auth, same tenant (ops/queue/visit-notes/desk), throttled |
| `POST /api/bookings/{booking}/sms/follow-up` | Staff-tapped follow-up reminder SMS (prepaid; gated by doctor follow-up Push SMS) | auth, same tenant (ops/queue/visit-notes/desk), throttled |
| `POST /api/prescriptions/{prescription}/sms` | Staff-tapped prescription-link SMS (prepaid; gated by doctor after-visit Push SMS) | auth, same tenant (ops/queue/visit-notes/desk), throttled |
| `POST /api/bookings/{booking}/sms/review` | Staff-tapped Google-review SMS when there is no ChamberQ Rx (prepaid; same after-visit Push SMS) | auth, same tenant (ops/queue/visit-notes/desk), throttled |

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
| `GET /portal/prescriptions/{prescription}?phone=` | Durable portal backup when staff forget to send `/p/{token}`. Phone must match. Password only if the patient chose one. | public, phone-gated, throttled 30/min |
| `POST /portal/rx-password` | Optional: set prescription password (only after a completed visit) | public, throttled 10/min |
| `POST /portal/rx-unlock` | Unlock old prescriptions for this session | public, throttled 10/min |
| `POST /portal/rx-lock` | Hide prescriptions again | public, throttled 20/min |
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
2. Book flow — chamber/doctor when needed, then **When can you come?** (only dates with seats left, soonest first; earliest option highlighted). **Your details** under the booking summary strip (Name / Phone / Year of birth optional / NID optional / Different WhatsApp / Who for?; **Share with other ChamberQ doctors**); **Change date** on the summary strip (or Back). If the number is known, choose **Who for?** inline — masked initials (`F. R., 34`); picking one stands the name field down. Clinic hero form POSTs to `/book` (phone then name flashed, never in the URL); `POST /` on the clinic host is the same handler if the browser posts the homepage instead. On a multi-branch clinic it asks **Which centre?** first so Dhaka and Chittagong sittings are never mixed in one list. A ChamberQ patient login on the same host prefills name/phone.
3. Submit → ticket at `…/bookings/{uuid}`. Goal: proof of serial; share via WhatsApp/copy, or Print / Save as PDF for a paper or file copy. With **Live queue**, the ticket also offers **জানাতে দিন** (Bangla): Allow once so the phone can buzz when the serial is two away / next / called, even if the ticket is closed. If Allow is blocked, the copy says to come at ticket time or sit by the TV.
4. Optional: PWA install scoped to tenant path or custom domain.

### Patient → Find a doctor (ChamberQ directory)
1. Open `/find` from the marketing nav or directly — browse every bookable ChamberQ doctor. Goal: pick a doctor without hunting their website.
2. Optional: **Patient login** at `/me/login` (phone + SMS code) to see every serial and prescription in one place. Booking does not require this.
3. Tap **Book serial** → that doctor's existing wizard at `/{slug}/book` (same BookingService). Ticket still lives on the doctor's site and also appears under `/me` after login.

### Patient → check status later
1. Open `/portal` or ticket link — or, with a ChamberQ login, `/me`.
2. Portal: enter BD phone → see matching bookings **and** prescriptions (open by default). After a completed visit they may optionally set a password; until they do, pads stay visible. `/me` lists every clinic for that phone without re-typing it.
3. Tap **View prescription** → full pad (phone must still match, or the logged-in account owns it). If they chose a portal password, enter it first. SMS `/p/{token}` still opens today’s pad for 48h without it. Doctors on Consult Screen still see shared history from other ChamberQ clinics **without** this password. Reception can clear a forgotten password on **Patients**.

### Patient → waiting room
1. Watch the outdoor TV (staff bookmark `/screen/{session}` once for one sitting, or `/screen/chamber/{chamber}` for every live room in that chamber today).
2. Hear call chime when serial is called.

## Admin/Staff Journeys

### New tenant → go live (Super Admin)
- **Trigger:** Sales closes a doctor/clinic.
- **Steps:** Create Tenant with URL **slug** (e.g. `drkarim`; rejected if already taken or if it matches a reserved path prefix such as `admin` / `book` / `find` / `me`), **owner login email** (founder — may not be a doctor), **doctor login email** (required), and optional **helper email** (defaults to `support@{slug}.chamberq.internal`). Plan Tier starts as **Maestro**, billing as trial, SMS as 0, all three modules on, theme and locale filled — change them only when the deal is not the default. Optional custom **domain** (repeater starts empty). Tick Prescription free for life if honouring it → type a **paying** price if this doctor gets a courtesy → attach **marketer** and **medical representative** (leave MR empty for a direct sale) / **discount code**, read the labeled list → paying → cuts preview → Create → copy the one-shot **owner / helper / doctor** passwords from the notification → hand off owner + doctor logins to the client (never the helper password).
- **URLs:** Platform `/{slug}/…`; after custom domain DNS, also `drkarim.com/…` at root.
- **Modules:** Front door alone = website + book + day list (no outdoor TV / Call next / come-around). Live queue adds TV + live ticket. Prescription adds consult/Rx. Booking confirmation SMS is optional (credits + doctor toggle).
- **Success:** Enabled module routes work; disabled ones 404. Owner at `/{slug}/admin` (or `/admin` on custom domain). ChamberQ uses the **helper** login on the same URL — invisible on the owner’s Staff & Roles list.

### Set how far ahead patients can book (Super Admin)
- **Trigger:** Owner wants a shorter or longer online booking window for Front doors that have not typed their own, or a clinic wants its own window.
- **Steps:** Super Admin → **Platform → Booking window** (`/admin/booking-window`) → enter days (1–365) → Save. That number is the fallback. To set one clinic only: tenant edit **Patient booking window**, or Branding Settings → Desk.
- **Data/systems touched:** `platform_settings.patient_booking_horizon_days` (fallback); `tenants.patient_booking_horizon_days` (optional override). Public book APIs, the clinic hero **Select date** list (sitting days only), and **Operations → Book serial** read the chamber’s window; Daily Roster **New Walk-In** (today only) does not. Stations OT handoff still uses the platform number.
- **Success:** The date step on that chamber website only offers sittings inside that many days from today. Other chambers keep the platform number unless they set their own.

### Confirm doctor payment & pay marketer (Super Admin)
- **Trigger:** bKash/bank payment received from doctor.
- **Steps:** Tenant edit → tick modules and Prescription free for life if honouring it, set **paying** amounts and % overrides if this deal is special, attach MR + marketer → **Confirm setup paid**, **Confirm monthly paid** (period YYYY-MM; year 1 has no partner cut), or **Confirm 12 months prepaid** after setup is paid (year lump, not 10% × 12) → Commissions list shows **owed** for marketer and MR → **Mark payout paid** with bKash trx note.
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

### Book a serial from the desk or call centre (chosen date)
- **Trigger:** Someone phones (or a receptionist books for a relative) for a sitting that is **not** “already standing at the door today.”
- **Steps:** Staff or owner → **Operations → Book serial** → pick **doctor** (pre-filled when the login only sees one) → pick **visit type** (Usual, Follow-up, Intervention if Stations is on, Lab if Stations or lab tests) → if Intervention and the fee list has procedures, pick **intervention type** (PRP, epidural, …) → if Lab, pick **lab type** (MSK, a named test, or collection window) → pick **centre** only when that doctor sits at more than one place → pick a **date** (calendar greys days they do not sit) → pick the matching **sitting** → name and phone → optional Different WhatsApp → Book. Confirmation modal (Push WhatsApp / Push SMS when those are on / Open ticket / Done). Auto SMS still goes after the response when ticked. Report / counseling stay on the floor handoff, not this page. **New Walk-In** on Daily Roster / Live Queue is the same visit types for people already at the chamber **today** (overflow stools; Live Queue is already on that sitting).
- **Data/systems touched:** `BookingService::createBookingForBookable` (`allowOverflow` false, `sendSms` true), `schedule_sessions`, `bookings` (optional `fee_catalog_item_id`), optional `SendBookingConfirmation`.
- **Success:** The serial appears on Daily Roster when staff pick that date; the published cap is full when the website would also say full.

### Open clinic day → run queue
- **Trigger:** Session day starts.
- **Steps:** Queue runner (staff or doctor per Branding **Who runs the queue**) → Live Queue Control → optional **Buzz this phone** (staff pocket alert when a sticky note appears) → session auto-selected when today has only one, else pick from the dropdown/session cards → **Open screen / Copy link** once onto the waiting-room TV (stable URL, no date — bookmark and reuse every day for that session). Stations clinics also get **All rooms TV** (`/screen/chamber/{chamber}`) → Start → Call → Patient arrived → Complete. If the line drops, the TV keeps the last number on screen and Call next still works **on this computer** (HDMI laptop or signed-in browser on the TV); replay uploads when the line returns. **After 10 minutes past sitting time**, if patients are waiting and nobody has Started, Daily Roster, Live Queue Control, and Consult Screen show a sticky amber note counting minutes late and waiting patients — Mark Late or Start (no patient SMS from this note). **After Start**, if nobody has been called for 10+ minutes, the same surfaces show an idle-after-start note (“Is the doctor in the chair?”) — clears on Call next, in chamber, **Doctor stepped out**, or End. **Start** after sitting time asks Mark Late / Just start / Cancel; if they already marked late and arrive before the announced time, Start now / Wait until that time / Cancel. **Doctor stepped out** blocks Call next and Call now until **He's back**. A no-response patient is skipped from the current-call card (twice, then no-show); any waiting or skipped patient can be called out of turn via **Call now** on their row (including overflow stools before the published line finishes). Desk walk-ins after the published cap use **Extra walk-in seats** on the sitting (staff only; online stays “full”). Booking SMS and the wizard confirm flash include a published **come around** time when live queue is on (unavailable while someone is in the chamber). Mark Late, Pause, Resume, Cancel session and Finish/End session all live behind the header's **Session actions** menu; **New Walk-In** is the standalone header action. **Mark Late** is also on **Daily Roster** (table header) so staff can warn waiting patients before opening Live Queue Control or pressing Start — same delay SMS / WhatsApp hand-off; on a `delayed` sitting it becomes **Add time** and only accepts a **larger** total delay. **Cancel session (doctor absent)** and **Finish/End session** behave identically toward patients: both name the count and the patients in their confirmation, both leave a patient already in the chamber as *completed* rather than cancelled, and both auto-SMS remaining patients when that doctor's Cancellation SMS is on, and both surface **Tell cancelled patients** afterwards for WhatsApp taps. Amber **patients today without notes** banner at the top when completed patients lack notes (**Fill in now** opens the catch-up list). Doctor opens **Consult Screen** for auto-updating patient context (no search). **Daily Roster** is the morning board (pick a **date** to see that day’s list; walk-ins, **Mark Late**, waiting **Outdoor vitals** stay on today) and the leftover list after the sitting. **Live Queue Control** is the counter **during** the sitting: after checkup, **Collect fee** is on the current-patient card and on the table (not on Consult Screen). Owner/helper can turn on Branding **Desk → Collect fee at check-in** so waiting patients also show Collect fee. Each patient row keeps at most two open buttons plus **More**. **Operations → Cashbook** is where rent/tea/salary go out.
- **Success:** Outdoor screen matches control panel; consult screen shows the patient in chamber; the summary strip's waiting count and projected finish time match the table. Patients who tapped **জানাতে দিন** get a Bangla pocket buzz at two away / next / called without staff sending SMS or WhatsApp.

### Tell waiting patients the doctor is late
- **Trigger:** Doctor will arrive late (call from traffic, emergency, etc.) and patients are already booked for today.
- **Steps:** Queue runner → **Daily Roster** → **Mark Late** → pick session (auto-filled when only one) → pick delay (15–120 min) → confirm (shows SMS credit cost when late Auto SMS is on). Or the same action under Live Queue Control → **Session actions** → **Mark Late** (no need to press Start). Consult Screen Mark Late is the same for a doctor-run queue. On a `delayed` sitting, **Add time** only accepts a **larger** total (30 → 60, not 15). If late Push SMS or Push WhatsApp is on, **Tell waiting patients** appears for tap-to-send.
- **Data/systems touched:** `live_sessions` (status `delayed`, `delay_minutes`), waiting/called/skipped `bookings`, optional `SendDoctorLateNotices` SMS, patient ticket delay banner.
- **Success:** Patients see/hear the delay; session is marked delayed until Start clears it; SMS wallet only charged when late SMS is enabled.

### Doctor consult (doctor role)
- **Trigger:** Doctor signs in, or staff call a patient into the chamber (`live_sessions.current_booking_id` set).
- **Steps:** Login, the sidebar logo, and `/{slug}/admin` all open **Consult Screen** (that is the doctor's home when Prescription is on — not the stats dashboard). The screen updates automatically (poll). From **768px up** (tablet as well as desktop) while the patient is in the chamber, the page *is* the prescription pad — below 1024px its two columns stack, prescription first, with touch-sized chips: sticky bar pinned below the page content header (name · age · sex · serial, allergy, **Preview / My paper tick / Save & print**; **Complete visit** is on that same header), left column C/C mini-table · H/O toggles (More includes COPD / Allergy) · O/E compact vitals line (Wt / BP / P / SpO₂ / T °F on one wrapping row, finding chips into Other findings, **weight/BP trend charts** when past data exists) · Dx pill · Inv list · **Reports** (typed note + photos of papers the patient brought) (plus last-visit copy), right column medicine spreadsheet (search, **Why?** typeahead under the brand, dose/frequency/duration chips, timing, drag handle + ↑↓, shorthand line, **warn-only duplicate/allergy checks**), advice chips then the box, and follow-up underneath. How much of that is already filled when he arrives: H/O is seeded from the patient's stored conditions and medicines; C/C is a row list (~40 bilingual chips): each tap adds a complaint line with its own duration; a previous visit offers **Same medicines / Same Dx / Same Inv / Same advice / Repeat whole visit**; picking a diagnosis surfaces his own **packs** for it, **Add investigations**, and a starter-advice chip in the **Advice** box (the actual sentence; tap to copy; a save does not hide it) plus five common advice chips (Bangla lines) and ★ save-as-mine on this computer; and typing a brand brings its dose, frequency, duration and timing with it — his own saved default first, the catalogue's per-drug default otherwise, blank when neither knows. **Why?** under the brand suggests as he types. **Vitals are never pre-filled**; last visit's shows grey beside each box. **★** on a row saves that line to My medicines; **Packs** applies one the doctor built earlier on **My medicines** — packs cannot be created or renamed from here. **The pad saves itself** — a second after he stops typing and the instant he clicks outside it — and says so in the bar (Unsaved / Saving… / Saved), so Complete visit can no longer close a visit on an unwritten script. Follow-up offers 1 week / 2 weeks / 1 month / 3 months / As needed / Pick a date. Only on **phones (<768px)** does the older layout stay: review history, then **Write prescription** modal. The consult ends in **two steps**: **Complete visit** (summary when notes exist) closes the visit *without* advancing the queue; Print / Send via WhatsApp while the patient is still on screen; then **Call next patient**. Staff completing from the queue skip the modal. Catch-up for patients completed without notes lives on **Live Queue Control** (amber banner + **Fill in now**), not here.
- **Data/systems touched:** `live_sessions`, `bookings`, `patients`, `visit_records` (including `chief_complaint` / `history` / `on_examination` / `temperature_f` / `report_photo_paths`), `prescriptions`, `prescription_items` (including `timing` / `indication` / `instructions`); reads `medicines` dosing defaults and `conditions` advice/investigation presets; writes `medicine_usages` (★); reads `prescription_templates` but never writes them (packs are built on My medicines).
- **Key CTA:** Save (desk) or Write prescription (phone) → Complete visit → Print / Send via WhatsApp → Call next patient.
- **Note:** Everything the pad fills is a proposal on a document the doctor signs — visible, editable, cleared in one keystroke, and never committed until Save. Nothing is learned from what he prescribes: packs and My medicines entries only ever come from an explicit tap. If the internet drops mid-consult, Save & print still work on this computer; Call next stays frozen until the line is back.
- **Success:** Doctor sees correct person and honest history state; visit notes saved when provided; the prescription can be printed or sent before the next patient is called; the shared link and portal show the full clinical pad for that prescription (voice/photo stay doctor-only); ticket itself still shows no clinical data.

### Desk staff sign-in (divided jobs)
- **Trigger:** Owner ticked only Money, only Queue, or only Prep on a Staff login.
- **Steps:** That person signs in (or taps the sidebar logo, or opens `/{slug}/admin`). They land on **Cashbook**, **Live Queue Control**, or **Daily Roster** — the page they were hired for. Lead desk and staff with every job (or more than one job) still open the stats dashboard.
- **Data/systems touched:** `users.desk_jobs`, `FilamentPanelUrl::home()`, `StaffDeskJobs::loginPageRelativeName()`.
- **Success:** A queue-only login is not dumped on the dashboard they never use.

### Visiting day / camp (doctor — bad internet away from the chamber)
- **Trigger:** Doctor will sit somewhere with unstable internet (village camp, second chamber, outreach), or the main chamber line is expected to drop.
- **Steps:** On good internet → **Operations → Visiting / camp** → **Pack bag** (copies packs, My medicines, known patients, letterhead onto this laptop). At the remote room → add name + phone or pick a packed patient → write medicines from the bag → **Save & print** (the printed sheet is what the patient takes home). When signal returns → **Upload pending visits** (or the yellow banner's Upload now) so the visit is stored in ChamberQ. This page has no WhatsApp/SMS send. This is not Live Queue — no Call next, no outdoor TV.
- **Data/systems touched:** IndexedDB on the laptop; `GET /api/offline/bag`; `POST /api/offline/sync` → `bookings` + `visit_records` (`offline_sync_id`) + `prescriptions`. Does not mutate the live queue.
- **Success:** Patient leaves with a printed pad; the same visit appears in ChamberQ when the line is back, without calling the next serial at the main chamber.

### Type up a paper prescription (staff — only for doctors who opted in)
- **Trigger:** A doctor who prescribes by hand has finished a consult, and `staff_may_enter_prescriptions` is on for that doctor (Doctors resource; **off by default**).
- **Steps:** Admin or doctor first turns on **Staff may type this doctor's prescriptions** on the Doctors record. Then staff → **Daily Roster** → the patient's *completed* row → **More** → **Enter prescription** (**Edit prescription** once one exists) → photograph the paper slip → photograph any lab reports / X-rays the patient brought → pick medicines from the grouped dropdown with dose/frequency/duration chips → set follow-up → Save. Booking status is unchanged and there is no doctor approval step. The form shows medicines, follow-up, the slip photo and report photos only — staff see no diagnosis, advice, typed reports note, voice note, allergy strip or past visits, and cannot open Consult Screen or the prescription print route.
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

### Staff & Roles (owner / helper / lead desk — settings)
- **Trigger:** Owner adds a partner, doctor login, or desk staff member; a **lead desk** supervisor hires counter staff; ChamberQ helper manages logins the owner must not see.
- **Steps:** **Settings → Staff & Roles** — list is grouped by job (Owners / Doctors / Desk staff) with filter chips. Create user → pick **Owner**, **Doctor**, or **Staff (desk + content)**. For **Staff**, optional **Desk jobs** ticks: **Money** (Collect fee + Cashbook + Pharmacy till and shop stock when that module is on), **Queue** (Live Queue + Call next), **Prep** (outdoor vitals + Mark prepped); leave all ticked for one person doing everything. **Lead desk** (owner/helper only) covers all counters and may add/edit **Staff** only — not owners, doctors, or helpers. On multi-branch clinics, optional **Branches** multi-select (empty = all branches; hidden when only one chamber). For desk staff, optional **Works for** one doctor (empty = hospital team at their branch(es)). ChamberQ helpers never appear for the owner; Super Admin adds/removes helpers separately. A lead with branch lock only hires staff for those branches.
- **Data/systems touched:** `users`, `users.desk_jobs`, `users.desk_is_lead`, `chamber_user`, `users.assigned_doctor_id`.
- **Success:** Owner can scan who is who at a glance; till / queue / weight counters only see their buttons; lead desk can grow the team without owner passwords; branch-locked reception only sees their roster/queue/cash; a doctor’s assistant only sees that doctor’s sittings.

### Content update (staff)
- **Trigger:** Doctor wants copy/photo change.
- **Steps:** Staff edits Web Page blocks in tenant admin. Hero photo and **Latest Educational Videos** cover/video are file uploads (or a YouTube link). Clinic tier can also add/edit **Departments**, **Blog posts**, and doctor **Website profile** fields under **Website**.

### Clinic website content (admin/staff — clinic tier)
- **Trigger:** Clinic needs a new department, blog article, or public doctor profile.
- **Steps:** Tenant admin → **Website** → **Departments** / **Blog posts** (create, publish) or **Doctors** → enable **Show on website**, photo, bio, slug → homepage sections (`service_matrix`, `health_insights`, `doctor_grid`) pull published rows automatically.
- **Data/systems touched:** `departments`, `blog_posts`, `doctors` website columns; public routes `/departments`, `/blog`, `/doctors`.
- **Success:** List + detail pages live; homepage teasers match without duplicating cards in the page builder.

### Follow-up reminders (staff — desk)
- **Trigger:** A visit has a follow-up date set and the prescribing doctor has follow-up notifications enabled.
- **Steps:** **Auto SMS:** daily job texts patients **3 days before** the follow-up date when the doctor's follow-up Auto SMS is on (1 credit; empty wallet skips). **Push WhatsApp / Push SMS:** when those are on, staff (or the doctor if this practice has no staff login) get a panel notification → **Operations → Follow-up reminders** → tap **Push WhatsApp** or **Push SMS** per row.
- **Data/systems touched:** `visit_records.follow_up_date`, reminder timestamp columns, `SmsMessage` (`purpose=follow_up`), doctor `notify_channels.follow_up`.
- **Success:** Patient is reminded before the follow-up; WhatsApp never sends without a human tap. Push SMS also needs a tap unless Auto SMS is on.

### Patient notifications (owner / doctor — settings)
- **Trigger:** A clinic wants ChamberQ to text patients itself, or wants staff to tap SMS / WhatsApp, for booking, late, cancellation, after the visit, or follow-up.
- **Steps:** **Settings → Doctors** → that doctor's **Patient notifications**. Each message type has three ticks: **Auto SMS** (ChamberQ sends from prepaid credit), **Push SMS** (staff tap Send SMS, still prepaid), **Push WhatsApp** (staff tap WhatsApp, free, never auto). A solo practice is one doctor, so this is that client's mix.
- **Data/systems touched:** `doctors.notify_channels` (`auto_sms` / `push_sms` / `push_whatsapp` per stage).
- **Success:** Staff only see Push buttons for what that doctor ticked; Auto SMS only fires when Auto is ticked.

### Repeating serials (staff — desk, per doctor)
- **Trigger:** Admin has ticked **Staff may book repeating serials** on that doctor (off by default). A patient needs the same sitting for later weeks (physio course, dressings).
- **Steps:** Daily Roster row → **More** → **Repeat sitting** → how many more sittings (1–12) → confirm dates. **Cancel later sittings** keeps this visit and cancels later waiting dates in the series.
- **Data/systems touched:** `doctors.allows_repeat_serials`, `bookings.repeat_series_id`, `RepeatBookingService` → `BookingService::createBookingForBookable` (published cap, no confirmation SMS).
- **Success:** Future roster days already have her name as normal serials. Today’s live queue is unchanged.

### Chamber cashbook (staff / doctor / admin — ops)
- **Trigger:** A patient pays at the desk, or the chamber spends money (rent, tea, salary).
- **Steps:** **Without Stations:** On **Doctors**, set **Consultation fee** and optionally **When to collect this doctor's fee** (clinic default is Branding → Desk). Leave **Other visit fees** empty if every visit is the same price; add named rows (e.g. Follow-up ৳500) only if this doctor charges more than one price. On **Live Queue** (after checkup, or at arrival if Branding **Collect fee at check-in** is on, or that doctor's override says so) and on **Daily Roster** leftovers, **Collect fee** — staff pick how they paid (cash, one online method, or **Cash + online** with cash ৳ and online ৳ that must add up to the locked fee) or waive; they cannot type the total. If extra fees exist they pick the visit type first. If the patient paid at the door and then **no-shows** (or the sitting is cancelled), Cashbook posts a **Patient refund** for the same amount. **With Stations (Super Admin opt-in):** On **Operations → Fee catalogue**, set each visit/procedure board price and clinic house share (MSK scan ৳2,200 on MUPS). On **Live Queue** and **Daily Roster**, **Collect fee** — pick a catalogue chip, type cash ৳ and mobile ৳ (discount and clinic/doctor split compute automatically; overpay is rejected). If **Referrals** is on, pick **Referred by** when a patient was sent by an outside doctor (also on New Walk-In; tap **+** if they are not in the list yet). Consult sittings hide Collect fee. Report and counseling hide Collect fee and skip a voucher number only when Branding (or that doctor’s override) says the seat is free. Owner sets the clinic default under **Branding Settings** (follow-up months / unlimited / always new; report and counseling always free, always paid, or one price for a while then another). Each doctor can keep the clinic default or set their own on **Doctors**. End-of-day money is **Operations → Cashbook**. On **Operations → Cashbook**, **Add expense** or **Add income** (each picks a category), then read day/week/month income, expense, net, waived ৳, and (Stations) clinic/doctor/discount columns. The account owner maintains category labels under **Operations → Cash categories** (hide unused headings, add custom ones such as Cleaning or Room rent).
- **Data/systems touched:** `chamber_cash_entries`, `cash_categories`, `CashCategoryService`, `PatientFeeRefundService`, `doctors.default_fee_taka`, `doctors.extra_fees`, `doctors.collect_fee_at_checkin`, `doctors.practice_rules`, `tenants.collect_fee_at_checkin`, `tenants.practice_rules`, `fee_catalog_items`, `ChamberCashService` / `StationsTillService`. Patients still pay at the chamber — no booking gateway.
- **Success:** End of day the khata shows what came in, what went out, clinic vs doctor share (Stations), and what is left.

### Outside GP referral payouts (when Referrals module on)

- **Trigger:** A patient arrives with a letter or name from an outside GP who sent them to the clinic.
- **Steps:** Super Admin turns on **Referrals** for the tenant. Owner can add GPs under **Operations → Referring doctors**, or desk staff tap **+** on **Referred by** at **Collect fee**, **New Walk-In**, or **Book serial** (name required; the same name is reused so payouts do not split). When the fee is collected, the system owes whatever **Branding Settings → Outside GP cut** says for visit / intervention / MSK (৳0 means no ledger row). MUPS seed types ৳200 / ৳1,000 / ৳0; other clinics type their own. End of month → **Operations → Referral ledger** → select pending rows → **Mark selected as paid** (posts one cashbook payout). Missed visits after door pay void pending commissions.
- **Data/systems touched:** `referring_doctors`, `bookings.referring_doctor_id`, `referral_commissions`, `ReferralCommissionService`, cashbook `referral_payout` expense.
- **Success:** Owner can show Dr Karim exactly how many patients he sent and what is still owed vs paid.

### Staff HR (when HR module on)

- **Trigger:** Owner needs attendance, leave, and salary on the same system as the till.
- **Steps:** Super Admin turns on **HR**. **HR → Employees** — add roster (link desk login if they have one). **Attendance** — mark present/late/absent per day. **Leave** — staff requests; owner approves or rejects on the list. **Payroll** — record monthly salary (auto-posts **Salary** expense to cashbook).
- **Data/systems touched:** `employees`, `attendance_records`, `leave_requests`, `payroll_payments`, `HrPayrollService`.
- **Success:** Salary and referral payouts both appear in Cashbook; no parallel Excel HR sheet required for day-to-day ops.

### Pharmacy counter (when Pharmacy module on)

- **Trigger:** The chamber sells medicines from a cupboard (or a small shop) and needs the till and the shelf to stay in step — without an online patient checkout.
- **Steps:** Super Admin ticks **Pharmacy**. Desk with the **Money** job (or owner) **Pharmacy stock** — add what this shop actually holds (search the national list, set sell price and company share, receive a box: pay ৳0 / some / full now, mark returnable or bought outright). Same desk opens **Operations → Pharmacy** — pick today’s prescription or a walk-in name, add lines, take cash / bKash / Nagad / card / cash+online (same as Collect fee). Receipt prints. Live qty drops. **Pay supplier** when the company should get its share of what sold (or to record a refund if a returnable box came back after an overpay). Optional **Doctor pharmacy cuts** (Branding % of shop cut, default 0 = off) only on Rx-linked sales; walk-in is ৳0. **Physical count** when the cupboard is counted: type what is on the shelf; the gap is stock history, not a cashbook line. Same-day **Void** undoes the sale. A later dedicated chemist staff login can take stock without a new till role — not split yet.
- **Data/systems touched:** `pharmacy_items`, `pharmacy_deliveries`, `pharmacy_sales` / `_items`, `pharmacy_counts` / `_items`, `pharmacy_stock_adjustments`, `pharmacy_supplier_settlements`, `pharmacy_doctor_commissions`, `PharmacySaleService` / `PharmacyStockService` / `PharmacySupplierService` / `PharmacyDoctorCommissionService`, cashbook `pharmacy` / `pharmacy_purchase` / `pharmacy_refund` / `pharmacy_supplier_refund` / `pharmacy_doctor_payout`.
- **Success:** End of day the khata shows the full sell price that came in; the shelf qty matches what was sold/received/counted; the company is owed only for what the deal says (sold vs bought-outright); the doctor is not silently owed a walk-in cut.

### Stations clinic floor (staff / doctor — when module on)
- **Trigger:** A pain clinic runs consult / visit / MSK / intervention / report / counseling rooms, outdoor vitals, procedure handoffs, or one-off sitting hours.
- **Steps:** Super Admin ticks **Stations** on the tenant. Staff set **Schedule Sessions** room type (visit / MSK / intervention / report / counseling; leftover `consult` still resolves). Missing rooms are skipped. **New visit:** from Visit, **Send to lab** (then type — today MSK) and/or **Send to intervention** (then type). Lab still continues to intervention → report → counseling; skipping lab goes straight to intervention then report. **Follow-up** (clinic/doctor rules on Branding / Doctors — default is a completed visit on a previous day within 3 months; after that window a return is a new visit even on the 4th time; unlimited never expires): from Visit, **Send to lab** then **Send to report**, or **Send to intervention** then **Send to counseling**. **Direct intervention** walk-in: Procedure done → **Send to counseling**. **MSK-only (outside GP sent them for a scan, no visit):** New Walk-In on today's **MSK** sitting, pick the referring doctor, collect the scan fee at the door. **Daily Roster / Live Queue:** **Outdoor vitals** is a primary on waiting rows (BP + weight) when Stations and the prep desk job are on → doctor consult pad prefills today's numbers. Extra steps sit under **More**. **Send to lab** asks for lab type then puts them on today's list. **Send to intervention** opens a sitting picker plus procedure type: same day is always listed (even when OT was this morning, before visit hours); the default is the next sitting that has not ended. Confirming creates a linked procedure serial. If they cannot come, **Move intervention** picks another sitting. Forgotten next-day OT dates sit on **Operations → Missed procedures** (WhatsApp + Move; nothing auto-cancels). **Operations → Sitting day overrides** for tomorrow's hours without rewriting Saturday forever. Doctors receive a **Morning queue count** notification after 09:05 when visits or procedures are waiting. Waiting-room TV: per sitting `/screen/{session}`, or **All rooms TV** `/screen/chamber/{chamber}` (up to six live tiles).
- **Data/systems touched:** `schedule_sessions.kind`, `bookings.voucher_number`, `bookings.related_booking_id`, `bookings.procedure_status`, `bookings.care_path` / `care_branch` / `care_origin_id`, `CarePath`, `PracticeRules`, `tenants.practice_rules`, `doctors.practice_rules`, `schedule_session_overrides`, `visit_records` vitals, `SendStationsMorningCountPushes`.
- **Success:** Desk follows the paper path for that patient; clinics without MSK/Report rooms still run visit → intervention → counseling; a referred MSK-only patient can be walked onto today's MSK list without a visit; MSK rows take a scan fee at the door; report/counseling rows take a voucher only when the clinic’s room-fee rules charge for that seat; the doctor sees outdoor vitals before opening the pad.

### Earlier-date waiting list (staff — ops)
- **Trigger:** Legacy bookings still flagged `wants_earlier_date` (the public wizard no longer offers the opt-in), or staff want to contact those patients when a seat frees up.
- **Steps:** Tenant admin → **Operations** → **Waiting for earlier date** — review future flagged bookings (soonest booked date first) → tap **WhatsApp** per row to message that patient (staff-tapped; no automatic SMS).
- **Data/systems touched:** `bookings.wants_earlier_date`, `bookings.booking_date`, patient name/phone on the booking row.
- **Success:** Staff can reach patients who previously asked to move earlier without hunting through the full roster.

### Missed procedure follow-up (staff — Stations)
- **Trigger:** A patient was booked onto a later intervention sitting (often next day) and did not attend. No SMS is sent for that booking.
- **Steps:** Tenant admin → **Operations → Missed procedures** — unfinished past-dated intervention rows (patient, phone, sitting, missed date, days overdue, originating visit) → **WhatsApp** (staff-tapped `wa.me`) or **Move intervention** onto another sitting. Rows stay until done, cancelled, or moved; nothing auto-no-shows.
- **Data/systems touched:** `bookings.procedure_status`, `bookings.booking_date`, `StationsHandoffService::overdueProceduresQuery()` / `moveProcedure()`.
- **Success:** Forgotten OT dates are still on a list staff can act on; a moved row leaves the list.

### Block a date — vacation / holiday / doctor away (staff / admin)
- **Trigger:** The clinic, a chamber, or one doctor will not sit on a given date.
- **Steps:** Staff (or the account owner; or the doctor only when there is no staff login) → Slot Blocks → New → pick date, optionally chamber and/or doctor → the form shows how many bookings this will cancel and requires the confirmation checkbox → Save. Saving cancels those bookings (waiting/called/in-chamber only — completed visits are left alone) and reports the count. Then **Notify patients** on that block row → tap each patient to open WhatsApp with a prepared message.
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

### Ops review (admin / doctor)
- **Trigger:** End of day/week.
- **Steps:** Operational Reports → day/week/month KPIs.

### Set sitting rooms and hours (staff)
- **Trigger:** A new room, a changed evening time, or a second chamber opens.
- **Steps:** Staff (or the account owner; or the doctor only when there is no staff login) → **Settings → Chambers** (address, map link; the list is in the sidebar only when the practice has more than one chamber) and **Settings → Schedule Sessions** (which doctor, which room, which days, start/end, how many serials).
- **Data/systems touched:** `chambers`, `schedule_sessions`.
- **Success:** Patients see the right rooms and hours on the booking wizard; the outdoor TV label matches the sitting.

### Patient records — lookup and corrections (admin/doctor)
- **Trigger:** Staff need to see who has visited, fix a duplicate person, move a visit to the right household member, or mark someone treated on paper before ChamberQ as a returning patient.
- **Steps:** Tenant admin → **Patients** (search by name/phone) → edit demographics including **Seen here before ChamberQ**, or use **Join two records** / **Move a visit**. Desk staff (who cannot open Patients) pick **Follow-up** on a walk-in, or tap **For follow up** / **Mark as first visit** on Daily Roster or Live Queue. Run `php artisan patients:backfill` once per environment to link legacy bookings (use `--dry-run` first).
- **Data/systems touched:** `patients`, `patients.seen_before_software`, `bookings.patient_id`.
- **Success:** Each person has one record; family members on one phone can each book the same day; staff can merge mistaken duplicates; a paper-file patient is not labelled a first visit.
