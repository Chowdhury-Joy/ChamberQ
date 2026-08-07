# Decisions Log

## 2026-07-27

<decision>
 <category>Business_Logic</category>
 <context>Bangladesh chamber doctors collect fees at the desk; online gateways added friction, support load, and failed payments without improving show-up rate for v1.</context>
 <action>Ship pay-at-chamber only: patients book a serial online with no payment gateway (bKash/Nagad/SSLCommerz stack removed). No patient login accounts — status via ticket UUID link or portal phone lookup.</action>
 <reason>Matches real chamber workflow and keeps the prototype focused on serial + queue, not payments compliance.</reason>
</decision>

<decision>
 <category>Business_Logic</category>
 <context>Need a clear sellable split between one-doctor chambers and larger multi-doctor / lab practices.</context>
 <action>Two plan tiers: `solo` (one doctor, one chamber; `lab_tests` / `multiple_chambers` / `multiple_doctors` default off) and `clinic` (all three default on). Per-tenant `feature_flags` JSON can override defaults. Marketing: Solo ৳5,000 setup / ৳2,000 mo; Clinic ৳25,000 / ৳7,500 mo; WhatsApp sales CTAs (no self-signup).</action>
 <reason>Simple pricing story for sales; feature gates keep solo UI uncluttered while clinic unlocks labs and multi-entity management.</reason>
</decision>

<decision>
 <category>Business_Logic</category>
 <context>SMS confirmations cost money per message; including “free SMS” in the plan created unpredictable COGS.</context>
 <action>Prepaid SMS wallet on the tenant (`sms_balance`). Super Admin tops up credits; each successful confirmation debits 1 credit. Empty wallet skips SMS but the booking still succeeds. No free monthly SMS allowance.</action>
 <reason>Cost is controllable and billable as credit packs; booking never fails because SMS failed.</reason>
</decision>

<decision>
 <category>UI/UX</category>
 <context>Solo chamber staff need clear jobs without a hospital-grade permission maze.</context>
 <action>Tenant roles: `admin` (full), `doctor` (ops + queue), `staff` (content text/image + queue). Page builder structure is admin-owned; staff edits existing text/images. Waiting-room screen shows full patient name.</action>
 <reason>Mirrors real small-chamber staffing (owner, doctor, front-desk) and keeps content changes safe for non-technical staff.</reason>
</decision>

<decision>
 <category>CRO</category>
 <context>Central marketing must convert solo doctors without a self-serve signup that creates unpaid junk tenants.</context>
 <action>Central `/` landing with Solo as featured plan and WhatsApp (`wa.me`) as the only primary CTA into sales/onboarding. Tenant creation is Super Admin / done-for-you.</action>
 <reason>High-touch onboarding fits Bangladesh chamber buyers and protects demo quality.</reason>
</decision>

## 2026-07-28

<decision>
 <category>UI/UX</category>
 <context>Live Queue Control showed the heading "Currently serving" for any current booking, including patients who were only called and still waiting to arrive. Staff found this confusing because serving has not started yet.</context>
 <action>Make the heading status-aware: "Currently calling" when status is `called`, "Currently serving" only when status is `in_chamber` (after Patient arrived), and "No active call" when there is no current booking.</action>
 <reason>Matches the real workflow — a patient is only being served after staff confirm they arrived. The badge already said "Called — Waiting for Patient"; the heading now agrees with that state.</reason>
</decision>

<decision>
 <category>UI/UX</category>
 <context>Operational Reports rendered as a flat, unreadable list of labels and numbers. Two causes: the summary cards and the status row repeated the same counts, and the view relied on Tailwind utility classes that do not exist in this panel's precompiled Filament stylesheet, so every grid and card collapsed into plain text.</context>
 <action>Rebuilt `operational-reports.blade.php` around four KPI cards (Total bookings, Completed, Still in queue, Needs attention), a single non-repeating status chip row, and titled Filament sections for the day table and week/month breakdowns. Layout is written as scoped `ops-` prefixed CSS in the view, built on Filament's own CSS variables (`--gray-*`, `--success-*`, `--danger-*`, `--info-*`, `--primary-*`) with `.dark` overrides. Added derived helpers `getQueueCount()`, `getProblemCount()`, `getCompletionRate()`, and `getStatusMeta()` on the page class, plus a per-request `$totalsCache` so the view can read totals repeatedly without re-querying.</action>
 <reason>Staff need to judge a day at a glance: what happened, what is still moving, and what went wrong. Grouping the seven raw statuses into three meaningful outcomes does that, while the chip row keeps the detail available. Scoped CSS is used because this panel has no `viteTheme()`, so arbitrary Tailwind classes are silently dropped — Filament's CSS variables keep the page on-theme in both light and dark mode without adding a build step.</reason>
</decision>

<decision>
 <category>Code</category>
 <context>The Operational Reports tests only exercised `OperationalReportService` and access control, so a broken Blade template on the page would have shipped undetected — which is how the unstyled layout survived.</context>
 <action>Added three Livewire render tests to `OperationalReportsTest`: day view headline numbers, week/month breakdown tables, and the zero-bookings empty state.</action>
 <reason>Aggregation correctness and page rendering are separate failure modes. Rendering the Livewire component in tests catches Blade and helper errors on every run.</reason>
</decision>

## 2026-07-31

<decision>
 <category>Code</category>
 <context>Project memory files required by the Read/Write Auto-Handoff protocol were missing or incomplete (`architecture.md`, `sitemap.md`, `architecture_history.md`), so agents could drift from real routes, plan gates, and journeys.</context>
 <action>Create and maintain living `architecture.md` + `sitemap.md`, append-only `architecture_history.md`, and keep `decisions.md` / `bug_history.md` current with product reality in this repo (`/Users/chowdhuryjoy/SolDoc`, GitHub `Doctor-Gemini`, branch `Solo-Doc-V1`).</action>
 <reason>Prevents duplicate features, broken CRO paths, and silent overrides of plan-tier rules on future work (e.g. multi-chamber / lab expansions).</reason>
</decision>

<decision>
 <category>Business_Logic</category>
 <context>Online pre-payment (bKash/Nagad/gateway deposits before the visit) keeps getting re-suggested during planning, even though v1 already chose pay-at-chamber only. That distracts from serial, queue, and chamber ops work.</context>
 <action>Pre-payment / online patient payment is later-stage only. Do not suggest, plan, design, or build it unless the user explicitly asks about pre-payment or online payments. Until then, keep pay-at-chamber as the only payment model.</action>
 <reason>Owner will reopen payments when ready; until then, unsolicited payment ideas waste focus and risk reintroducing removed gateway complexity.</reason>
</decision>

## 2026-07-31 (path tenancy)

<decision>
 <category>Code</category>
 <context>Platform URLs should be doctorgemini.com/drkarim (path slug), not drkarim.doctorgemini.com (subdomain). Doctors with their own domain still get root paths on that domain.</context>
 <action>Hybrid tenancy on central `CENTRAL_DOMAINS`: stancl `InitializeTenancyByPath` at `/{tenant}/…` (tenant id = URL slug). Optional custom domains still use `InitializeTenancyByDomain` at root paths. Central path routes registered before domain-less catch-alls. Filament: `TenantAdminPathPanelProvider` at `/{tenant}/admin` on central; existing `TenantAdminPanelProvider` at `/admin` on custom domains. Helpers `tenant_web_url()` / `tenant_web_route()` (not stancl's `tenant_route()`). PWA manifest/scope and service worker respect path prefix.</action>
 <reason>One SSL/DNS setup on the platform domain; slug matches Super Admin tenant id; custom domain remains a premium root-URL upgrade without a second app install.</reason>
</decision>

## 2026-07-31 (solo multi-chamber)

<decision>
 <category>Business_Logic</category>
 <context>Solo doctors in Bangladesh often sit at 2–5 different chambers or hospitals on different days. The original Solo tier locked them to one chamber, which blocked a common real workflow without upgrading to Clinic.</context>
 <action>Solo plan now includes `multiple_chambers` by default, capped at 5 locations via `Tenant::SOLO_MAX_CHAMBERS` and `ChamberPolicy`. Clinic stays unlimited chambers. Solo remains one doctor (`multiple_doctors` off) and no labs (`lab_tests` off). Super Admin can still override `multiple_chambers = false` on a tenant to lock one location. Marketing Solo copy updated to “up to 5 locations.”</action>
 <reason>Matches how independent specialists actually practice; keeps Clinic differentiated by multi-doctor + labs + unlimited scale, not by basic multi-location booking.</reason>
</decision>

## 2026-07-31 (adaptive waiting-time ETA)

<decision>
 <category>Business_Logic</category>
 <context>Ticket “come around” times are a static schedule guess and can lie when the doctor runs fast or slow. A multi-layer auto-switch (schedule → live, plus average styles) is more flexible than Solo chambers need at first.</context>
 <action>v1: tenant/staff picks **one** ETA model and sticks with it for that chamber/day. Candidate models: (1) Schedule guess — session length ÷ seats, plus delay minutes; (2) Live simple average — average of today’s finished consult times; (3) Live steady pace — drop longest + shortest, then average. Patient ticket/screen always shows one time. No auto-switching between models in v1. Delay minutes stay a separate staff input. Auto-switch / combined layers can come later if needed.</action>
 <reason>One choice is easier to explain to doctors (“pick how waiting times are calculated”) and avoids surprising patients when the clock mode changes mid-session.</reason>
</decision>

## 2026-07-31 (marketer commissions)

<decision>
 <category>Business_Logic</category>
 <context>Sales partners (marketers) refer doctors via links and deserve transparent commissions on setup and recurring monthly fees, while billing stays manual (bKash/bank) with no payment gateway.</context>
 <action>Marketer role + `/partner` Filament panel. Commission base = amount doctor actually paid after discount (e.g. 20% setup / 10% monthly forever). Ledger: auto-create monthly commission rows; Super Admin confirms doctor paid → status `owed`; Super Admin marks marketer payout `paid`. SMS credit packs are not commissionable in v1. One marketer per tenant; paused marketers stop new attachments but existing owed stays payable.</action>
 <reason>Matches Bangladesh high-touch WhatsApp sales while giving partners a self-serve dashboard and giving Super Admin owed-vs-paid visibility without automating payouts.</reason>
</decision>

<decision>
 <category>CRO</category>
 <context>Referral links and discount codes must survive the WhatsApp handoff so Super Admin can attach the right partner when creating a tenant.</context>
 <action>Central marketing captures `?ref=` and `?code=` into session via `CaptureReferralParams` middleware; WhatsApp prefilled messages append `Ref:` / `Code:` when present. Super Admin tenant create prefills marketer/discount from session with manual override.</action>
 <reason>Partner gets credit even when the doctor skips self-signup and chats sales directly — the context rides in the WhatsApp message and session.</reason>
</decision>

<decision>
 <category>Code</category>
 <context>Commission math, monthly row generation, and doctor-payment confirmation must stay consistent and idempotent across Super Admin actions and cron.</context>
 <action>`DiscountCalculator`, `PlanPricingService`, `CommissionService`, Artisan `commissions:generate-monthly` (scheduled 7th of month). Tables: `marketers`, `discount_codes`, `billing_payments`, `commissions`; tenant columns for marketer, discount snapshot, list/due amounts, `setup_paid_at`.</action>
 <reason>Single service layer prevents duplicate monthly rows and ensures commission is always calculated on post-discount amounts actually paid.</reason>
</decision>

<decision>
 <category>UI/UX</category>
 <context>Card sections (KPI stats, pricing, services, doctor grids, etc.) used inconsistent column counts — some 4-up, some auto-fit, some 3-up only — which made layouts feel uneven across marketing, patient sites, and admin panels.</context>
 <action>Standardize all card collections on shared `CardGrid` / `.card-grid` + `data-card-count`: mobile 1 col, tablet 2 cols, desktop **2 cols when count is 2 or 4**, otherwise **3 cols**. Filament stat widgets use `UsesCardGridColumns` trait; Blade sections use `<x-card-grid>`.</action>
 <reason>2×2 for even fours (pricing, KPI quartets) reads balanced; 3×3 for 3/5/6 items avoids a orphaned last row. One rule everywhere reduces design drift.</reason>
</decision>

## 2026-07-31 (audit remediation)

<decision>
 <category>Business_Logic</category>
 <context>Post-audit review found commission, referral, roster, and booking edge cases that broke partner payouts, queue sync, and phone lookup.</context>
 <action>Batch fix: preserve paid commissions on re-confirm; lowercase marketer codes + case-insensitive legacy lookup; allow direct-sale payment confirmation without marketer; re-price tenants on plan/discount edit; route Daily Roster actions through `LiveSessionService`; normalize phones and block duplicate same-day/session bookings in `BookingService`; align `markAbsent` with `endSession` on `no_show`; period-filter finance owed; validate tenant slugs as `[a-z0-9-]`; escape PWA SVG output.</action>
 <reason>Each fix closes a real ops failure mode (wrong payout, broken ref link, split queue state, portal mismatch) without changing the pay-at-chamber product model.</reason>
</decision>

## 2026-07-31 (audit residuals)

<decision>
 <category>Code</category>
 <context>Five residuals after the main audit pass: duplicate end-session method, broken LiveSession bookings eager load, paid-commission amount rewrite, SMS path URLs on wrong host, and a misleading session fallback in path URL middleware.</context>
 <action>Remove dead `LiveQueueControl::endSession()`; introduce `HasManyByScheduleAndDate` for date-safe eager load; no-op `markCommissionOwed` entirely when already paid; build path SMS ticket URLs from `config('app.url')`; drop `filament_path_tenant` session read/write from `SetPathTenantUrlDefaults`.</action>
 <reason>Closes ledger drift, wrong patient SMS hosts, and latent cross-tenant URL bleed without changing product UX.</reason>
</decision>

## 2026-08-01T22:55:29+0600

<decision>
 <category>UI/UX</category>
 <context>Figma “Conditions I Treat” (`Summit-Dental` node 26385:314) shows three equal treatment cards in one horizontal row; the live solo homepage had them stacked because `.card-grid` CSS was missing on CDN Tailwind tenant pages, and the 3-col breakpoint (1200px) was later than Tailwind `lg` / typical laptop widths.</context>
 <action>Link `css/card-grid.css` on tenant webpages; keep feature pills as a vertical list inside each card; move “Including:” above the grey feature container; set card-grid desktop breakpoint to 1024px so counts other than 2/4 go 3-up on laptop.</action>
 <reason>Matches the Figma row of treatment cards without changing the shared 2-vs-3 count rule; inner service pills stay a checklist, not a nested grid.</reason>
</decision>

## 2026-08-01T23:08:41+0600

<decision>
 <category>UI/UX</category>
 <context>Solo homepage section H2s looked uneven — Conditions/FAQ/Videos at ~44px, About at ~56px, Testimonials at ~38px — because each Blade file hard-coded its own Tailwind size instead of the Figma Heading/H2 token (64px).</context>
 <action>Add shared `.solo-h2` in `tenant/solo/webpage.blade.php` (mobile 2.35rem → tablet 3rem → desktop 4rem / 64px, leading 0.85) and use it on every solo section title (conditions, about, videos, FAQ, testimonials).</action>
 <reason>One type ramp keeps the page feeling designed as a system; matches Figma H2 and stops section-by-section drift.</reason>
</decision>

## 2026-08-05T05:38:06+0600

<decision>
 <category>UI/UX</category>
 <context>Waiting-room TVs only played a chime when staff called a serial; patients further from the screen often missed who was next, and chambers asked for a spoken “calling number…” like a bank token system.</context>
 <action>Add tenant settings `call_announce_mode` (chime / voice / chime_and_voice, default chime_and_voice) and `call_announce_locale` (en / bn). Outdoor screen uses the browser’s SpeechSynthesis to say “Calling number N” or “কল নম্বর N” after (or instead of) the existing chime; Branding → Live Queue Settings controls the choice. No cloud TTS and no pre-recorded number packs in this pass.</action>
 <reason>Browser voice is free, works offline after unlock, and matches chamber hardware (TV/tablet browser) without new SMS/audio hosting cost. Doctors who dislike TTS can switch back to chime-only.</reason>
</decision>

## 2026-08-05T05:44:58+0600

<decision>
 <category>UI/UX</category>
 <context>Applying Figma token sizes literally (H1 88px, H2 64px, line-height 0.85) clipped solo homepage titles and made layouts feel broken.</context>
 <action>Keep shared `.solo-*` classes but enforce readable line-heights (≥ 1.05), slightly smaller desktop H1/H2, stack hero credentials, full-width testimonials heading + card-grid, `.solo-question` for FAQ (no uppercase), opaque sticky header (no backdrop-blur), and drop About’s forced 85vh stretch.</action>
 <reason>Figma comps assume artboard crop; live multi-line chamber copy needs breathing room or letters get cut off and sections collide.</reason>
</decision>

## 2026-08-05T05:56:46+0600

<decision>
 <category>UI/UX</category>
 <context>Staff testing Call from Live Queue still heard the same ghostly voice; first WAV pack used Samantha (same hollow tone as browser speech).</context>
 <action>Regenerate clips with Karen @ faster rate; play that WAV inside Live Queue Control on Call/Start; remove SpeechSynthesis fallback from the outdoor screen entirely.</action>
 <reason>Admin and TV must share one clear recording; falling back to browser speech reintroduces the ghost voice.</reason>
</decision>

## 2026-08-05T06:17:02+0600

<decision>
 <category>UI/UX</category>
 <context>After booking, patients often need a paper or file copy of the serial for reception or family — especially older visitors who don’t keep the phone page open.</context>
 <action>On the ticket confirmation page, add Print and Save as PDF buttons that open the browser print dialog. Print stylesheet hides nav, share actions, and live “now serving” so the paper/PDF shows serial, date, session, doctor, location, and the ticket link. No server-side PDF generator in v1.</action>
 <reason>Every phone already knows how to print or “Save as PDF”; avoids a new PDF package and works the same on path and custom-domain tenants.</reason>
</decision>

## 2026-08-05T06:21:27+0600

<decision>
 <category>UI/UX</category>
 <context>After the waiting-room voice work, a homepage “layout fix” (shared `.solo-*` type ramp, looser line-heights, restacked hero/testimonials/FAQ/header) changed how the patient site looked; the owner wanted the previous Figma-matched homepage back.</context>
 <action>Restore `tenant/solo/webpage.blade.php` and solo section blades to the version before that layout pass. Keep `tenant_safe_href()` on Book CTAs and the `card-grid.css` link so path-tenant booking and Conditions grid still work.</action>
 <reason>Owner preferred the earlier visual; functional booking/grid fixes must not be rolled back with the look-and-feel revert.</reason>
</decision>

## 2026-08-05T06:28:21+0600

<decision>
 <category>UI/UX</category>
 <context>Patient homepage look and Book Appointment CTAs were repeatedly tweaked during other work (voice, layout “fixes”), which risked undoing a settled Figma-matched homepage the owner approved.</context>
 <action>Freeze the solo patient homepage: no UI/layout/typography/section or Book Appointment button changes unless the owner explicitly says “update patient homepage” or “change patient homepage”. Book CTAs stay on `tenant_safe_href(..., '/book')`. Enforced via `.cursor/rules/patient-homepage-lock.mdc` + SolDoc `CLAUDE.md` project rule.</action>
 <reason>Stops drive-by homepage edits; unlock phrase is explicit so agents cannot treat general “improve UI” requests as permission to restyle home.</reason>
</decision>

## 2026-08-05T06:45:37+0600

<decision>
 <category>UI/UX</category>
 <context>Owner wanted the hero doctor name larger on phones only; tablet/desktop sizes already felt right.</context>
 <action>Bump solo hero H1 mobile size from 2.35rem to 2.85rem; leave `sm:text-5xl` and `lg:text-[5.5rem]` unchanged. Explicit homepage update scoped to mobile.</action>
 <reason>Improves name presence on small screens without reopening the locked desktop homepage look.</reason>
</decision>

## 2026-08-05T06:52:15+0600

<decision>
 <category>UI/UX</category>
 <context>Hero doctor name felt cramped as one long line on phones after the mobile size bump.</context>
 <action>Store optional line break in hero headline (demo: “Dr. Mahfuzur\nRahman”); render with `whitespace-pre-line` on mobile and `sm:whitespace-normal` so tablet/desktop stay one line. Hero headline field in Web Pages is a 2-row textarea.</action>
 <reason>Two-line name reads clearer on narrow screens without changing the desktop hero layout.</reason>
</decision>

## 2026-08-05T12:32:19+0600

<decision>
 <category>UI/UX</category>
 <context>Need to verify solo homepage mobile improvements without editing the locked live patient homepage Blade templates.</context>
 <action>Ship a standalone HTML/CSS mock at `public/previews/solo-homepage-v2.html` (hamburger menu, 44px taps, wider gutters, calmer type, shorter snap videos, conditions “Show more”) for owner review before any live Blade change.</action>
 <reason>Preview-first respects the patient homepage lock and lets UX be approved before production templates change.</reason>
</decision>

## 2026-08-05T13:18:42+0600

<decision>
 <category>CRO</category>
 <context>Bangladeshi patients book almost entirely on phones. The wizard showed only progress dots (no sense of how long the flow is), and on long steps the Continue button fell below the fold, which is where hesitation turns into drop-off.</context>
 <action>Booking wizard mobile pass, applied in the shared partial so both solo and clinic shells get it: (1) a plain-language `Step n of N — <title>` label under the dots; (2) `.btn-group` becomes `position: sticky; bottom: 0` under 640px with `env(safe-area-inset-bottom)` padding, Back at natural width and the primary button taking the rest; (3) 48px minimum height on `.btn` and `.selection-card`, plus a visible `.selected` state on type cards and checked lab tests; (4) phone field gets `inputmode="numeric"`, `autocomplete="tel"`, `017XXXXXXXX` placeholder, a "same number you will show at reception" hint, and live stripping of spaces/dashes with the caret preserved.</action>
 <reason>These are the four cheapest friction removals on the highest-value journey; sticky (not fixed) keeps the bar inline on short steps so it never covers content, and stripping separators stops a correct BD number from being rejected for formatting.</reason>
</decision>

<decision>
 <category>Business_Logic</category>
 <context>The Chambers admin form asked staff for latitude and longitude, while the page builder's location section already asked for a Google Maps link. Chamber staff have a share link from the Maps app; they do not look up coordinates.</context>
 <action>Replaced `chambers.latitude` / `chambers.longitude` with a single `map_url` column holding a pasted Google Maps link. Migration backfills existing coordinate pairs into `https://www.google.com/maps?q=lat,lng` before dropping the columns (reversible). `Chamber::isGoogleMapsUrl()` allowlists Google hosts only (`maps.app.goo.gl`, `goo.gl`, `maps.google.com`, `google.<tld>` with a `/maps` path) and backs both the Filament validation rule and `googleMapsUrl()`. Empty link falls back to a Maps search on the chamber address.</action>
 <reason>Matches how staff actually get a location and makes the two admin surfaces consistent. The host allowlist is required because the link is re-published to patients in the ticket and the WhatsApp share text, so an arbitrary pasted URL would be a redirect vector.</reason>
</decision>

## 2026-08-05T19:39:43+0600

<decision>
 <category>UI/UX</category>
 <context>The ticket page is the screen patients reopen most on a phone, and they scroll down it constantly — to the map, the "before you come" notes, the share buttons. Once they scroll, the serial and the number being called are both off screen, so they have to scroll back up to answer the only question they actually have: "is it my turn yet?"</context>
 <action>Added a fixed serial strip (`#serialStrip` in `tenant.partials.ticket-body`) that fades in once the big serial scrolls past, showing "Your serial N" on the left and "Now serving M" on the right, and turning green while the booking is `called`. Each shell positions it at its own header offset — `top: 0` on `tenant.ticket` (no navbar, only a floating locale chip, which is raised to z-40 with matching right padding on the strip) and `top: 68px` / `95px ≥640px` on `tenant.solo.ticket`. The shared script reads the strip's own `getBoundingClientRect().top` rather than repeating those breakpoints, and toggles visibility from a rAF-throttled passive scroll listener. Strip is `no-print` and `aria-hidden` so it does not duplicate the existing `aria-live` queue region.</action>
 <reason>Answers the waiting patient's only question without a scroll, at the cost of one fixed element. Reading the offset off the element keeps the two shells' different header heights in CSS where they belong. A scroll listener rather than IntersectionObserver because the strip's trigger point depends on that live offset, and the rAF throttle keeps it cheap on a page that is already polling every 5s.</reason>
</decision>

## 2026-08-05T19:59:41+0600 (admin panel audit remediation)

<decision>
 <category>Business_Logic</category>
 <context>A full read of `app/Filament/**` found five places where the admin UI quietly disagreed with the service or policy behind it: a duplicate slot-block cancellation pass that also cancelled completed visits and rendered patient names as HTML, a `wasChanged()` read taken after a second save that dropped marketer setup commissions, and bulk deletes that skipped `ChamberPolicy` / `DoctorPolicy` because Filament checks `deleteAny()`.</context>
 <action>Delete the duplicate cancellation from `CreateSlotBlock::afterCreate()` — it now only reports the count the `SlotBlock::created` hook produced, and points staff at the escaped "Notify patients" modal. Capture all `wasChanged()` answers before the re-pricing save in `EditTenant::afterSave()`. Add `deleteAny()` to all four tenant policies: `false` for Chamber and Doctor (count-based rules cannot survive a bulk selection) with `DeleteBulkAction` removed from those two tables, and the same gate as `viewAny()` for LabTest and LabCollectionSlot.</action>
 <reason>One write path per behaviour is the rule that already governs bookings (`BookingService`) and queue state (`LiveSessionService`); slot blocking had drifted from it. Denying bulk delete rather than adding per-record authorization is deliberate: a bulk action is authorized once against a count taken before any row is removed, so "keep at least one chamber" can never hold there — deleting one at a time from Edit already enforces it correctly.</reason>
</decision>

<decision>
 <category>Code</category>
 <context>Duplicate marketer login emails were accepted (central accounts have `tenant_id = null`, and SQL treats NULLs as distinct in the `(tenant_id, email)` index), while duplicate tenant staff emails and duplicate tenant slugs surfaced as 500s. A slug matching a reserved path prefix produced a tenant whose site could never be reached.</context>
 <action>Scope each form's `unique()` rule to match its index: tenant staff email `where('tenant_id', tenant('id'))`, marketer login email `whereNull('tenant_id')`, tenant slug `unique(ignoreRecord: true)` plus `notIn(config('tenancy.reserved_path_prefixes'))`.</action>
 <reason>The validation rule has to mirror the index or it is either useless (missing a real collision) or wrong (rejecting an address another tenant legitimately uses). Central accounts need their own rule because the database cannot express that constraint.</reason>
</decision>

## 2026-08-05T20:19:38+0600

<decision>
 <category>UI/UX</category>
 <context>The "This page has expired" alert had been reported five times and treated as a tenancy-middleware bug four of those times. Measurement showed the stack is now correct (a real Livewire commit from a fresh login page returns 200); what is left is the ordinary stale-CSRF-token case, which no middleware change can fix — three panels share one session cookie on one host, and Filament rotates the token via `session()->regenerate()` on every login.</context>
 <action>Register a global `panels::body.end` render hook in `AppServiceProvider` that, **for guests only**, intercepts Livewire's 419 and reloads the page instead of showing the browser confirm dialog. Signed-in pages keep Livewire's default prompt. Raise local `SESSION_LIFETIME` from 120 to 1440 minutes.</action>
 <reason>A guest login screen has no state worth preserving, so a silent reload is strictly better than a dialog that confuses the operator. Signed-in pages are excluded deliberately: auto-reloading would discard a half-finished page-builder edit or walk-in form without asking. The lifetime bump removes the most common trigger during a development day; it is a local convenience, not a security posture change for production.</reason>
</decision>

## 2026-08-05T21:01:25+0600

<decision>
 <category>Business_Logic</category>
 <context>The owner was being signed out repeatedly while working and asked for session expiry to be removed entirely. Note this supersedes the "local convenience, not a production posture change" framing of the 20:19 entry above: the change is now committed config that applies to every environment, at the owner's explicit instruction after the timeout concern was raised.</context>
 <action>`SESSION_LIFETIME` = 525,600 minutes (one year) in `.env`, `.env.example`, **and** as the `config/session.php` default so a missing env var cannot reintroduce the two-hour timeout. `SESSION_EXPIRE_ON_CLOSE=false`. `AUTH_PASSWORD_TIMEOUT` = 31,536,000 seconds so the password-confirmation window never prompts either.</action>
 <reason>Owner's explicit call. One year rather than a larger number because browsers cap cookie lifetime near 400 days, so anything beyond that is silently truncated and would be a lie in the config. The security trade-off is real and was flagged: an unattended chamber machine stays signed in indefinitely, which matters more once several staff share a device — revisit before multi-staff production rollout. Recorded here rather than left implicit so the next agent does not "fix" it back to 120.</reason>
</decision>

<decision>
 <category>Code</category>
 <context>Measurement during this task disproved idle expiry as the cause of the owner's ~5-minute sign-outs: a guest session held the same CSRF token across probes to t=300s, session files were growing rather than being reaped, password hashes were stable with `needsRehash` false, and no `logout()` call exists anywhere in `app/`.</context>
 <action>Removing expiry is therefore treated as a comfort change, not the fix. `App\Providers\AuthDebugProvider` (gated behind `AUTH_DEBUG`) stays installed to capture the real cause — it logs each logout with its stack frames, `has_session_cookie`, session id and URL.</action>
 <reason>This symptom has already been mis-diagnosed repeatedly in `bug_history.md`; committing to a cause without the authenticated-session evidence is what produced the earlier fix/revert ping-pong.</reason>
</decision>

## 2026-08-05T21:41:16+0600

<decision>
 <category>Code</category>
 <context>The no-expiry session settings are exactly the kind of value a later "harden the app" or "that looks like a bad default" pass reverts without asking — especially the non-standard `525600` default sitting in `config/session.php` where a framework default of `120` is expected.</context>
 <action>Lock them the same way the patient homepage is locked: `.cursor/rules/session-expiry-lock.mdc` plus a **Session expiry lock** section in `CLAUDE.md`, naming the locked keys and requiring the phrase **change session expiry** or **restore session timeout** to alter them. The rule states the accepted trade-off so it is not re-litigated, and explicitly says the separate "session replaced within seconds of login" defect must not be answered by touching these values.</action>
 <reason>Locks in this repo are how a settled owner decision survives contact with future agents. The homepage lock exists because drive-by edits kept undoing an approved design; this value has the same exposure, with the added trap that reverting it looks like a security improvement rather than a regression.</reason>
</decision>

## 2026-08-05T21:55:46+0600

<decision>
 <category>UI/UX</category>
 <context>Primary CTAs on the central sales site and Filament admin panels used coral/amber (orange), which the owner wanted replaced with a clearer blue + white treatment.</context>
 <action>Marketing `.mk-btn-primary` (including the featured Solo plan CTA) now uses `--mk-blue` / `--mk-blue-deep` (`#2563eb` / `#1d4ed8`) with white label text. Super Admin and Tenant Admin Filament panels switch `primary` from `Color::Amber` to `Color::Blue`. Coral accents on non-button marketing chrome (eyebrows, highlights) stay as secondary decoration.</action>
 <reason>Owner asked for all orange buttons to become blue with white text; blue matches the shared patient-site primary (`theme.css` / `Tenant::DEFAULT_THEME_COLOR`) so sales and admin feel like one product family.</reason>
</decision>

## 2026-08-05T22:15:09+0600

<decision>
 <category>UI/UX</category>
 <context>The clinic public site looked like a different product from the solo doctor site (Outfit, dark footer, short homepage), so patients and sales demos felt inconsistent across plan tiers.</context>
 <action>Restyle clinic homepage shell, facility-banner hero, and clinic sections to the solo visual language (DM Sans + Instrument Serif, pill CTAs, white sticky header/footer, `.solo-section` rhythm). Align clinic book/ticket/portal shells the same way. Enrich demo clinic homepage seed with conditions, doctors, about facility, why-us, multi-branch locations, testimonials, and FAQ. Add clinic `testimonials` section view and multi-branch `locations[]` support on `location_hours` (Filament + Blade). Solo homepage files stay locked/unchanged.</action>
 <reason>Owner asked for clinic to match solo style across the full patient journey while keeping a wide facility photo hero (not the solo portrait split) and clinic-specific content (multi-doctor, labs, branches).</reason>
</decision>

## 2026-08-05T22:22:54+0600

<decision>
 <category>UI/UX</category>
 <context>Patient sites had no default favicon, so browser tabs looked empty or showed a generic document icon.</context>
 <action>Added `public/icons/health-favicon.svg` (blue rounded square + white medical cross). `Tenant::faviconHref()` returns a custom `favicon_url` or that default. Clinic/book/ticket/portal shells (and solo book/ticket) always emit the icon link; demo seed sets `favicon_url` for solo and clinic tenants.</action>
 <reason>A simple health cross reads as medical care in the tab without requiring each doctor to upload an icon first.</reason>
</decision>

## 2026-08-05T23:15:17+0600

<decision>
 <category>UI/UX</category>
 <context>Owner wanted to try an Alvion Framer hospital look for clinic patient sites without risking the live clinic or locked solo homepage.</context>
 <action>Ship a standalone look-only mock at `public/previews/alvion-clinic-homepage.html` (dark full-bleed hero, lime accents, services/team/pricing/blog, BD-style demo copy and ৳ prices). Book/Contact/Discover buttons stay placeholders (`href="#"`). No live Blade, route, or booking changes.</action>
 <reason>Same preview-first path as solo-homepage-v2: review the storefront look in a showroom before remodeling the real clinic site.</reason>
</decision>

## 2026-08-05T23:22:36+0600

<decision>
 <category>UI/UX</category>
 <context>Owner rejected the Alvion preview and asked for a 1:1 copy of Clireo (https://clireo.framer.website) instead.</context>
 <action>Delete `public/previews/alvion-clinic-homepage.html`. Add `public/previews/clireo-homepage.html` as a look-only structural recreation of Clireo’s homepage (Golos Text, navy `#1B2978` / pink `#FA84E0`, hero booking card, about, treatment scroller, before/after, reviews, why/approach/doctors/stats, blog, FAQ, CTA, footer). Forms and CTAs are not wired to Doctor Gemini booking.</action>
 <reason>Owner-directed design swap; Clireo is the review reference now. Live solo/clinic templates stay untouched until explicitly approved.</reason>
</decision>

## 2026-08-05T23:33:27+0600

<decision>
 <category>UI/UX</category>
 <context>Owner liked the Clireo preview and asked to follow the Getwebfield spacing system and add animation where needed.</context>
 <action>Keep Clireo brand (navy/pink, Golos Text). Wire the preview to `public/css/getwebfield-spacing.css` (`.space-section` 48/96 stacked padding, `.layout-container` 1400px, `.stack-header`, `.grid-cards` / `.grid-hero` / `.grid-split` / `.grid-stats`, `.space-card`). Add scroll-in reveals, card/button hover lifts, approach-tab crossfade, and honor `prefers-reduced-motion`.</action>
 <reason>Spacing rhythm matches the machine-wide Getwebfield system while preserving the approved Clireo look; motion adds presence without changing conversion layout.</reason>
</decision>

## 2026-08-06T00:11:34+0600

<decision>
 <category>UI/UX</category>
 <context>Clireo preview was close but not 1:1 — missing Framer text effects, and the before/after block was not wanted.</context>
 <action>Remove the before/after section from `public/previews/clireo-homepage.html`. Keep all other Clireo sections (including Our Values). Match Framer text effects: hero word-by-word blur/rise (CSS keyframes), cyan underline on “health”, dual-label hover on nav links and CTAs, section heading word reveals on scroll. Still look-only; not wired to booking.</action>
 <reason>Owner asked for a full visual copy of Clireo’s text motion, with before/after explicitly dropped from this review mock.</reason>
</decision>

## 2026-08-06T00:23:40+0600

<decision>
 <category>UI/UX</category>
 <context>Clireo preview section headings looked smaller than the live Framer theme.</context>
 <action>Match Framer’s measured type scale on `public/previews/clireo-homepage.html`: H1/rating 90px, section H2 54px or 46px by section, about statement 40px, marquee 74px, card titles 26px; font-weight 400 and tight letter-spacing. Apply sizes to `.fx-heading` (the real markup), not the unused `.section h2` selector.</action>
 <reason>Owner asked for heading sizes to match Clireo 1:1; desktop measurements taken from https://clireo.framer.website.</reason>
</decision>

## 2026-08-06T10:25:20+0600

<decision>
 <category>UI/UX</category>
 <context>Owner shared a Clireo About-section screenshot and asked the preview to match that layout (not the photo-collage + stacked feature list we had).</context>
 <action>Rebuild About in `public/previews/clireo-homepage.html`: centered statement, white “More about us” pill with navy arrow tile, Trusted-by avatars inline, then three equal white icon cards; keep the scrolling specialty marquee under it with star separators.</action>
 <reason>Match the Framer About composition the owner approved by screenshot.</reason>
</decision>

## 2026-08-06T10:30:52+0600

<decision>
 <category>UI/UX</category>
 <context>About CTA and feature cards were visible at the same time as the heading word reveal, which felt early compared to Clireo.</context>
 <action>Keep `.about-cta-row` and `.about-feature` hidden until the About heading finishes its word-by-word reveal, then fade them in (CTA first, cards staggered). Honor reduced-motion by showing them immediately.</action>
 <reason>Owner asked for button, trust row, and cards to appear only after the About text is fully visible.</reason>
</decision>

## 2026-08-06T11:29:29+0600

<decision>
 <category>UI/UX</category>
 <context>Owner wanted the Clireo preview booking card without a message box, and a doctor picker next to phone.</context>
 <action>In `public/previews/clireo-homepage.html` hero form: remove Message textarea; put Phone and Select Doctor side-by-side (same row pattern as Date &amp; Time), with the four preview doctors as options.</action>
 <reason>Owner-directed form tweak for the look-only Clireo mock.</reason>
</decision>

## 2026-08-06T11:54:07+0600
<decision>
 <category>UI/UX</category>
 <context>Owner said the Treatments section in the Clireo preview looked less premium than the live template (light band + separate white cards under photos).</context>
 <action>Restyle `#treatments` in `public/previews/clireo-homepage.html` to match Clireo: navy `#1B2978` section band, white heading/eyebrow, outline “View all” CTA, full-bleed photo cards with bottom gradient mask, white in-image icons/titles/copy, light outline carousel arrows and muted footer line. Keep mobile ~1.2-card horizontal peek.</action>
 <reason>Matches the template’s dark treatment band and image-overlay cards instead of a flat light-section card strip.</reason>
</decision>

## 2026-08-06T11:55:21+0600
<decision>
 <category>UI/UX</category>
 <context>Owner asked the Treatments horizontal scroller to show ~1.8 cards in view (was ~1.2).</context>
 <action>In `public/previews/clireo-homepage.html`, set `.treat-scroller` `grid-auto-columns` to `calc((100% - gap) / 1.8)` below the desktop breakpoint.</action>
 <reason>Owner-directed peek width for the look-only Clireo treatments carousel.</reason>
</decision>

## 2026-08-06T12:06:33+0600
<decision>
 <category>UI/UX</category>
 <context>Owner wanted visible gaps between Treatments cards on desktop, and ~2.5 cards visible on tablet.</context>
 <action>In `public/previews/clireo-homepage.html` `.treat-scroller`: mobile keeps ~1.8 cards; tablet (≥640) uses ~2.5-card columns with `space-xl` gap; desktop (≥1200) keeps rem card widths with `space-xl` gap so cards are clearly separated.</action>
 <reason>Aligns peek counts and spacing with Getwebfield breakpoints and the owner’s Clireo preview feedback.</reason>
</decision>

## 2026-08-06T12:21:46+0600

<decision>
 <category>UI/UX</category>
 <context>Product will be renamed ChamberQ; needed a brand mark that reads as medical queue/chamber software, not a generic app icon.</context>
 <action>Create `public/icons/chamberq-logo.png` (300×300): teal rounded square with a white C+Q monogram and medical cross in the negative space. Asset only — not wired into panels or favicon yet.</action>
 <reason>Locks a rename-ready logo file owners can review before swapping SolDoc/Doctor Gemini chrome.</reason>
</decision>

## 2026-08-06T13:51:37+0600
<decision>
 <category>UI/UX</category>
 <context>Owner shared Clireo desktop treatments reference: 24px card gaps, 3 focused center cards, dimmed partial peeks on left/right, full-bleed scalable carousel.</context>
 <action>In `public/previews/clireo-homepage.html`: wrap scroller in `.treat-stage` (full-bleed desktop); desktop uses `--treat-gap: 24px` and `--treat-visible: 3.5` for viewport-scaled columns; `.treat-card.is-dim` navy overlay on clipped edge cards; JS toggles dim on scroll/resize, centers initial peek, arrow scroll = card width + gap.</action>
 <reason>Matches Clireo’s focused-carousel pattern in the look-only preview without touching locked solo homepage blades.</reason>
</decision>

## 2026-08-06T13:53:38+0600

<decision>
 <category>UI/UX</category>
 <context>Owner wanted the doctors block to follow treatments on the Clireo homepage preview — show who delivers care right after what they offer.</context>
 <action>In `public/previews/clireo-homepage.html`, move the “Meet The Doctors Behind Expert Care” section (including stats band) to sit immediately after `#treatments` and before `#values`; add `id="doctors"` on that section.</action>
 <reason>Treatments → doctors is a stronger trust narrative for the look-only preview; no change to locked solo patient homepage blades.</reason>
</decision>

## 2026-08-06T13:58:53+0600

<decision>
 <category>UI/UX</category>
 <context>Owner asked the Clireo preview treatments carousel to show four fully focused cards in the main viewport with one dimmed peek on each side, and to loop infinitely when scrolling or using arrows.</context>
 <action>In `public/previews/clireo-homepage.html`: desktop `--treat-visible` changes from `3.5` to `6` (4 focused + 2 side peeks); JS clones the five treatment cards prepend/append for seamless infinite scroll, jumps scroll position when entering clone zones, and dims first/last visible cards (plus any clipped cards) via `.is-dim`. Mobile/tablet peek counts unchanged.</action>
 <reason>Matches owner’s Clireo carousel reference without touching locked solo homepage blades.</reason>
</decision>

## 2026-08-06T14:05:00+0600

<decision>
 <category>UI/UX</category>
 <context>Owner shared Clireo desktop treatments reference showing three fully focused center cards with one dimmed peek on each side (not four focused).</context>
 <action>In `public/previews/clireo-homepage.html`: desktop `--treat-visible` changes from `6` to `5` (3 focused + 2 side peeks); keep `--treat-gap: 24px` and existing infinite-loop clone + `.is-dim` edge logic unchanged.</action>
 <reason>Matches the owner’s reference image: scalable viewport cards, 24px gaps, three bright center cards with non-focused side peeks.</reason>
</decision>

## 2026-08-06T14:15:00+0600

<decision>
 <category>UI/UX</category>
 <context>Owner wanted every Clireo preview section to scroll in with strict top-to-bottom order: eyebrow → heading → sub-description → cards → footer text — not independent per-element reveals.</context>
 <action>In `public/previews/clireo-homepage.html`: add `data-reveal-section` + numbered `data-reveal-step` markers on About, Treatments, Doctors, Values, Reviews, Why choose, Approach, Blog, FAQ, and Final CTA; replace global `.reveal` IntersectionObserver for section content with a section orchestrator (fade, word-reveal heading, staggered cards); fold About’s one-off `showAboutFollowers` into the same system; hero stays immediate; `prefers-reduced-motion` shows all instantly.</action>
 <reason>Consistent reading-order entrance animation across the look-only Clireo preview without touching locked solo homepage blades.</reason>
</decision>

## 2026-08-06T16:12:00+0600

<decision>
 <category>UI/UX</category>
 <context>Owner clarified section reveals should not use predefined step numbers — whatever content is visually first from the top should animate first, and whatever is last should animate last.</context>
 <action>In `public/previews/clireo-homepage.html`: replace numbered `data-reveal-step` with `data-reveal-block`; section orchestrator now sorts blocks by visual top position (then left) at reveal time and runs them in that order — no hardcoded sequence numbers.</action>
 <reason>Flexible top-to-bottom entrance that follows actual layout order as sections evolve, without maintaining step indices per section.</reason>
</decision>

## 2026-08-06T16:18:00+0600

<decision>
 <category>UI/UX</category>
 <context>Owner removed Our Approach from the Clireo preview and reported hero sub-copy, rating row, and booking card were visible on load before the headline finished animating.</context>
 <action>Delete the Approach section (HTML, CSS, tab JS) from `public/previews/clireo-homepage.html`. Hero joins `data-reveal-section`: backed row, lead, rating, and book form start hidden and reveal top-to-bottom after ~1.1s headline word animation; removed immediate hero `.is-in` forcing.</action>
 <reason>Cleaner preview scope and headline-first hero entrance without flashing secondary hero content on load.</reason>
</decision>

## 2026-08-06T16:48:00+0600

<decision>
 <category>UI/UX</category>
 <context>Owner asked to remove Our Values and Why Choose Clireo from the preview and align the reviews block with the Clireo template Treatment Results section and its scroll-in animation pattern.</context>
 <action>In `public/previews/clireo-homepage.html`: delete `#values` and `#why-choose` sections; rebuild `#reviews` as template Treatment Results (eyebrow, “Real Results From Before &amp; After Treatment” heading, review scroller, Google 4.8 row, book CTA) with centered header and top-to-bottom `data-reveal-block` sequence (heading word reveal → scroller fade → footer row fade).</action>
 <reason>Matches Clireo template section copy/structure without restoring the old before/after slider; keeps Framer-like section entrance on the look-only preview.</reason>
</decision>

## 2026-08-06T16:58:00+0600

<decision>
 <category>UI/UX</category>
 <context>Owner clarified they wanted Why Choose Clireo restored with the same top-to-bottom section reveal pattern as the Clireo template (not removed).</context>
 <action>Re-add `#why-choose` after `#reviews` in `public/previews/clireo-homepage.html`: eyebrow → heading word reveal → section lead → Book Consultation CTA → 4-card grid, each as `data-reveal-block` in visual order (grid fades as one block, not per-card stagger).</action>
 <reason>Template-like section entrance without cards animating out of reading order.</reason>
</decision>

## 2026-08-06T17:04:00+0600

<decision>
 <category>UI/UX</category>
 <context>Owner shared Clireo template screenshot for Why Choose: centered copy with navy floating cards on diagonal corners (not a 4-column grid).</context>
 <action>Rebuild `#why-choose` in `public/previews/clireo-homepage.html` as `.why-choose-stage` with centered core (eyebrow → heading word reveal → lead → `btn-about` CTA) and absolutely positioned `.why-float-card` slots (tl/br on tablet+, four corners on desktop); cards fade in as a group after core via `data-reveal-priority`; pink icons in white boxes on navy cards.</action>
 <reason>Matches Clireo template layout and scroll entrance pattern in the look-only preview.</reason>
</decision>

## 2026-08-06T17:09:00+0600

<decision>
 <category>UI/UX</category>
 <context>Owner asked to remove Why Choose Clireo and make the About section load/reveal faster.</context>
 <action>Delete `#why-choose` section and its CSS from `public/previews/clireo-homepage.html`. Speed up `#about`: faster word-reveal interval (24ms), shorter settle (320ms), 200ms step gaps, 0.4s fades; feature cards fade in as one block instead of stagger.</action>
 <reason>Leaner preview and snappier About entrance after hero.</reason>
</decision>

## 2026-08-06T17:13:00+0600

<decision>
 <category>UI/UX</category>
 <context>Owner asked to apply the same faster section reveal timing used on About to all other Clireo preview sections.</context>
 <action>Global fast reveal in `public/previews/clireo-homepage.html`: 0.4s fades, 0.45s heading words, 24ms word interval / 320ms settle, 200ms step gaps (hero stays 160ms); doctors + blog card grids fade as one block instead of stagger.</action>
 <reason>Consistent snappy scroll-in across the look-only preview.</reason>
</decision>

## 2026-08-06T17:14:00+0600

<decision>
 <category>Content</category>
 <context>Owner asked to change the Clireo preview contents based on https://www.facebook.com/cbphbd.</context>
 <action>Rebrand preview copy to Chattogram Best Physiotherapy Hospital (CBPH): hero/about/services/doctors/reviews/blog/FAQ/CTA/footer updated with physiotherapy rehab focus, Panchlaish address, phone numbers 01630-078675 &amp; 01882-373894, Facebook links, and verified team names (Dr. Antar Das, Batia Nahar Ahsan, Dr. Mohammad Golam Eazdani). Clireo template layout and motion unchanged.</action>
 <reason>Look-only preview now reflects the owner’s CBPH Facebook reference while keeping the approved Clireo shell.</reason>
</decision>

## 2026-08-06T17:22:00+0600

<decision>
 <category>Content</category>
 <context>Owner asked to replace Western placeholder photos with Asian imagery in the CBPH preview.</context>
 <action>Swap hero/CTA backgrounds, avatars, service cards, team portraits, reviews, blog, and FAQ images in `public/previews/clireo-homepage.html` to Unsplash/Pexels Asian &amp; South Asian physiotherapy and portrait photos. Decorative SVG icons left unchanged.</action>
 <reason>Preview visuals better match CBPH’s Chattogram audience; stock icons retained for UI consistency.</reason>
</decision>

## 2026-08-06T18:42:00+0600

<decision>
 <category>UI/UX</category>
 <context>Owner approved the CBPH Clireo preview (`public/previews/clireo-homepage.html`) after iterative review and said to use this design for clinic patient sites going forward.</context>
 <action>Establish `public/previews/clireo-homepage.html` as the canonical **clinic-tier homepage design reference** (Clireo layout + Getwebfield spacing + CBPH demo content). Future clinic Blade work (`tenant/webpage.blade.php`, `tenant/sections/*`) should follow this mock — not the interim solo-style clinic shell from 2026-08-05. Solo homepage blades stay locked. The static file remains the showroom until live templates are migrated; booking CTAs there are still not wired to Doctor Gemini.</action>
 <reason>Locks the approved clinic visual direction in project memory so agents and implementers do not revert to DM Sans / solo-style clinic chrome or re-litigate the Alvion/Clireo choice.</reason>
</decision>

## 2026-08-06T20:16:38+0600

<decision>
 <category>Business_Logic</category>
 <context>Patient Records Stage 1 (`patient-records-plan.md` Part 1): bookings were keyed only by phone, blocking families from booking two children the same day, and the queue had no row lock on `live_sessions`.</context>
 <action>Introduce tenant-scoped `patients` (person per household member, shared phone) with nullable `bookings.patient_id`. `BookingService` resolves/creates a patient on every new booking, blocks duplicate same **person** on same bookable + date (legacy null-`patient_id` rows still match phone + normalized name). Inline household picker on the booking wizard phone step and Daily Roster walk-in via `GET /api/patients/by-phone`. `patients:backfill` with `--dry-run` links historical bookings. Filament **Patients** list with join-records and move-visit actions. `LiveSessionService` queue mutations use `lockForUpdate()` on the live session row. SMS confirmations lead with `Name — serial N`.</action>
## 2026-08-06T20:26:30+0600

<decision>
 <category>Business_Logic</category>
 <context>Patient Records Stage 2 (`patient-records-plan.md` Part 2): doctors need a consult screen that auto-follows the queue; queue operation must be doctor-or-staff only (not account owner); solo practices must have a doctor login; one party runs the queue per practice.</context>
 <action>Filament **Consult Screen** auto-follows `live_sessions.current_booking_id` (name, age, sex, visit count, warnings, honest “no notes” states). `canManageQueue()` is doctor/staff only — admin (owner) removed. Per-tenant `queue_runner` (`staff` default | `doctor`) in Branding Settings toggles who gets Live Queue Control and call/complete actions; doctors use Consult Screen in staff-run mode. `TenantUserBootstrapService` + required doctor email on Super Admin tenant create ensures a doctor login exists. Super Admin Create Tenant form requires `initial_doctor_email`.</action>
 <reason>Matches how chambers actually run (staff call, doctor consults), prevents owner-only solo setups from being locked out of the queue, and keeps clinical view separate from operational queue controls.</reason>
</decision>

## 2026-08-06T20:31:09+0600

<decision>
 <category>Business_Logic</category>
 <context>Patient Records Stage 3 (`patient-records-plan.md` Part 4): diagnosis must be coded for future research counts; doctors need fast two-tap picks with their own frequent conditions floating to top; free text must remain allowed as uncoded.</context>
 <action>Global `conditions` table (code, name, JSON aliases, category) loaded from `data/condition-list-draft.csv` via `conditions:load`. `condition_usages` tracks per-tenant doctor frequency/recency. `ConditionService` searches name + aliases (min 3 chars), ranks by match + usage boost, `resolveSelection()` returns coded or uncoded payload. Doctor-only `GET /api/conditions/search` for the Stage 4 diagnosis picker.</action>
 <reason>Retrofitting codes onto free text later is prohibitively expensive; building the master list and picker now lets Stage 4 visit recording store structured diagnoses from day one while still accepting uncoded entries.</reason>
</decision>

## 2026-08-06T20:38:53+0600

<decision>
 <category>Business_Logic</category>
 <context>Patient Records Stage 4 (`patient-records-plan.md` Part 3): doctors need almost-free visit capture at Mark Completed; staff must not record or read clinical notes; patient-facing pages must never leak diagnoses or prescriptions.</context>
 <action>`visit_records` (one per booking when content exists): coded `condition_id` or `diagnosis_uncoded`, advice, tests advised, reports seen, follow-up date. Optional `prescriptions` + `prescription_items` with browser-print layout at doctor-auth `GET /prescriptions/{id}/print`. Doctors get an optional Filament modal on Mark Completed (Daily Roster, Live Queue Control, Consult Screen) with condition search + free text; staff complete without modal. `canViewVisitNotes` / `canRecordVisitNotes` are doctor-only. Empty submissions create no visit row so “N previous visits · no notes recorded” stays honest.</action>
 <reason>Notes must never block queue throughput; only doctors were in the consult; separating read/write permissions keeps staff on demographics while clinical data stays off tickets and portal.</reason>
</decision>

## 2026-08-06T20:44:49+0600

<decision>
 <category>Business_Logic</category>
 <context>Patient Records Stage 5 (`patient-records-plan.md` Part 5): Super Admin needs usage signals that predict churn before payment fails — quiet clients, onboarding stalls, SMS wallets empty, overdue accounts — without crossing the patient-records boundary.</context>
 <action>Super Admin **Client Health** page (`SellerOverview` + `SellerOverviewService`) at `/admin/seller-overview`: quiet clients (days since last live session, booking drop vs own 4-week baseline, schedule set but never started), go-live funnel for signups in the last 90 days, SMS warnings at balance ≤ 5, overdue payments list with days overdue. All aggregates are tenant-level counts — never patient names, diagnoses, prescriptions, or visit contents. Tenant-scoped models queried with `withoutGlobalScope(TenantScope::class)` on the central domain.</action>
 <reason>Payment tells you someone left last month; usage tells you three weeks earlier. A Sunday-morning call list needs tenant names and signals, not clinical data.</reason>
</decision>

## 2026-08-06T20:44:49+0600

<decision>
 <category>Code</category>
 <context>`patient-records-plan.md` Part 5 warns that a future “log in as this doctor” support button is the back door through the Super Admin clinical boundary; the signup agreement draft in Appendix B already promises staff cannot view patient records.</context>
 <action>Do **not** add impersonation or “view as tenant” without an explicit owner decision. If built later: doctor must opt in, session must expire, every use must be audited, and Appendix B / `decisions.md` wording must be updated first.</action>
 <reason>Decide the boundary now so a convenience support feature does not silently violate the counts-only central panel rule or the research/signup promise.</reason>
</decision>

## 2026-08-06T20:48:23+0600

<decision>
 <category>Business_Logic</category>
 <context>Patient Records Stage 6 (`patient-records-plan.md` Part 6): platform needs cross-practice disease-pattern statistics from coded diagnoses, but small filter slices can re-identify patients; doctors must have agreed to anonymous aggregates at signup.</context>
 <action>Super Admin **Research data** page (`ResearchData` + `ResearchDataService`) at `/admin/research`: aggregate `visit_records` with `condition_id` set only (uncoded excluded), across all tenants. Filters: date range, plan tier — no per-tenant or per-patient slicing. **K-anonymity:** `MIN_GROUP_SIZE` 10 — counts below 10 suppressed; UI warns to widen filters. Page copy states aggregate anonymous research only and references signup agreement Appendix B.</action>
 <reason>Standard cheap privacy protection built in from day one; coded list from Stage 3 is what makes counting possible without reading free text.</reason>
</decision>

## 2026-08-06T20:57:04+0600

<decision>
 <category>Business_Logic</category>
 <context>Patient Records Stage 4 deferred items (`patient-records-plan.md` Part 3): doctors need voice notes and paper prescription photos without typing; end-of-session catch-up for missed notes; transcript is convenience only.</context>
 <action>`visit_records` extended with `voice_path`, `photo_path`, `voice_transcript`. Voice stored on `public` disk at `visit-audio/{tenant_id}/` via browser MediaRecorder + `POST /api/visit-media/upload-voice`; photos at `visit-photos/{tenant_id}/` via Filament upload. Doctor-auth stream routes for playback/view; staff forbidden. Transcript is a manual optional field in the modal — **no speech-to-text integration** in this pass; it never sets coded diagnosis. Consult Screen shows catch-up banner during active sessions; end session warns doctors. No handwriting recognition on photos.</action>
 <reason>Recording is the primary capture for doctors who will not type; transcript and photo are layered on without replacing audio or risking misread drug names from OCR.</reason>
</decision>

## 2026-08-06T21:05:01+0600

<decision>
  <category>Business_Logic</category>
  <context>The queue runner rule ("one party per practice — staff-run or doctor-run") was enforced against the configured setting alone. `queue_runner` defaults to `staff` for every new tenant, so a solo doctor with no staff login had nobody able to call patients, and could not correct it because only an admin may change the setting. The seeded demo tenant carries one user of every role, so no existing test exposed it.</context>
  <action>Added `Tenant::effectiveQueueRunner()` — the configured runner checked against role presence in that practice. When the configured party has no user, controls fall back to the other eligible party. `User::canOperateQueueControls()` now resolves against the effective runner rather than the configured one. Role presence is memoised per request on the Tenant instance, since it is consulted on every panel page load.</action>
  <reason>Exclusivity existed to stop two parties calling patients at once, not to leave a chamber with nobody able to work the queue. The fallback only fires when the configured party is empty, so exactly one party is ever live and the earlier decision holds. Setting the column at tenant creation was rejected: staffing changes after signup (the owner adds or removes a staff login later), so a creation-time snapshot would drift back into the same dead end. The account owner is still excluded from queue controls in all cases — the fallback moves authority between doctor and staff only.</reason>
</decision>

## 2026-08-06T21:15:00+0600

<decision>
 <category>UI/UX</category>
 <context>Clireo clinic homepage preview had a hero “booking” form that did not submit to Doctor Gemini (`action="#"`, `onsubmit="return false;"`). Live clinic tenants already funnel patients through the shared `/book` wizard → `/bookings/{uuid}` ticket flow (`BookingController`, `tenant/sections/*` CTAs).</context>
 <action>Wire the clinic Clireo design reference (`public/previews/clireo-homepage.html`) to the real booking path: replace the fake hero form with a Clireo-styled **book CTA card** (helper copy + “Start booking” → `/book`); point all Book / booking-oriented Contact nav, doctor section, reviews, FAQ promo, final CTA, footer, and mobile drawer links to `/book` (relative). No embedded form may fake-submit — display-only Clireo chrome or redirect into the wizard. Live Blade migration should use `tenant_web_url('/book')` and `tenant_web_url('/book?doctor='.$id)` on every CTA when the homepage is rebuilt.</action>
 <reason>Patients must not fill a decorative form that never books; one wizard owns location, session, serial, and ticket logic. Preview documents the live pattern; full Clireo Blade shell remains a follow-up.</reason>
</decision>

## 2026-08-06T23:37:36+0600

<decision>
  <category>Code</category>
  <context>Stage 4 shipped visit voice notes and prescription photos on the `public` disk. That disk is symlinked into the web root, so every file had a permanent unauthenticated URL despite the doctor-only controller in front of it.</context>
  <action>Moved both to the `local` private disk (`storage/app/private`) with explicit `private` visibility. `absolutePublicPath()` became `absolutePath()`, documented as existing only to stream through `VisitMediaController`. Added `ClinicalMediaPrivacyTest`, which asserts the outcome — files are outside the web root and `/storage/{path}` returns 403 — rather than asserting a disk name.</action>
  <reason>UUID filenames are obscurity, not access control: a public URL leaks through server access logs, shared-computer browser history and forwarded links, cannot be revoked when a doctor or patient leaves, and cannot be audited if a leak is ever alleged. The confidentiality duty sits with the doctor, not the platform, so the exposure lands on the customer. Fixed pre-launch because no production files existed yet — the same change after go-live would be a data migration plus a disclosure to doctors. Tests assert the property rather than the implementation so a future disk change cannot silently reintroduce web-serving.</reason>
</decision>

## 2026-08-06T23:46:10+0600

<decision>
  <category>CRO</category>
  <context>The only client-facing decks (`docs/slides/SoloDoc-Client-Slides.pptx`, `SoloDoc-Marketing-Slides.pptx`) date from 2026-08-03: they carry the retired "SoloDoc" name, a stale Tk 12,000 Solo setup price (current is Tk 5,000), and predate patient records, the Consult Screen, prescriptions, vacation mode and the privacy model. They are also 12 bullet-only slides that lead with a feature list rather than the doctor's own problem.</context>
  <action>New canonical sales deck at `docs/slides/Doctor-Gemini-Client-Pitch.pptx` (17 slides, English), generated from a checked-in script `docs/slides/build-client-pitch.js` (pptxgenjs) so pricing and copy can be regenerated rather than hand-edited. Argument order is problem → what the queue costs the doctor → before/after → four-step walkthrough → feature proof → privacy → objections → pricing → close. Prices, SMS pack rates and the 2 hrs → 15 min claim are taken from `config/marketing.php`; palette from the Clireo navy and the solo blue. Speaker notes on 12 slides carry the demo choreography (lead with the outdoor screen announcement, book a live serial on the doctor's own phone) and the two things to volunteer unprompted: the internet dependency, and the anonymous cross-tenant research aggregation. The old SoloDoc decks are superseded, not deleted.</action>
  <reason>Doctors do not buy a feature list — waiting time is a patient cost, not a doctor cost, so the deck has to convert it into lost consult fees, idle chamber time and assistant payroll before any screen is shown. Generating from a script keeps the deck honest against `config/marketing.php` instead of drifting like the 2026-08-03 pricing did. Disclosing the research aggregation and the offline limitation in the deck itself is a trust play: a doctor who hears the limitation first believes the rest.</reason>
</decision>

## 2026-08-07T00:00:46+0600

<decision>
  <category>CRO</category>
  <context>The general client deck (`Doctor-Gemini-Client-Pitch.pptx`, logged 2026-08-06T23:46:10+0600) leads with reduced patient waiting time — a pitch that is dead on arrival for an already-busy, established doctor whose room is full regardless of queue tooling (see the objection-handling discussion in this session's transcript: "if the doctor is fully booked and patients will wait three hours regardless... the time argument is dead and pushing it makes you look green").</context>
  <action>Second deck at `docs/slides/Doctor-Gemini-Established-Practice-Pitch.pptx` (11 slides, English, generated from checked-in `docs/slides/build-established-practice-pitch.js`), for established/high-volume doctors and multi-doctor practices. Drops the wait-time before/after entirely. Argument order instead: paper/continuity problem at scale → why volume makes memory (not the waiting room) the risk → booking/queue still ship but are de-emphasized → continuity of the consult record → confidentiality (staff and associates scoped, doctor-only clinical data) as its own slide → growing into Clinic tier (associates, multiple chambers, labs) → professional web presence built around credentials → same "what doesn't change" and pricing pattern as the general deck, with Clinic tier visually featured instead of Solo. Typeface is Inter Tight per the request that started this deck (not on the project's safe/metric-compatible font list — see `pptx` skill's Typography section — so every text container in `build-established-practice-pitch.js` carries extra height slack over a conservative estimate, since this machine has no LibreOffice/soffice to visually confirm real-font fit).</context>
  <reason>A senior or high-volume doctor's queue is already full; selling shorter waits reads as not understanding their practice and undercuts credibility for the rest of the pitch. The real value at that scale is not losing history across hundreds of patients and years, keeping clinical data out of staff/associate reach as the practice's profile grows, and a web presence that matches an already-built reputation. Kept as a second deck rather than replacing the general one because the original wait-time framing is still the stronger opener for a doctor who is not yet full.</reason>
</decision>

## 2026-08-07T00:09:11+0600

<decision>
  <category>CRO</category>
  <context>The established-practice deck shipped 2026-08-07T00:00:46+0600 used phrases like "continuity across hundreds of patients," "scoped access," and "reputation rides on consistency" — vocabulary that reads as software-industry jargon, not how a doctor talks or thinks. Owner feedback: "contents should be easy to understand. you are complicating them."</context>
  <action>Rewrote every slide's copy in `docs/slides/build-established-practice-pitch.js` to short, plain sentences — no "continuity," "scoped," "leverage," or similar abstractions. Example: "Your reputation rides on consistency" became "One bad prescription hurts your name"; "Associates need a shared system" became "New doctors need your notes too." Slide structure, layout, and argument order are unchanged from the prior entry. Three titles that grew past one line after simplification were shortened further rather than given taller boxes, to avoid crowding the subtitle line beneath them (slides 3, 5, 6). Regenerated `docs/slides/Doctor-Gemini-Established-Practice-Pitch.pptx` from the edited script.</action>
  <reason>A sales deck's job is to be understood in one read by a doctor mid-conversation, not to sound sophisticated. Plain, concrete language (what the patient/doctor/staff sees and does) beats abstract nouns every time in a pitch document.</reason>
</decision>

## 2026-08-07T00:39:08+0600

<decision>
  <category>CRO</category>
  <context>Sales meetings need a single-page leave-behind that maps every product feature to the chamber problem it fixes, distinct from the two full slide decks — something a doctor can keep on the desk or have forwarded on WhatsApp after the meeting.</context>
  <action>New one-page PDF at `docs/slides/Doctor-Gemini-Problem-Feature-Solution.pdf`, generated from checked-in `docs/slides/build-problem-feature-solution.py` (reportlab). 15-row Problem / Our Feature / Solution table covering the full product surface (booking, live queue, outdoor screen, patient records, prescriptions, voice notes, staff/doctor access split, website, household picker, vacation mode, multi-doctor/Clinic tier, multi-chamber, lab tests, pay-at-chamber, white-glove setup), footer with Solo/Clinic pricing pulled from the same figures as the two decks. Same navy/blue/mint palette as the client decks for visual consistency across all three leave-behinds.</context>
  <reason>A doctor skimming after a meeting needs the whole feature set on one page, not 11-17 slides — this is a reference artifact, not a presentation. Kept as a separate generated script (not hand-edited) so a pricing change only needs updating in one place before rebuilding, same rationale as the two pptx decks.</reason>
</decision>

## 2026-08-07T00:47:39+0600

<decision>
  <category>Business_Logic</category>
  <context>Owner set the product name to ChamberQ (previously "Doctor Gemini" — itself a rename from an earlier "SoloDoc" naming that never fully propagated). Confirmed scope explicitly: rename everywhere, including the live app, not just sales material.</context>
  <action>Renamed "Doctor Gemini" → "ChamberQ" across: `config/marketing.php` product_name default; `.env.example` (`APP_NAME`, `MARKETING_PRODUCT_NAME`, and the `doctorgemini.com` domain example → `chamberq.com`); the four literal WhatsApp-message and meta-description strings in `resources/views/marketing/home.blade.php` (the page's own `{{ $product }}` binding already reads from config and needed no change); `tests/Feature/MarketingLandingPageTest.php` assertion; `README.md` title; `CHANGELOG.md` header line; `CLAUDE.md` project title; the `architecture.md` Overview line and `sitemap.md` `/` route purpose (both living docs, edited in place, `Last Updated` bumped); the visible label on `public/previews/clireo-homepage.html`. Also renamed and updated the four client/marketing guide docs (`docs/Doctor-Gemini-Client-Guide.md`, `-BN`, `docs/Doctor-Gemini-Marketing-Playbook.md`, `-BN` → `docs/ChamberQ-*`), which were still internally calling the product "SoloDoc" from before the Doctor Gemini rename ever reached them — replaced both the stale "SoloDoc" product references and the `doctorgemini.com` domain examples. Rebuilt and renamed the three sales artifacts from this session (`docs/slides/ChamberQ-Client-Pitch.pptx`, `ChamberQ-Established-Practice-Pitch.pptx`, `ChamberQ-Problem-Feature-Solution.pdf`) from their already-updated generator scripts. Historical entries in this file and in `architecture_history.md` that mention "Doctor Gemini" are left untouched — they describe what was true when written; only this new entry and the architecture_history.md line reflect the rename. The old superseded `SoloDoc-Client-Slides.pptx` / `SoloDoc-Marketing-Slides.pptx` decks were already flagged as stale (2026-08-06T23:46:10+0600) and are left as-is.</action>
  <reason>A product rename that only touches sales collateral but leaves the live marketing site, README, and tests saying the old name is worse than not renaming at all — it creates two names in circulation. Fixing the four guide docs' internal "SoloDoc" references in the same pass, even though the request was phrased around "Doctor Gemini," closes a gap the prior rename left open rather than leaving a third stale name sitting in client-facing material.</reason>
</decision>

## 2026-08-07T01:50:29+0600

<decision>
  <category>UI/UX</category>
  <context>A UX/UI review of Live Queue Control found 19 issues, and the owner asked for all of them. The page had three classes of problem: things that actively misled staff (the patient being announced sorted below cancelled bookings; a hardcoded light-mode red on "End Session"; a "Timeout" button whose label hid that it marks a real patient no-show; a "Call Next Patient (End)" button that stayed enabled with nobody to call; pause reason/ETA collected then never displayed; announcement audio failing silently into `console.log`), wrong information hierarchy (the 40-times-a-session primary action rendered as a small button in the narrow column while six header actions — two of them destructive — sat in one undifferentiated row, with no queue summary anywhere), and polish gaps.</context>
  <action>Rewrote `live-queue-control.blade.php` and reworked `LiveQueueControl.php`. Ordering CASE now ranks `called` first and gives `skipped`/`no_show` their own ranks; current/in-chamber rows get `recordClasses` highlighting. The five session-lifecycle actions moved into one `ActionGroup` ("Session actions"), leaving "New Walk-In" as the sole standalone primary; the inline `style` overriding End Session's colours was deleted in favour of Filament's own `danger` treatment. Added a queue summary strip (waiting / seen / no-show / avg consult measured from today's completed consults with a schedule fallback / projected finish time) via a new `queueStats` computed property, a live elapsed timer with server-clock-skew correction, explicit skip labelling with its consequence spelled out ("No response — skip (1 of 2)" / "mark no-show"), a disabled "No one waiting" state, pause reason + expected-return time with an in-card Resume, per-row "Call now" for out-of-turn calls, a TV-screen copy-link, session cards instead of a bare "choose a session" line, an animated live dot, auto-selection when today has exactly one session, and a one-tap "Enable sound" recovery when the browser blocks announcement audio. Per-patient success toasts on call-next and patient-arrived were removed (the card already shows the state); the skip toast now names the patient and says what happens next.</action>
  <reason>All three groups had the same root failure — the screen showed state without showing consequence, so staff had to hold the queue's rules in their heads. Grouping the destructive lifecycle actions and spelling out what skip does were preferred over adding confirmation dialogs, because a queue screen is tapped constantly and extra modals would be dismissed reflexively.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>While rebuilding the page it turned out that this panel has no custom Filament theme build, and Filament's shipped `public/css/filament/filament/app.css` contains none of the general Tailwind utilities the page was written against — `grid-cols-3`, `md:grid-cols-2`, `gap-6`, `space-y-6`, `text-sm`, `w-full`, `flex-1` and `max-w-xl` all return zero matches in that bundle. The scattered inline `style=""` attributes in the old markup, which read like redundant duplication of the adjacent Tailwind classes, were in fact the only thing making the layout work.</context>
  <action>All layout, spacing, typography and colour for this page now live in one `<style>` block at the top of the view using `lqc-`-prefixed classes and real CSS, with `.dark` overrides for dark mode. Filament's own components (`section`, `button`, `badge`, `callout`, `input.select`) are still used and bring their own styling. Documented the reason in a comment above the block so the next person does not "clean it up" back into Tailwind classes.</action>
  <reason>Writing Tailwind utilities that silently do nothing is worse than plain CSS: the markup reads as if it is styled and the breakage only shows up visually. Adding a custom Filament theme build to compile Tailwind for one page was rejected as disproportionate — it would add a build step and asset-publishing burden to the whole panel.</reason>
</decision>

## 2026-08-07T09:57:21+0600

<decision>
  <category>Business_Logic</category>
  <context>The real consult-room sequence is: patient in → doctor reviews history → conversation → prescribe → **print or send it while the patient is still in the room** → patient leaves → next patient called. The code fused the last three steps: `CompleteBookingWithVisitNotes::finishCurrentSessionPatient()` saved the notes and immediately called `LiveSessionService::completeCurrentPatient()`, which marks the booking completed **and** advances the queue in the same transaction. There was therefore no window in which to hand anything over — by the time the modal closed the next patient was already being summoned, and the only route to a printout was the "Reprint prescription" link buried in Past visits, on a screen that had already flipped to the new patient. Separately, a prescription had no patient-facing surface at all: the sole route was doctor-auth `GET /prescriptions/{id}/print`, so a patient could only leave with paper.</context>
  <action>**Split completion from queue advance.** New `LiveSessionService::completeCurrentPatientWithoutAdvancing()` — same `lockSession()` + transaction as `completeCurrentPatient()`, minus the `advanceQueue()` call — leaves `current_booking_id` on the now-`completed` booking. Consult Screen's `completeAndCallNext` became `completeVisit`; its `callNext` action (which had been calling `completeCurrentPatient()`, harmless only because it was gated to fire when nobody was current) now calls the pre-existing `callNextPatient()` primitive and is visible during the new window. `LiveQueueControl::nextPatient()` split into `completeVisit()` / `callNextPatientOnly()`, with a matching `completed` branch in its blade (status-aware heading + badge, extending the 2026-08-06 "Currently calling/serving" pattern) and no elapsed-timer while closed. Both screens render a shared `prescription-share-actions` partial during the window: **Print prescription** (existing doctor-auth print route) and **Send via WhatsApp**. **New patient-facing share link.** `Prescription::shareUrl()` builds `TenancyUrl::temporarySignedRoute()` (new tenant-aware signed-route helper mirroring `TenancyUrl::route()`'s path-tenancy handling) valid `Prescription::SHARE_LINK_EXPIRY_HOURS` = 48h, served by `GET /prescriptions/{prescription}/share` (`signed` + `throttle:30,1`, **no** `auth`) rendering `tenant/prescriptions/share.blade.php`. Send reuses `Booking::whatsappLink()` **unmodified** with a custom message. Livewire caches `getXProperty()` per request, so both pages gained a `forgetQueueState()` called by every mutating action — without it the post-action re-render showed the pre-action state until the next 3s poll.</action>
  <reason>A doctor should not have to choose between handing over the prescription and keeping the queue moving; the software was forcing that choice. The new patient-facing surface does **not** reopen the Stage 4 rule that patient-facing pages never leak diagnoses or prescriptions: it is scoped to exactly one prescription — medicines, the dosing advice and follow-up date written on that prescription, prescriber name/registration, patient name, date — and the controller never loads `visit_records` clinical fields, so diagnosis, tests advised and reports seen cannot leak even by template error. No chamber contact details. It is reachable only through an unguessable, expiring, per-prescription signature, not a login or portal, and nothing on the page links onward into the practice's records. Dosing advice and the follow-up date **are** included deliberately: a prescription copy without "take after meals" is less safe than no copy. The link is expiring-but-reusable inside its 48h window rather than literally single-use — a patient reopening the same WhatsApp message to re-check a dose is the expected behaviour, and true single-use would need new persisted state for no safety gain. Sending stays a human-tapped `wa.me` link, so the "no WhatsApp Business API, never automated" constraint in `architecture.md` still holds.</reason>
</decision>

## 2026-08-07T10:32:00+0600

<decision>
  <category>UI/UX</category>
  <context>Two gaps found while walking the new two-step consult flow. (1) Splitting "Complete visit" from "Call next patient" means the queue now waits for a human — if the doctor hands over the prescription and then gets distracted, nobody is called and the waiting room stalls with no sign anything is wrong. The owner's note: staff usually see the patient walk out and press the button, so this is normally a non-event — it only needs to speak up when nobody has. (2) The outdoor screen announced a serial once; in a real waiting room one pass is missed over conversation.</context>
  <action>(1) New shared `filament/tenant-admin/components/call-next-nudge.blade.php` on both Consult Screen and Live Queue Control: a green check reading "Visit completed — ready for next patient" that turns amber with a bell and a live "Nobody called yet — Ns" counter after `Booking::CALL_NEXT_NUDGE_SECONDS` (30s). Counted client-side with the same clock-skew correction as the consult timer, because Live Queue Control has no `wire:poll` and a server-side check would never fire there. (2) `resources/views/tenant/screen.blade.php`'s `speakCall()` now repeats the clip `ANNOUNCE_REPEATS` (3) times with a 700ms gap, guarded by an `announceSequence` token so a newly called serial cuts a sequence still repeating the previous one, and bailing out on mute or a failed/missing clip rather than retrying twice more.</action>
  <reason>30s not minutes: the room is empty and someone is waiting outside, so the useful window is short. It stays a colour and wording change rather than a sound or modal, because the owner is right that staff usually catch it — an alarm for the common case would train people to ignore it. Three repeats is what a human receptionist does; the sequence token matters because without it a fast Call-next would leave two serials talking over each other, which is worse than announcing once. The admin panel's own announcement is deliberately left at one play — staff are looking at the screen, and three repeats at the desk would be irritating.</reason>
</decision>

## 2026-08-07T10:33:28+0600

<decision>
  <category>UI/UX</category>
  <context>Owner's question: "where do they get prescribed?" While the patient was in the chamber the Consult Screen was entirely read-only — name, allergies, last visit, past visits — with no writing area anywhere. The only way in was a **Complete visit** button in the top-right header, which reads as "I am finished with this patient", not "write the prescription". But a doctor prescribes *during* the conversation, not after it. So the one control needed mid-consult was labelled as the end-of-consult action and parked away from the patient's details. Owner then confirmed the doctor must also be able to reopen and edit before finishing.</context>
  <action>Added a **Write prescription** / **Edit prescription** button on the patient's own card (`ConsultScreen::writePrescriptionAction()`, mounted from the blade), visible while the booking is `in_chamber` and the user `canRecordVisitNotes()`. It opens the same `VisitNotesFormSchema` and saves through `VisitRecordService::saveForCompletedBooking()` **without** touching booking status, so the visit stays open. New `VisitNotesFormSchema::stateFromRecord()` maps a saved `VisitRecord` (+ prescription items, voice, photo) back into form state, wired via `->fillForm()` on both the write action and `completeVisit`, so reopening shows what is already there and finishing never presents a blank form over an existing prescription. The card summarises what has been written ("Prescription so far" + diagnosis / N medicines / voice / photo chips) or says "Nothing written yet". New `ConsultScreen::currentVisitRecord` property (distinct from `lastVisitRecord`, which excludes today's booking) feeds both, and is cleared by `forgetQueueState()`.</action>
  <reason>Writing and finishing are two different moments in a real consult and should not share one button. Re-editing is required because patients mention things late — the doctor must be able to add a medicine without having already closed the visit. Safe to reuse the existing save path because `saveForCompletedBooking()` was already idempotent per booking (`updateOrCreate` on both visit record and prescription, items deleted and recreated), so repeated saves replace rather than append — verified in the browser: two saves produced `[SERGEL, NAPA]` with one visit record and one prescription, and dropping a medicine on a later edit actually removes it. Note the method name now understates its use (it is no longer only for completed bookings); left as-is this pass to avoid churn across its three call sites while another session is committing in this repo.</reason>
</decision>

## 2026-08-07T11:01:46+0600

<decision>
  <category>UI/UX</category>
  <context>UX review of the mid-consult prescription flow found the patient card would say "Prescription so far" and offer "Edit prescription" as soon as a doctor saved *any* field — including just advice text with no diagnosis and no medicine. A doctor scanning the card mid-consult had no way to tell "a prescription exists" from "some notes exist but nothing has been prescribed," and in the advice-only case the chip row was empty (advice/tests advised/reports seen/follow-up date had no chip at all), so the card showed a bold claim with nothing underneath it.</context>
  <action>The card and the write-prescription button now key off whether the saved visit record actually has a prescription with at least one medicine (`->prescription?->items->isNotEmpty()`), not merely whether a `VisitRecord` row exists. Notes-only state reads "Notes so far — no medicines yet" and the button stays "Write prescription"; only once a medicine exists does it become "Prescription so far" / "Edit prescription". Added chips for Advice, Tests advised, Reports seen, and Follow-up set so nothing saved is invisible on the summary.</action>
  <reason>"Prescription" is a specific clinical claim — it should not be used for a bag of unrelated notes fields. Matching the label to what was actually written keeps the summary honest and prevents a doctor from being told there's something to "edit" that was never written.</reason>
</decision>

## 2026-08-07T12:21:37+0600

<decision>
  <category>Business_Logic</category>
  <context>Doctors in Bangladesh prescribe by brand name; retyping dose/frequency/duration every visit is slow. A shared national catalogue helps new doctors, but each doctor's real defaults differ and must learn from their own completed visits — not from mid-consult drafts that might never be handed to the patient.</context>
  <action>Added tenant-agnostic `medicines` catalogue (~95 Bangladeshi brands from `data/medicine-list-draft.csv`) plus per-doctor `medicine_usages` learning rows. `MedicineService::search()` ranks catalogue + personal usage; selecting a medicine in `VisitNotesFormSchema` prefills only blank sibling fields with a visible hint. `MedicineService::recordUsage()` and `ConditionService::recordUsage()` run only when the booking is already `completed` at save time. Doctors manage their own rows on **My medicines** (edit defaults, hide, add manual) without editing the shared catalogue.</action>
  <reason>Brand-first prescribing matches paper scripts; learning must reflect what was actually dispensed, not notes still being edited mid-consult.</reason>
</decision>

<decision>
  <category>UI/UX</category>
  <context>Owner walk-through: the consult modal buried prescription under diagnosis, used awkward field labels, date-first follow-up, and the voice recorder buttons did nothing in the Livewire modal. The tenant admin panel also lacked Tailwind utilities for mobile layout work on Consult Screen.</context>
  <action>Reordered `VisitNotesFormSchema` (prescription first), unified Diagnosis select with free-text option, renamed "Reports seen" → "Reports the patient brought", relative follow-up chips + `follow_up_note`, frequency/duration quick picks, **Same as last visit**, allergy strip, complete-visit read-only summary with Edit. Consult Screen: warnings above write section, mobile sticky bottom queue actions, two-column layout from tablet up. Filament `tenantAdmin` Vite theme (`resources/css/filament/tenantAdmin/theme.css`) registered via `ConfiguresTenantAdminPanel::viteTheme()`. Voice recorder wrapped in `@script`; optional STT auto-fills blank fields only when tenant `voice_transcription` is enabled — doctor confirms everything. Supersedes the 2026-08-06 "no STT" deferral for tenants that opt in.</action>
  <reason>Mobile-first consult room: the doctor's thumb reaches primary actions; medicine entry is the hot path; voice should draft, not silently commit.</reason>
</decision>

## 2026-08-07T14:09:24+0600

<decision>
  <category>Business_Logic</category>
  <context>Many established doctors do not want to type a prescription on a device mid-consult. They would rather write on paper as they always have and let staff key it into the system afterwards. Until now this was impossible: `canRecordVisitNotes()` is doctor-only and `VisitRecordService::saveForCompletedBooking()` 403s for staff, so staff completing a booking recorded nothing at all — the practice lost the prescription history, "Same as last visit", and searchable diagnosis for every paper-first doctor. This contradicts the earlier logged rule ("`canViewVisitNotes` / `canRecordVisitNotes` are doctor-only", 2026-08-06, and `patient-records-plan.md` "Who may read notes — only doctors"), so it was raised with the owner before implementing rather than assumed.</context>
  <action>Added a **per-doctor** delegation switch `doctors.staff_may_enter_prescriptions` (default **false**) and a new capability `User::canEnterPrescriptionFor(?Doctor)`, kept deliberately separate from `canRecordVisitNotes()` / `canViewVisitNotes()`, which remain doctor-only. When on, staff get an **Enter prescription** action on that doctor's *completed* Daily Roster rows, opening `VisitNotesFormSchema::staffPrescriptionComponents()` — medicines, follow-up and a photo of the paper slip only. `VisitRecordService::saveStaffEnteredPrescription()` re-checks the permission, intersects the payload against `STAFF_WRITABLE_FIELDS`, and writes only prescription / follow-up / photo columns, preserving any diagnosis or voice note the doctor already recorded. Per the owner's explicit choice there is **no per-prescription doctor approval step** — the switch itself is the standing permission — and staff visibility is prescription-only, not "today's visit" or full notes. Medicine usage learning is not recorded for staff entries because usage is keyed to the acting user.</action>
  <reason>The owner chose delegation-by-permission over an approval queue: an approval step the doctor must clear for every visit recreates exactly the device hassle they were avoiding. Scoping the switch to the individual doctor (not the tenant) means a practice can mix a paper-first doctor with one who types their own, and keeping the capability separate from `canViewVisitNotes()` means the "staff cannot read patient records" promise in `patient-records-plan.md` still holds for history, diagnosis, voice notes and the print route. Default off so no existing practice silently widens staff access on upgrade. The paper-slip photo is required-in-spirit rather than enforced: it makes the doctor's actual handwriting checkable against what was typed, which is the only real defence against a transcription error on a document that prints under the doctor's BM&DC number.</reason>
</decision>

## 2026-08-07T14:32:26+0600

<decision>
  <category>UI/UX</category>
  <context>The prescription-tab medicine picker confused doctors: 34 ORS variants buried GI medicines, generic-name search stopped working after the grouped dropdown change, "Other medicine — type below" required two fields, and each added medicine stayed fully expanded so a four-item script became a long scroll.</context>
  <action>Pruned 10 nonsense `ORS PLUS ORS *` CSV rows and moved remaining ORS brands to a **Rehydration** category; added `medicines:load --prune`. Appended generic to `Medicine::displayLabel()` for dropdown search. Replaced the bottom "Other" option + `medicine_name_custom` field with Filament's **+** create action on the medicine `Select` (`MedicinePickerFields`). Prescription repeater items collapse once `medicine_name` is filled.</action>
  <reason>Doctors search by brand and generic; a clean GI list and searchable labels restore the old API-search behaviour without reintroducing a second field. The + button matches the user's chosen pattern (not diagnosis-style free text). Auto-collapse keeps multi-medicine scripts readable on mobile without removing edit access.</reason>
</decision>

## 2026-08-07T15:39:46+0600

<decision>
  <category>Business_Logic</category>
  <context>The voice → field auto-fill had never actually worked in this build: `TRANSCRIPTION_DRIVER` was unset (so the `log` driver returned an empty draft), there was no `OPENAI_API_KEY`, and the `voice_transcription` feature flag defaulted to false on both tiers. The owner asked whether a free alternative existed and, if not, to take the feature out of this version rather than ship a dead switch.</context>
  <action>Deferred speech-to-text entirely. Removed the transcription service, its config, the `POST /api/visit-media/transcribe` route and controller method, the `visit-notes-draft` Livewire listener, `VisitNotesFormSchema::mergeDraftIntoState()` and the `_machine_filled` state key, the recorder blade's transcription branch, and the `voice_transcription` tier flag. All of it is stashed unloaded under `docs/deferred/voice-transcription/` with a restore guide. **Plain voice notes are explicitly kept** — record, store, play back — per the 2026-08-06 decision that recording is the primary capture for doctors who will not type. `visit_records.voice_transcript` stays as the manual optional field it originally was, relabelled "Voice note summary" so it no longer implies a machine draft.</action>
  <reason>No free option covers the whole pipeline: speech-to-text has free-ish paths (browser Web Speech API, self-hosted Whisper, Groq free tier) but turning a transcript into structured medicine rows needs an LLM, and none of the free ones are reliable enough. Cost was never the real blocker — accuracy was. Doctors dictate mixed Bangla-English with Bangladeshi brand names, close to a worst case for Whisper, and a misheard drug name on a document printed under a BM&DC number is a patient-safety failure, not a UX annoyance. Stashing rather than deleting keeps the working pipeline recoverable when models handle Bangla-English medical dictation well enough to trust. This supersedes the STT opt-in added on 2026-08-07T13:0x ("optional STT auto-fills blank fields when tenant `voice_transcription` is enabled") and returns the product to the 2026-08-06 "no speech-to-text integration" position.</reason>
</decision>

## 2026-08-07T16:16:01+0600

<decision>
  <category>Business_Logic</category>
  <context>Reviewing whether the clinic tier had kept pace with the solo-tier V2 work turned up a structural gap: a doctor's **login** (`users.role = doctor`) and their **prescribing profile** (`doctors` row, which carries `practice_type`, qualifications and BM&DC number) were unrelated records with nothing joining them. Anything that needed to know "which doctor is this?" outside a booking therefore had to guess, and `MedicineService::resolvePrescribingDoctor()` could only guess for a solo practice (`Doctor::first()` — there is only one). A clinic fell through to `null`, and the medicine list silently defaulted to the general-physician catalogue. A dermatologist at an 8-doctor clinic opening **My medicines** was not offered her own dermatology brands; the same doctor at a solo practice was.</context>
  <action>Added nullable `doctors.user_id`, unique per tenant, with a **Login account** select on the Doctors form (tenant-scoped `unique` rule, so one account cannot claim two profiles) and a `User::doctorProfile` relation. `resolvePrescribingDoctor()` gained one step in the middle: booking's session doctor → **signed-in doctor's own paired profile** → solo's single doctor → `null`. The migration backfills the pairing **only** where a tenant has exactly one doctor profile and exactly one doctor login; multi-doctor clinics are left blank. No foreign key — SQLite cannot add one to an existing table — so a `deleting` hook on `User` clears the link instead. Pairing stays optional.</action>
  <reason>The booking still wins when there is one, because a doctor covering a colleague's session should prescribe as that session, not as themselves. Backfilling only the unambiguous case is the point of the whole change: guessing a pairing in a multi-doctor clinic would eventually put one doctor's name and registration number on another's printed prescription, which is worse than the papercut being fixed. Pairing is optional because a practice may list a visiting doctor who never logs in. `null` remains a meaningful answer — `User::canEnterPrescriptionFor()` reads it as deny, so staff paper-prescription entry keeps failing closed rather than opening up.</reason>
</decision>

## 2026-08-07T16:34:50+0600

<decision>
  <category>UI/UX</category>
  <context>The clinic tier had fallen behind solo: the solo homepage got the V2 design pass and is now locked, while clinic still rendered the older interim shell. The Clireo/CBPH reference was approved as the clinic design direction on 2026-08-06 but had never been applied to the live site — it existed only as a static mock at `public/previews/clireo-homepage.html`. The owner asked for a **full port, all sections**, with colours **driven by each tenant's `theme_color`** rather than the reference's fixed navy/pink.</context>
  <action>Phased port. Phase 1 (this task): extracted the reference's `<style>` and `<script>` into `public/css/clinic-clireo.css` and `public/js/clinic-clireo.js`, then rewrote `:root` so every colour derives from one `--brand` — `--ink` / `--ink-deep` (text and dark surfaces) as `color-mix()` of brand into near-black, `--accent` / `--accent-2` (the old pink role) as light tints of brand, `--muted` / `--line` / `--bg` mixed from those; the 11 hardcoded navy `rgba()` shadows and overlays became `color-mix(… , transparent)` of the same tokens. `tenant/webpage.blade.php` was rewritten as the Clireo shell and is the single place `--brand` is set. Phases 2–4 (not done): the 8 sections the reference covers, the 9 it does not, then the clinic book/ticket/portal shells. The Tailwind CDN stays in the shell until the last section blade is converted.</action>
  <reason>Deriving from one `--brand` keeps the reference's layout, type scale, spacing and motion — which is what was approved — while letting each clinic look like itself, which is what the owner chose over a fixed palette. Tokens rather than literals because a hardcoded hex is a tenant whose branding silently stops applying, and the failure is invisible in testing on one tenant. Porting the stylesheet wholesale (rather than re-deriving styles section by section) means phases 2–4 wire markup to classes that already exist and are already tenant-coloured. Keeping the Tailwind CDN during the port is deliberate: dropping it at phase 1 would have collapsed all 18 un-ported sections, so a clinic homepage shows mixed-era styling until the port finishes — an accepted, temporary state, recorded here so it is not mistaken for a bug.</reason>
</decision>

## 2026-08-07T17:15:48+0600

<decision>
  <category>UI/UX</category>
  <context>Phase 2 of the clinic homepage port. The Clireo reference leans on photography it hardcodes — a portrait per physiotherapist, a treatment photo per service card, a patient headshot beside every review, and an abstract icon per feature. None of the tenant web-page blocks store any of that: `doctors` has no image column, `service_matrix` items are title + description, and `testimonials` items are quote + name + label. The hero's right column in the reference is a working booking form, which we also already have as the `/book` wizard.</context>
  <action>Ported all 8 mapped sections, substituting for the missing data rather than filling it with stock imagery: `.doc-card--initial` shows the doctor's initial on a brand-tinted panel, `.treat-card--textonly` drops the media box and keeps the copy, `.review-person` shows name and label with no avatar, and `.about-feature` uses the clinic's own uploaded gallery photo where the reference had an icon. The hero card is a summary that links into `/book`, not a second booking form. `testimonials` now has one `.review-scroller` for every width instead of separate mobile and desktop markup holding two copies of each quote. Per-service booking survived the loss of the 8-or-more list layout: every treatment card is itself a link to `/book`.</action>
  <reason>A stock face beside a named consultant, or a stranger's photo above a real patient's quote, misrepresents the clinic to patients choosing who to see — worse than an honest initial or a plain card. Duplicating the booking form in the hero would create a second source of truth for availability and serial numbers, which is how double-booked serials happen. Keeping every service card a link preserves the per-service CTA that the old list layout provided, so the restyle costs no conversion path.</reason>
</decision>

## 2026-08-07T17:54:55+0600

<decision>
  <category>UI/UX</category>
  <context>Phase 3 of the clinic homepage port covered the nine blocks the Clireo reference never had. Two things surfaced while doing it. First, `image_carousel` was the one section built on Alpine, and the new clinic shell does not load Alpine — phase 1 had silently broken it (slides stacked, arrows dead). Second, and more seriously, the solo shell resolves each block as "solo override if one exists, else `tenant.sections.*`", so nine of the twelve blades being rewritten were also rendering on the **locked** solo homepage.</context>
  <action>Ported all nine into the reference's language, inventing only where it had no counterpart (`.marquee` for trust badges, `.why-card` grids for journey/conditions/locations, `.book-band` for the wizard entry, `.slider` for the carousel, `.rich-text` for policy copy). `image_carousel` was rebuilt as a scroll-snap track that works with no JS at all, with arrows, dots and autoplay added by `clinic-clireo.js` as an enhancement; autoplay stops permanently at the first interaction. `stat_band` animates only values that are plain integers with an optional suffix, and the counted span's own text is the real value rather than the reference's hardcoded "0". Copied the 12 pre-port shared blades into `resources/views/tenant/solo/sections/` so solo renders exactly what it rendered before, and `tenant/sections/` became clinic-only.</action>
  <reason>The solo copies are the important part: the patient-homepage lock is meaningless if a clinic-side restyle can reach solo through a view-resolution fallback, and the failure would have been invisible in clinic testing. Pinning costs 12 duplicated files and buys a boundary that matches how the lock is actually written. On the carousel, scroll-snap-first means the section degrades to a swipeable strip rather than to nothing, which is what the Alpine version did once its library was gone. On the stats, an animated counter that prints "50000+" or "24" is worse than no animation: those numbers are claims a clinic makes about itself.</reason>
</decision>

## 2026-08-07T19:38:58+0600

<decision>
  <category>UI/UX</category>
  <context>Phase 4 of the clinic Clireo port: the book, ticket, and portal pages. These three shells were still on the interim solo language (DM Sans + Instrument Serif, Tailwind CDN, pill `.solo-cta` nav). The booking wizard and ticket body are shared with solo (`tenant.partials.booking-wizard`, `tenant.partials.ticket-body`) and carry real booking/queue logic — touching their markup risks the locked solo flow. The clinic homepage had finished phases 1–3 but still loaded the Tailwind CDN as a safety net; with all 18 section blades ported, that dependency could finally go.</context>
  <action>Restyled the three clinic shells only: same Clireo head/nav as `tenant/webpage.blade.php`, with per-shell `<style>` blocks that re-tokenize the shared partials' semantic classes (`.step`, `.selection-card`, `.ticket-card`, `.serial`, `.btn`, …) onto `--brand` / `--ink` / `--accent`. Did not edit the partials. On the ticket shell, aliased `--color-primary` and `--radius-md` because `ticket-body` has a few inline `var(--color-primary)` references. Portal was a full rewrite (no shared partial underneath). Added `.btn-contact--always` to `clinic-clireo.css` because book/ticket/portal have no mobile drawer — without it the nav CTA would be hidden below 900px. Removed the Tailwind CDN and `card-grid.css` from the clinic homepage; clinic card rows use `.grid-cards` from `getwebfield-spacing.css`. Solo shells unchanged and still on Tailwind.</action>
  <reason>Shell-only restyling keeps one wizard and one ticket implementation for both tiers — the alternative (forking the partials) doubles every booking bug fix. Re-tokenizing in the shell is the same pattern the booking wizard already used before the port (each tier styled `.selection-card` differently). Aliasing `--color-primary` on the ticket page is cheaper than editing a shared partial for one clinic colour swap. Dropping Tailwind from the clinic homepage is only safe once every blade it renders is Clireo-native; doing it at phase 1 would have collapsed unported sections. Keeping Tailwind on solo is deliberate: the locked solo homepage is not part of this port.</reason>
</decision>

## 2026-08-07T20:10:01+0600

<decision>
  <category>CRO</category>
  <context>Sales leave-behind PDF for Pain Point / Feature / Solution was decided earlier (2026-08-07T00:39:08) as Doctor-Gemini-Problem-Feature-Solution.pdf but never landed in the repo — only an orphan copy sat in Downloads/slides after the ChamberQ rename removed docs/slides/.</context>
  <action>Checked in `docs/slides/ChamberQ-Painpoint-Feature-Solution.pdf` generated from `docs/slides/build-painpoint-feature-solution.py` (reportlab). Columns are Pain Point / Feature / Solution (15 rows). Copy refreshed for current product: voice note + paper photo + optional staff medicine entry; clinical diagnosis/voice stay doctor-only. Footer pricing matches `config/marketing.php` defaults (Solo Tk 5,000 / 2,000; Clinic Tk 25,000 / 7,500). Also restored the two ChamberQ pitch decks and their build scripts into `docs/slides/` from the same Downloads folder so the three leave-behinds live together again.</action>
  <reason>A doctor keeping one page after a WhatsApp or chamber meeting needs the current product name and accurate access rules — the Downloads orphan still said staff can never open a prescription, which is wrong once a doctor turns on staff paper entry. Regenerating from a script (not hand-editing the PDF) keeps pricing and feature rows rebuildable when the product moves again.</reason>
</decision>

## 2026-08-07T20:48:02+0600

<decision>
  <category>CRO</category>
  <context>Owner asked for a second Pain Point / Feature / Solution leave-behind limited to the top 7 rows, with no Clinic mention — for Solo sales conversations where the full 15-row page (including multi-doctor, labs, Clinic pricing) is too much.</context>
  <action>Added `docs/slides/ChamberQ-Painpoint-Feature-Solution-Solo-Top7.pdf` from `build-painpoint-feature-solution-solo-top7.py`. Same palette and columns as the full page; seven Solo-relevant rows (booking, live queue, outdoor screen, patient records, prescriptions, voice/paper/staff notes, doctor-only clinical data). No Clinic tier, labs, multi-doctor, or Clinic pricing — footer is Solo only (Tk 5,000 / 2,000).</action>
  <reason>A Solo pitch meeting needs a short desk leave-behind; Clinic features on the same page dilute the story and invite “do I need Clinic?” distraction before the doctor has bought Solo.</reason>
</decision>

## 2026-08-07T20:52:50+0600

<decision>
  <category>CRO</category>
  <context>Owner clarified the Solo Top-7 leave-behind must make the doctor feel pumped enough to *want* to pay setup in the meeting — desire, not a payment form or hard ask.</context>
  <action>Rewrote `ChamberQ-Painpoint-Feature-Solution-Solo-Top7.pdf`: headline “Imagine tomorrow’s chamber”; columns “What’s wearing you down / What fixes it / How tomorrow feels” with visceral pain and emotional-outcome solutions; navy close band “Say yes in this meeting — your page can be live tomorrow” with Solo price callout (Tk 5,000 / 2,000) and soft line that setup is done-with-you. No Clinic. No bKash form.</action>
  <reason>A feature table informs; a felt before/after plus a clear “yes today → live tomorrow” close creates urgency without contradicting pay-at-chamber for patients or the white-glove setup story. Hard payment instructions belong with the salesperson’s bKash number, not printed on a leave-behind that may be forwarded.</reason>
</decision>

## 2026-08-07T20:55:58+0600

<decision>
  <category>CRO</category>
  <context>Owner clarified “top 7” means the seven highest-desire Solo features for a sales close — not the first seven rows of the full 15-row product map.</context>
  <action>Rebuilt Solo Top-7 PDF around sales-priority features: (1) online serial booking, (2) live queue + ticket, (3) outdoor screen with voice, (4) branded patient website, (5) patient record + Consult Screen, (6) digital prescriptions, (7) multi-chamber up to 5. Dropped privacy/voice/household/vacation from this page (they stay on the full map). Kept pumped headline/close; still no Clinic/labs.</action>
  <reason>Website and multi-chamber are core Solo buying reasons in Bangladesh (doctors sit at several OPDs; many have no bookable site) and outperform mid-list ops features for “want to pay today” energy. The full page remains the complete feature map.</reason>
</decision>

## 2026-08-07T21:35:22+0600

<decision>
  <category>UI/UX</category>
  <context>Owner rejected colour-only clinic tweaks and demanded the approved Clireo HTML (`public/previews/clireo-homepage.html`) converted into the live clinic homepage, with only the hero right side changed to a real booking form.</context>
  <action>Ported clinic shell + section blades to HTML markup/structure; kept Clireo pink accents; nested stats inside `doctor_grid`; dropped separate locations/stat_band from the demo page order; reseeded `demo` as CBPH (copy, photos, doctors, navy theme); hero right = live GET form into `/book`. Solo shells untouched.</action>
  <reason>Pixel/structure fidelity to the approved reference is the acceptance bar for clinic; a form on the hero is the one product delta that turns the static preview into a bookable ChamberQ page.</reason>
</decision>

## 2026-08-08T01:14:33+0600

<decision>
  <category>Business_Logic</category>
  <context>`/api/patients/by-phone` powers the wizard's "Who is this appointment for?" household picker. It must stay unauthenticated — it runs before any login — which made it a patient-name oracle: any valid BD mobile returned real names and visit counts at 60/min. Four mitigations were weighed with the owner: mask the labels, throttle only, require a name match first, or OTP-verify the phone.</context>
  <action>Chose masked labels plus a hard throttle. The endpoint returns `Patient::maskedPickerLabel()` — initials + age, e.g. `F. R., 34` — and no name field at all, at 10 req/min. Picking someone submits only their `patient_id`; `BookingController` resolves it against this tenant AND this phone, and `BookingService` writes `$patient->name` (never the request's) onto the booking. `PatientService::resolveForBooking()` no longer renames a patient resolved by id. Staff-side pickers (Daily Roster, Live Queue walk-in) keep full names via `pickerLabel()`, since those callers are authenticated.</action>
  <reason>A household recognises its own members from initials and an age; a scraper gets nothing worth having. OTP was rejected as it burns a prepaid SMS credit per lookup and adds a step to the conversion path for a pay-at-chamber v1; name-match-first would have removed most of the picker's value. The server-side name resolution is what makes masking safe — without it the mask would have been written onto tickets and SMS.</reason>
</decision>

<decision>
  <category>UI/UX</category>
  <context>Bangla shipped at 149 of 593 strings. The booking wizard and ticket were nearly complete, but the patient portal was 15 of 18 missing — a patient could book and get a ticket in Bangla, then hit English the moment they checked their appointments. The admin panel is the bulk of the remaining ~450.</context>
  <action>Translated patient-facing surfaces only: the portal (both clinic and solo shells), the 6 remaining wizard strings, and the shared nav/footer keys — 22 new entries, taking `lang/bn.json` to 171. `PatientFacingBanglaTest` now fails if any string in the wizard, ticket-body or either portal lacks a Bangla entry. The staff/admin panel is deliberately left in English and tracked as a separate task.</action>
  <reason>Bangla dropping out mid-journey is worse than a consistently English admin panel: patients are the ones who cannot choose, while staff are trained users on a tool they use daily. Machine-translating ~450 admin strings — including clinical terminology in prescribing workflows — would need native review before it could ship, so it is its own piece of work. The generated Bangla in this pass should still get a native speaker's read before production.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>The `/lang/{locale}` switcher is a GET link that writes to the session, and used `back()`, which trusts the `Referer` header — so a page on another origin could bounce a visitor straight off the clinic's domain. Converting it to a POST form would have meant editing the locked solo homepage shells.</context>
  <action>Kept it a GET link and left all markup alone; replaced `back()` with an explicit same-host check that falls back to the tenant home. The CSRF-shaped aspect (a GET that changes state) is accepted: the only state it writes is display language.</action>
  <reason>The open redirect was the part with real phishing value and it was fixable in one route with no markup change. Converting five shells to POST forms to defend a language toggle would have required unlocking the patient homepage for a change with no user-visible benefit — disproportionate under the prototype scope rule.</reason>
</decision>
