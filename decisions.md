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

## 2026-08-08T01:46:24+0600

<decision>
  <category>Code</category>
  <context>The app targets MySQL in production but had only ever been run on SQLite (local dev, CI, and the whole test suite). Asked how far off production readiness we were, the honest answer was "unknown, because the app has never touched its production database". A validation pass proved the point immediately: it could not be installed on MySQL at all — three migrations were unrunnable.</context>
  <action>Fixed the three schema defects (see `bug_history.md` 2026-08-08T01:46:24) rather than working around them: `tenants` renumbered to `0000_…` so it precedes every table that references it; the `bookings (tenant_id, id)` unique key moved into that table's create migration; the `(tenant_id, slot_block_id)` composite SET NULL foreign key reduced to a single column. Then validated the engine properly — migrations up and fully reversible, seeders, both test suites, every `ONLY_FULL_GROUP_BY` reporting path, utf8mb4 Bangla + emoji round-trip, `EXPLAIN` proof that the earlier `whereDate` → `where` change reaches the index, and an eight-process race on the last remaining seat. Added a `phpunit-mysql` CI job so both engines run on every pull request.</action>
  <reason>Renaming a migration is normally a footgun, but there is no production deployment yet and the alternative — a schema that cannot be created on the target database — is worse. Fixing the ordering gives one identical schema on every engine, which is the point; the alternative of adding MySQL-only constraints later would have left test and production schemas diverging in exactly the area under test. The CI job matters more than any individual fix: these three bugs survived for months purely because nothing ever ran the migrations on MySQL, and without the job the next one would too.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>`doctors.user_id` still has no foreign key. The migration comment says SQLite cannot add one to an existing table, which is true — but MySQL can, and the column is now verified as the only unenforced reference in a schema with 52 foreign keys.</context>
  <action>Left it unenforced, deliberately, rather than adding a MySQL-only constraint. Integrity is maintained in application code by `User::booted()`'s `deleting` hook, which nulls `doctors.user_id` when a login is removed.</action>
  <reason>Adding the FK only where the driver supports it would make the production schema differ from the schema every test runs against — reintroducing precisely the blind spot this pass existed to remove. Worth revisiting if the project ever drops SQLite for testing; until then, one schema everywhere is the more valuable property.</reason>
</decision>

## 2026-08-08T01:58:08+0600

<decision>
 <category>Business_Logic</category>
 <context>Different doctors want different mixes of SMS vs WhatsApp for booking confirmation, doctor-late, cancellation, and prescription hand-off; a single tenant-wide rule forced one chamber policy on everyone.</context>
 <action>Per-doctor `notify_channels` JSON on `doctors` (four stages × SMS/WhatsApp toggles). Defaults match prior behaviour (booking SMS on; cancel + prescription WhatsApp on; late off). WhatsApp stays human-tapped `wa.me` only. SMS uses the prepaid wallet: auto for booking + Mark Late when that stage SMS is on; staff-tapped Send SMS for cancel + prescription via `NotifySmsController`. Empty wallet or prefs off never fail the booking/queue action.</action>
 <reason>Lets Dr A prefer SMS for delays while Dr B keeps WhatsApp for prescriptions without burning credits on stages a doctor did not opt into, and without requiring WhatsApp Business API.</reason>
</decision>

## 2026-08-08T09:53:43+0600

<decision>
  <category>Business_Logic</category>
  <context>Credits are sold to clinics as "1 credit = 1 message" (200 for ৳100), but networks bill per segment and one non-GSM character cuts a segment from 160 characters to 70. A cancellation notice was billing 3 sends against 1 credit. Owner was asked who absorbs the difference, and chose: always make it fit one credit.</context>
  <action>Enforced at the send path rather than in the templates: `GsmText::toSingleSegment()` transliterates to the GSM alphabet and truncates prose (never links) so ordinary messages always cost exactly one credit. Bangla is transliterated rather than rejected, so a patient who typed their name in Bangla still appears in their own confirmation. SMS bodies stay hardcoded English — a test fails the build if `SmsService` ever uses `__()`. The one message that physically cannot comply (a signed prescription link alone exceeds a segment) keeps its context and debits the true 2 credits.</action>
  <reason>The previous approach — a documented rule asking authors to keep bodies ASCII — lasted two days, because the failing text came from a caller rather than a template. Enforcing at the single choke point means no future feature can reintroduce it. Charging true cost in the one impossible case keeps the wallet honest without ever sending a naked URL, which from an unknown number is indistinguishable from a phishing text.</reason>
</decision>

## 2026-08-08T10:36:55+0600

<decision>
  <category>Code</category>
  <context>The doctor-late SMS blast had to come off the request thread, but this application runs no queue worker and never has — `QUEUE_CONNECTION=database` is configured and nothing is queued. Dispatching to the queue would have been the conventional answer and the wrong one: with no worker consuming it, every late notice would sit in the jobs table unsent, and the failure would be silent.</context>
  <action>Dispatched with `->afterResponse()`, which Laravel runs via `dispatchSync()` in the container's terminating callback — same process, after the response is sent, no worker required. The job is still written as a `ShouldQueue` class carrying its own `tenantId` and re-initialising tenancy, so promoting it to a real background job is deleting one method call.</action>
  <reason>A frozen screen is a bad experience; silently not telling patients the doctor is late is a worse one. After-response buys the responsiveness without adding an ops dependency the deployment does not yet have, and costs nothing to reverse once a worker exists. It also keeps tests honest — `dispatchAfterResponse` runs on `app->terminate()`, so the existing assertions still prove the texts are really sent rather than merely enqueued.</reason>
</decision>

## 2026-08-08T11:49:22+0600

<decision>
  <category>Code</category>
  <context>`~/AGENTS.md` §0 governs every project on this machine as "a working prototype, not a hardened production system, unless the user says otherwise", and explicitly tells agents not to over-invest in security hardening, edge cases, or operational concerns. That framing has been shaping every judgement call in this repo — including which audit findings were treated as "fine for a prototype" and deferred. The owner ended the prototype phase on 2026-08-08.</context>
  <action>Recorded the scope change as a project-level override in `CLAUDE.md` and `.cursor/rules/production-phase.mdc` (both agents work in this repo). Hardening, durability, backups, monitoring and deployment configuration are now in scope by default and no longer need to be requested. Correctness is judged against MySQL, not just SQLite. The patient-homepage and session-expiry locks are explicitly **not** unlocked by this, and pay-at-chamber still stands.</action>
  <reason>§0 is a real instruction that changes what an agent volunteers, defers, and treats as good enough — leaving it in place while the product goes live would keep producing prototype-grade judgement on live patient data. It lives in `CLAUDE.md` rather than the repo's `AGENTS.md` because that file is a symlink to the machine-wide protocol; editing it would silently reclassify every other project on the machine. The mirrored `.cursor` rule exists because a second agent is committing to this repo and would otherwise keep working to the old standard.</reason>
</decision>

## 2026-08-08T12:31:00+0600

<decision>
  <category>Business_Logic</category>
  <context>The prescription SMS cost two credits, not one. Its link was a `temporarySignedRoute`, which carries the expiry and the signature in the query string: ~181 characters, longer than an entire 160-character GSM segment before a single word of the message. Credits are sold to clinics as "1 credit = 1 message", so every prescription notice quietly overcharged the wallet by double, and `GsmText` could not fix it — a link is the one thing it will never truncate.</context>
  <action>Moved the link's two security properties out of the URL and into the row. `prescriptions` gained `share_token` (10 base62 characters, unique index) and `share_token_expires_at`; `Prescription::shareToken()` mints one lazily on first share, reuses it while it is still valid, and rotates it once expired. `shareUrl()` now returns `/p/{token}` and the new tenant-scoped route resolves it, checking the stored expiry. The old signed route stays registered until the links already delivered have expired. SMS and WhatsApp both call `shareUrl()`, so both moved together. `prescriptionBody()` also dropped `- view:` for a bare colon.</action>
  <reason>Same guarantees, a twentieth of the characters: 10 base62 characters is ~8.4e17 combinations against a route throttled to 30/min, so the token is no more guessable than a signature. Storing the expiry rather than signing it also buys something the signed URL never had — the link is revocable, because clearing the column kills it. The route is tenant-scoped rather than central so the existing tenant global scope makes cross-clinic lookup impossible by construction instead of by a hand-written `where`. `- view:` went because measurement, not taste: with a long clinic name and a long patient name the body lands within a handful of characters of the ceiling, and those 7 characters are the difference between one credit and two.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>The share view is the one deliberate exception to "patient-facing pages never show prescriptions", and its privacy scope (this prescription's medicines, never a diagnosis) is enforced by what the controller loads. Adding a second entry point for the short link created an opportunity for the two to drift apart.</context>
  <action>`showByToken()` and the legacy `show()` both delegate to one private `render()`; neither builds its own view payload. Added `<meta name="referrer" content="no-referrer">` to the share view.</action>
  <reason>A duplicated `load()` call is exactly how a future change adds `visitRecord.condition` to one path and not the other, and the resulting leak would be invisible in review. The referrer meta is pre-emptive: nothing on the page loads off-origin today, but the token now sits in a URL that is reused for 48 hours, so the day someone adds a logo or a web font the token would start travelling to that third party in the `Referer` header.</reason>
</decision>

## 2026-08-08T12:29:52+0600

<decision>
  <category>Business_Logic</category>
  <context>List prices needed a clean reset for current ChamberQ sales: Solo had drifted across ৳5k and ৳12k setup stories, and Clinic setup at ৳25k no longer matched the intended offer.</context>
  <action>Set marketing defaults to Solo ৳15,000 setup / ৳3,000 monthly and Clinic ৳75,000 setup / ৳7,500 monthly in `config/marketing.php` and `.env.example`. Updated EN/BN client + marketing guides, landing-page test expectations, pitch-deck build scripts, and regenerated `docs/slides/ChamberQ-Painpoint-Feature-Solution.pdf` plus `ChamberQ-Painpoint-Feature-Solution-Solo-Top7.pdf`.</action>
  <reason>One source of truth for what partners quote and what leave-behinds show — regenerating PDFs from scripts prevents the old SoloDoc-style price drift.</reason>
</decision>

## 2026-08-08T12:32:31+0600

<decision>
  <category>CRO</category>
  <context>Owner asked for a PDF of the full feature list (not the Pain Point / Feature / Solution leave-behind) after updating list prices.</context>
  <action>Added `docs/slides/build-full-feature-list.py` → `ChamberQ-Full-Feature-List.pdf`: plain-English catalogue of patient, queue, consult/records/Rx, ops, and Clinic-only features, with Solo ৳15,000/৳3,000 and Clinic ৳75,000/৳7,500 in the footer band.</action>
  <reason>Sales and partners needed one printable “everything we ship” sheet; regenerating from a script keeps it aligned with the product and marketing prices.</reason>
</decision>

## 2026-08-08T13:15:50+0600

<decision>
  <category>CRO</category>
  <context>Owner needed a second leave-behind to share directly with a solo doctor — the internal full feature catalogue includes Clinic extras and reads like a product inventory, not a doctor-facing “what you get.”</context>
  <action>Added `docs/slides/build-solo-what-you-get.py` → `ChamberQ-Solo-What-You-Get.pdf`: Solo-only features in plain chamber language, no multi-doctor/labs, WhatsApp close band with Solo Tk 15,000 / 3,000.</action>
  <reason>Doctors should receive a shareable Solo sheet; partners keep the full catalogue. Same regenerate-from-script pattern as other leave-behinds.</reason>
</decision>

## 2026-08-08T15:26:29+0600

<decision>
  <category>Business_Logic</category>
  <context>Real Bangladeshi prescription pads carry weight and BP on every visit, and BP is often the clinical reason for a referral (e.g. cardiology). Those numbers had no structured home in SolDoc, and the patient share link deliberately excluded all visit_records fields — so a shared Rx could not carry the reading a referred consultant needs.</context>
  <action>Add optional per-visit `weight_kg`, `bp_systolic`/`bp_diastolic`, and free-text `clinical_notes` on `visit_records` (doctor notes modal only; staff paper-entry whitelist unchanged). Doctor print shows vitals in the patient strip plus diagnosis, clinical notes, and tests advised above the ℞ block. Patient share (`/p/{token}`) may show pre-formatted weight and BP labels only — diagnosis, clinical notes, tests, reports, voice and photos remain off the share page.</action>
  <reason>Vitals are the recoverable clinical signal on the pad (Mrs Gouri’s 170/100 explains the cardiology referral); putting them on the patient copy helps the next clinician without reopening the Stage 4 rule that the share link is not a back door into the full visit record.</reason>
</decision>

## 2026-08-08T15:42:23+0600

<decision>
  <category>UI/UX</category>
  <context>The doctor printout reserved a "Doctor's signature" line at the bottom. Owner said doctors who want to authenticate the paper will stamp/seal it rather than sign in a printed box.</context>
  <action>Remove the signature block and its CSS from `tenant/prescriptions/print.blade.php`. No uploaded signature image feature.</action>
  <reason>Unused chrome on every print; a seal on the physical page does not need a labelled blank on the PDF.</reason>
</decision>

## 2026-08-08T15:54:34+0600

<decision>
  <category>Business_Logic</category>
  <context>Vitals validation was written into the save path, where it could refuse a submission. That path runs before the queue advances, so a slipped digit in an optional weight or BP box would have held the booking open and left the next patient uncalled — and the message would not even have rendered next to the field, because Filament actions namespace the error bag.</context>
  <action>Split the two jobs. The form validates (`vitalsSection()`: BP required with its partner in both directions, per-field min/max, systolic must exceed diastolic, with clinical wording via `validationMessages()`). The save path only sanitises — `normalizeVitals()` never throws; an unusable BP is dropped as a pair, an implausible weight is dropped, and the rest of the note is kept. `isUsableBloodPressure()` is the one definition both sides call. `submissionHasContent()`'s docblock now states that it must always answer.</action>
  <reason>Two different failures deserve two different answers. A doctor who mistypes needs to be told, next to the box, before they finish — that is the form's job. A malformed request arriving at the service must not be allowed to stop a patient leaving the chamber; between losing an optional reading and stalling the queue, losing the reading is the recoverable one, and the form makes it nearly unreachable anyway. Keeping the bounds in a shared method rather than restating them in two places is what stops the screen and the database disagreeing later about what a valid reading is.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>The patient share page shows the patient's age alongside their name; the original share-link decision listed only name and date.</context>
  <action>Keep the age and record it in `architecture.md` and `sitemap.md` as part of the documented share scope.</action>
  <reason>Age is on the paper prescription for a reason — a pharmacist reading the patient's own copy uses it to sanity-check a dose, and it is the patient's own data on their own link, adding nothing a stranger holding the link could not already infer from the medicines. The privacy line that matters is unchanged: still no diagnosis, clinical notes, tests, reports, other visits or chamber contact. Documented rather than removed so the scope stops being wider than the record of it.</reason>
</decision>

## 2026-08-08T22:47:24+0600

<decision>
  <category>Code</category>
  <context>Production booking sent patients to a localhost ticket because absolute URLs were built from the internal PHP host behind the reverse proxy.</context>
  <action>Post-booking API `ticket_url` is relative (`tenant_web_route(..., absolute: false)`). Also enable `trustProxies(at: '*')` so other absolute links (ticket copy/share, prescription URLs) use the public forwarded host. SMS ticket links still require a correct production `APP_URL`.</action>
  <reason>A relative path keeps the patient on the domain they already used — like “room 12 in this building” instead of printing the wrong street address. Proxy trust fixes share links that must be absolute; APP_URL remains the SMS source of truth because texts are not tied to a browser session.</reason>
</decision>

## 2026-08-08T23:04:53+0600

<decision>
  <category>UI/UX</category>
  <context>Waiting-room TV links included today's date, so staff had to copy a fresh URL every morning — awkward for a display that should stay bookmarked.</context>
  <action>Canonical outdoor screen link is `/screen/{session}` (and `/api/screen/{session}`), always resolving to `Carbon::today()` in APP_TIMEZONE. Live Queue Control Open/Copy uses that URL and tells staff to bookmark once. Dated `/screen/{session}/{date}` stays for old bookmarks. Stable pages reload when the poll's `session_date` rolls to a new calendar day.</action>
  <reason>One bookmark per Morning/Evening slot matches how a chamber TV is set up once; different sessions still need different links because they are different queues.</reason>
</decision>

## 2026-08-08T23:06:13+0600

<decision>
  <category>UI/UX</category>
  <context>Waiting-room TVs showed the next serial number but not when that patient would roughly be called, so people further back kept asking reception “how long?”.</context>
  <action>Outdoor screen API adds `next_estimated_time`: the same ETA engine’s `actual_estimate` minus a fixed 5 minutes (`ScreenController::TV_NEXT_ETA_LEAD_MINUTES`). The TV bottom strip shows “Next: #N · ~h:i A”. Deliberately not the ticket’s larger “come early” buffer — the board is for people already in the room who need a short walk-up cue.</action>
  <reason>Actual minus five minutes is close enough to stand up without promising the padded arrival time patients see on their phone.</reason>
</decision>

## 2026-08-08T23:17:34+0600

<decision>
 <category>Code</category>
 <context>After fixing the booking ticket redirect, an audit found the same localhost-host bug class on prescription share, portal ticket links, ticket/TV polls, announce audio, and the outdoor-screen Copy link.</context>
 <action>Introduce `TenancyUrl::publicAbsolute()` for every outbound patient/TV URL; keep same-origin paths relative (`absolute: false`) or root-relative via `public_asset()`; harden Domain link scheme so a leftover localhost APP_URL cannot force `http://` onto a real clinic host.</action>
 <reason>One shared builder stops each call site inventing its own host rules; relative same-origin URLs survive a bad APP_URL or missing proxy headers without sending patients off-domain.</reason>
</decision>

## 2026-08-09

<decision>
 <category>CRO</category>
 <context>ChamberQ sales proposals and doctor-facing copy were framing the product as a “patient website,” listing pay-at-chamber as a feature, and saying “no patient login” in a way that sounded like patients could only book — understating ticket follow-up, portal lookup, and prescription links, and missing the always-improving product story.</context>
 <action>Sales and proposal language: (1) call the public site the doctor’s <strong>portfolio website</strong> that also works as their booking platform — not a “patient website”; (2) do <strong>not</strong> sell pay-at-chamber or absence of a payment gateway as a product feature (that is operational reality, not a ChamberQ capability); (3) describe the patient side positively as a <strong>full experience without sign-up</strong> — book, follow ticket, check status by phone, receive prescription links — not “no login accounts”; (4) state that ChamberQ is actively developed and new features roll out to subscribed chambers automatically without per-feature fees or the doctor having to ask.</action>
 <reason>Matches how doctors think about their professional presence (portfolio first), avoids sounding like a limitation when patients get more than booking, and positions the monthly fee as a living product rather than a frozen brochure site.</reason>
</decision>

## 2026-08-09T10:52:29+0600

<decision>
 <category>Code</category>
 <context>Composer on a PHP 8.5 Mac locked Filament's HTML sanitiser to Symfony 8, which crashes on the live PHP 8.3 server whenever Filament sanitises form HTML.</context>
 <action>Require PHP ^8.3 (matches production and Filament's openspout dependency), pin symfony/html-sanitizer to ^7.0, set composer.json config.platform.php to 8.3.0, and re-lock related Symfony components on the 7.4 line.</action>
 <reason>Composer must resolve for the server patients hit, not the developer laptop. v7 still uses Masterminds on PHP &lt; 8.4, so admin saves work on 8.3 without upgrading the host overnight.</reason>
</decision>

## 2026-08-09T23:46:54+0600

<decision>
 <category>CRO</category>
 <context>The CBPH proposal showed only a flat ৳75,000 setup + ৳7,500 monthly, so the hospital could not see why a multi-cabin clinic costs what it does versus a solo chamber.</context>
 <action>CBPH proposal Investment section is itemized and CBPH-named: portfolio website at ৳2,000/page (10 pages = ৳20,000), queue management ৳30,000, waiting-room/outdoor TVs ৳8,000, consult & home-programme screens ৳10,000, rooms/therapists/sittings & training ৳7,000 (setup total ৳75,000); monthly ৳7,500 explained as hosting, multi-therapist use, automatic product updates, and desk support. SMS remains optional prepaid. Cash only.</action>
 <reason>Line items with a plain-English “why” make the quote feel built for CBPH (shared desk, Female Dept., named therapists) rather than a generic clinic price tag — and match how the buyer evaluates work (website pages vs queue system vs screens).</reason>
</decision>


## 2026-08-09T23:58:34+0600

<decision>
 <category>CRO</category>
 <context>CBPH setup line items previously summed exactly to ৳75,000 with a per-page website rate, so the special felt like a flat total rather than a concession — and a hospital-sized site is closer to 15–25 pages than ten.</context>
 <action>Show inflated round list prices that sum to ৳95,000 (website ৳30,000 for a 15–25 page overview with no per-page rate; queue ৳35,000; TVs ৳10,000; consult screens ৳12,000; rooms/training ৳8,000), then CBPH special setup ৳75,000 and “You save ৳20,000”. Monthly stays ৳7,500.</action>
 <reason>Round list → special total makes the discount readable; page-range overview matches how a multi-therapist hospital site is scoped without nickel-and-diming per page.</reason>
</decision>


## 2026-08-10T00:08:17+0600

<decision>
 <category>CRO</category>
 <context>CBPH proposal had too many setup micro-lines (TVs, consult screens, rooms/training) that confused the buyer; website was underpriced for a 15–25 page hospital site; hosting cost was invisible.</context>
 <action>Simplify setup to two cash lines — portfolio website ৳50,000 and “Setup the system” ৳55,000 (queue, TVs, cabin screens, rooms, training) — standard total ৳105,000, CBPH special ৳75,000 (save ৳30,000). Add hosting ৳10,000/year, auto-renewable, paid by ChamberQ until storage reaches 200GB. Monthly remains ৳7,500 for product use, updates, and desk support.</action>
 <reason>Fewer lines read as a hospital package; higher website + system list makes the special feel real; hosting with a 200GB courtesy cap is a clear extra benefit without burying it inside monthly.</reason>
</decision>


## 2026-08-10T00:11:25+0600

<decision>
 <category>CRO</category>
 <context>Owner did not want the CBPH quote collapsed into website + one big “setup the system”; only the three micro-lines (TVs, consult screens, rooms/training) should go — replaced by a small setup fee — while queue management stays its own line.</context>
 <action>CBPH setup lines: website ৳50,000; queue management ৳35,000; Setup the system ৳5,000 (rooms, TVs, cabin screens, training). Standard total ৳90,000 → CBPH special ৳75,000 (save ৳15,000). Hosting ৳10,000/year auto-renewable, paid by ChamberQ until 200GB, unchanged.</action>
 <reason>Matches the owner’s instruction exactly: remove those three priced lines, keep queue separate, put system wiring at ৳5,000.</reason>
</decision>


## 2026-08-10T00:12:31+0600

<decision>
 <category>CRO</category>
 <context>CBPH proposal hosting line was ৳10,000/year.</context>
 <action>Hosting is ৳15,000/year, auto-renewable; ChamberQ still pays until storage reaches 200GB.</action>
 <reason>Owner set the hosting list price at ৳15,000.</reason>
</decision>


## 2026-08-10T00:24:16+0600

<decision>
 <category>CRO</category>
 <context>CBPH quote needed SEO content as a visible line, and the monthly fee needed to show what the hospital pays for besides product use.</context>
 <action>Setup adds “SEO’d blog articles — 6 to start” at ৳3,000 (standard setup total ৳93,000 → CBPH special ৳75,000, save ৳18,000). Monthly ৳7,500 broken into ChamberQ clinic ৳6,500 + 2 SEO’d articles/month ৳1,000. Hosting remains ৳15,000/year (we pay until 200GB). PDF not regenerated until owner asks.</action>
 <reason>Starter + ongoing articles match how search content is sold; breaking monthly makes the ৳1,000 article retainer obvious inside the same ৳7,500 total.</reason>
</decision>


## 2026-08-10T00:26:39+0600

<decision>
 <category>CRO</category>
 <context>CBPH quote needed hosting visible on the one-time table without folding it into cash setup or monthly, and monthly needed the same what/why/amount detail as setup.</context>
 <action>Hosting listed on one-time table as ৳15,000/year with * footnote (auto-renewable; ChamberQ pays until 200GB; not in ৳75,000 cash). Monthly broken like setup: clinic use ৳4,000, updates ৳1,500, desk support ৳1,000, 2 SEO articles ৳1,000 (total ৳7,500). No hosting on monthly. PDF not regenerated until asked.</action>
 <reason>Star + footnote keeps the hosting benefit and courtesy cap clear without inflating the special; matching table shape makes monthly easy to compare with setup.</reason>
</decision>


## 2026-08-10T00:31:15+0600

<decision>
 <category>CRO</category>
 <context>“Ongoing product updates” on the CBPH monthly quote was unclear to the buyer; clinic use needed to read as maintenance for website vs queue, with a slight list inflate like setup.</context>
 <action>Monthly lines: website maintenance ৳3,000, queue maintenance ৳3,500, desk support ৳1,500, 2 SEO articles ৳1,000 — standard ৳9,000 → CBPH special ৳7,500 (save ৳1,500). Dropped “product updates.”</action>
 <reason>Maintenance split matches how the hospital thinks about the site vs the desk; list → special mirrors the setup discount pattern.</reason>
</decision>


## 2026-08-10T00:40:03+0600

<decision>
 <category>CRO</category>
 <context>CBPH monthly maintenance lines felt too high; owner wanted data backup and hosting maintenance on the monthly list while keeping standard monthly at ৳9,000.</context>
 <action>Monthly standard ৳9,000: website maintenance ৳2,000, queue maintenance ৳2,500, desk support ৳2,000, SEO 2 articles ৳1,000, data backup ৳1,000, hosting maintenance ৳500 → CBPH special ৳7,500 (save ৳1,500). Desk support = WhatsApp/remote help for reception and therapists.</action>
 <reason>Lower maintenance + backup/hosting care reads as a fuller ops package without raising the special.</reason>
</decision>


## 2026-08-10T02:22:55+0600

<decision>
 <category>Business_Logic</category>
 <context>The shared medicine catalogue (~88 brands) was too thin for real GP prescribing and weak in sales against “large database” competitors; the owner wanted ~450 verified-enough brands without a 24k MedEx dump or licensing risk.</context>
 <action>Expanded `data/medicine-list-draft.csv` to ~460 brands across 22 categories. Added `data/build-medicine-catalogue.py`: ~60 household brands pinned with correct strength/form; remaining rows scored from BDDrugBank `medex_merged.csv` (build-time reference only) within per-category budgets. ORS capped at ~10 real products (removed nonsense ORS PLUS variants). Production test now expects &gt;400 rows after `catalogues:load`.</action>
 <reason>Curated scale beats raw row count for prescribing UX and patient safety; pinned brands stop MedEx multi-form ambiguity (e.g. ACE IV vs 500 mg tablet); rebuild script keeps the list maintainable without shipping third-party data in the repo.</reason>
</decision>


## 2026-08-10T16:10:23+0600

<decision>
 <category>Business_Logic</category>
 <context>Pre-production readiness called for off-server data copies after cyber attack or a from-scratch redeploy; nothing in the app exported or restored tenant data, and clinical media on disk was already flagged as unbacked-up.</context>
 <action>Ship disaster-recovery ZIP export + import: chamber Admin **Data backup** page (Settings) and Super Admin **Platform data backup** plus per-tenant download/restore on Tenants. Each ZIP holds one UTF-8 CSV per table, `manifest.json`, FK-ordered import (`replace` or `merge`, optional `dry-run`). Artisan `data:backup-export` / `data:backup-import` for empty-server rebuilds. Passwords never exported; import assigns random passwords. Voice/photo binaries stay off-ZIP (paths only).</action>
 <reason>One-click full-chamber backup matches the owner's disaster-recovery goal better than per-table Filament exporters; round-trip CSV keeps Excel-readable copies while UUID primary keys preserve relationships on restore.</reason>
</decision>

2026-08-10T20:50:09+0600

## 2026-08-10T20:49:56+0600

<decision>
  <category>Business_Logic</category>
  <context>Small / price-sensitive solo chambers need a quieter, thinner offer than full Solo, without becoming the main sales focus or a separate company brand. Full Solo remains the primary one-doctor product; Clinic stays for multi-doctor.</context>
  <action>Split one-doctor marketing into two named plans under ChamberQ: **Rising Star** (small-doctor lane) and **Maestro** (main Solo, formerly marketed as Solo). Rising Star: full multi-section homepage; **no extra custom pages** (homepage only); **no outdoor/TV queue screen**; **live queue on the patient’s phone ticket**; website + booking focused; **100 patients per month** cap; listed on the central marketing homepage **only in the pricing section** (not featured elsewhere) plus a dedicated **subdomain**; not actively marketed beyond that. Maestro: full Solo product including TV/outdoor screen; will later support **additional custom pages**. Clinic plan unchanged. Rising Star is not the owner’s main focus.</action>
  <reason>Gives small chambers a clear, limited package that protects Maestro as the premium one-doctor story, caps support load (no TV setup, no multi-page site, monthly patient limit), and still lets curious buyers find Rising Star without a second brand or a hard Lite push in every sales channel.</reason>
</decision>
2026-08-10T21:09:55+0600

## 2026-08-10T21:09:55+0600

<decision>
  <category>Business_Logic</category>
  <context>Rising Star / Maestro plan split was logged 2026-08-10T20:49:56+0600 without prices, subdomain hostname, or a precise patient-cap definition.</context>
  <action>Rising Star pricing: **৳8,000 setup / ৳1,200 per month** (~৳22,400 year-1). Cap = **100 bookings created per calendar month** (not unique patients); overage should block further online bookings and point to Maestro upgrade. Marketing subdomain: **risingstar.chamberq.com**. Maestro keeps current Solo sticker **৳15,000 setup / ৳3,000 per month** until changed. Clinic unchanged.</action>
  <reason>Option B setup with a slightly lower monthly (৳1,200 vs ৳1,500) keeps Rising Star clearly under Maestro (~half year-1) without racing the ৳500 free-tier competitors; booking-count cap is simple to enforce and explain.</reason>
</decision>
2026-08-10T22:08:24+0600

## 2026-08-10T22:08:24+0600

<decision>
  <category>Business_Logic</category>
  <context>Rising Star monthly booking cap was set at 100 (2026-08-10T21:09:55+0600). Owner tightened the small-doctor lane and clarified unlimited use on higher plans.</context>
  <action>Rising Star cap is **50 bookings created per calendar month** (was 100). **Maestro** has **no booking cap** (unlimited). **Clinic** is **yes to everything** in this split: full site features (including extra pages when shipped), TV/outdoor screen, phone live queue, multi-doctor/labs as today, and **no Rising Star-style booking cap** (unlimited).</action>
  <reason>50 bookings/month better matches a small chamber and pushes upgrade to Maestro sooner; Maestro and Clinic stay the unlimited operational products.</reason>
</decision>

## 2026-08-10T22:24:20+0600
<decision>
  <category>CRO</category>
  <context>Two Belle Vue Hospital doctors (Dr. Shamim Ahmed — diabetes & medicine; Dr. Sharfuddin Mahmood — cardiology) needed sales proposals after flyer research. Owner chose 1C (separate proposal per doctor) and Maestro pricing.</context>
  <action>Shipped two Maestro solo proposals under `docs/proposals/`: `Dr-Shamim-Ahmed-ChamberQ-Proposal` and `Dr-Sharfuddin-Mahmood-ChamberQ-Proposal` (HTML + Markdown each). Same CBPH proposal shell; personalized chambers/sittings (Belle Vue + Labaid / Belle Vue + Shajinaz); sticker ৳15,000 setup / ৳3,000 month; WhatsApp 01818-614349.</action>
  <reason>One doctor = one Maestro story keeps the pitch personal and matches Solo sticker; Clinic-style multi-doctor framing would undersell their individual practice and overcomplicate a first conversation.</reason>
</decision>

## 2026-08-10T22:27:33+0600
<decision>
  <category>CRO</category>
  <context>Belle Vue Maestro proposals needed the competitor comparison chart, and all sales pages must stay portrait (A4) — the existing comparison PDF was landscape letter.</context>
  <action>Embedded the v3 strengths comparison as three portrait A4 pages inside both doctor HTML proposals (before Investment). Also wrote `docs/slides/ChamberQ-Competitor-Comparison-v3-portrait.pdf` and `docs/proposals/build-portrait-comparison.py` to regenerate. Doctor-facing pages omit the internal “gaps” table; the standalone portrait PDF keeps it.</action>
  <reason>Doctors print/share one portrait PDF; landscape charts force sideways pages mid-proposal. Strengths-only in the proposal stays sales-safe; gaps stay available for internal use.</reason>
</decision>

## 2026-08-10T22:31:42+0600

<decision>
  <category>Business_Logic</category>
  <context>Rising Star earlier included live queue on the patient phone ticket (no TV). Owner narrowed the small-doctor lane further.</context>
  <action>Rising Star has **no phone live queue** and **no outdoor/TV queue**. Rising Star is **website (full homepage, no extra pages) + online booking only**, with the **50 bookings created / calendar month** cap. Maestro and Clinic keep phone live queue (and TV where applicable); Clinic remains yes to everything.</action>
  <reason>Keeps Rising Star focused on digital presence and taking serials online without queue-ops setup or support; live queue stays a Maestro/Clinic differentiator.</reason>
</decision>

## 2026-08-10T23:32:04+0600
<decision>
  <category>CRO</category>
  <context>Owner wanted sendable PDFs of the two Belle Vue Maestro proposals, with the portrait comparison chart inside each file — not a separate landscape/portrait slide.</context>
  <action>Printed both HTML proposals to portrait A4 PDFs via Brave headless: `Dr-Shamim-Ahmed-ChamberQ-Proposal.pdf` and `Dr-Sharfuddin-Mahmood-ChamberQ-Proposal.pdf` (14 pages each; comparison on pages 10–12). Regenerator: `docs/proposals/print-doctor-proposals.sh`.</action>
  <reason>One PDF per doctor is what gets WhatsApp’d; comparison must travel with the pitch, upright with the rest of the proposal.</reason>
</decision>

## 2026-08-11T02:29:34+0600
<decision>
  <category>Business_Logic</category>
  <context>Doctors were picking the same brand twice on one prescription, and dose chips showed strengths (e.g. 20 mg) that the selected brand does not carry in the catalogue.</context>
  <action>Prescription repeater dropdown excludes brands already chosen on sibling rows. Dose ToggleButtons resolve from the selected brand's `default_strength` plus Other only; frequency/duration presets unchanged.</action>
  <reason>Matches how real prescribing works — one line per brand, dose from the bottle label — and stops misleading strength chips.</reason>
</decision>

<decision>
  <category>UI/UX</category>
  <context>Mobile Write prescription modal showed a grey lecture under the title; Complete visit and Write prescription were both blue on Consult Screen.</context>
  <action>Removed the Write prescription modal description and the post-save "visit still open" toast body. Complete visit is green on Consult Screen and Live Queue Control.</action>
  <reason>Doctors already know the flow; colour separates "keep writing" (blue) from "finish visit" (green).</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>Clinic websites duplicated departments, blog cards, and doctor bios inside homepage JSON — five departments meant retyping the same card five times.</context>
  <action>Clinic relational CMS: Departments and Blog posts as tenant tables with one list + one detail Blade each; doctor public profiles on `doctors`; homepage `service_matrix` / `health_insights` / `doctor_grid` read published rows. Solo homepage unchanged.</action>
  <reason>One template, many items — like Word styles vs copying paragraphs — and staff edit content once in Website admin.</reason>
</decision>

## 2026-08-11T12:14:33+0600
<decision>
  <category>CRO</category>
  <context>Needed a reusable screen-share / meeting deck for experienced solo doctors (room already full), matching the teal Maestro proposal look — not the older navy Client/Established PowerPoint decks. Owner confirmed: generic leave-behind (not named doctor), slide format, plan name Maestro.</context>
  <action>Shipped `docs/slides/build-maestro-experienced-solo-pitch.js` → `ChamberQ-Maestro-Experienced-Solo-Pitch.pptx` (13 slides): cover, who it’s for, friction, digital front desk, patient day, desk/consult, busy-day flow, Rx home, what we build, kept simple, Maestro investment (৳15,000 / ৳3,000), go-live, WhatsApp close (01818-614349). Teal palette from proposals (`#0C3A3B` / `#0F766E` / `#5EEAD4`). Framing: order and reputation, not “fill your room.”</action>
  <reason>Experienced solos need the Maestro story without Clinic upsell or Rising Star distraction; proposal colours keep sales material on one brand language.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>Marketing and agents still say “Solo” for the full one-doctor plan; proposals and Rising Star split already call that plan Maestro.</context>
  <action>From 2026-08-11, in conversation and new sales material, call the full one-doctor product **Maestro** even when someone says “Solo.” Rising Star stays the limited lane; Clinic stays multi-doctor. Internal `solo` feature-flag / plan key in code is unchanged unless a later rename task migrates it.</action>
  <reason>Stops two names for the same sticker (৳15k / ৳3k) and matches shipped Belle Vue proposals.</reason>
</decision>

## 2026-08-11T13:09:19+0600
<decision>
  <category>UI/UX</category>
  <context>Maestro experienced-solo slide deck used Helvetica Neue for headlines; proposal HTML uses Bebas Neue for display titles and Helvetica Neue for body.</context>
  <action>Rebuilt the deck with embedded Bebas Neue (`docs/proposals/assets/fonts/BebasNeue-Regular.ttf` via `pptx-embed-fonts`) for all headlines; body/eyebrows stay Helvetica Neue. Bebas is Regular-only — no faux bold.</action>
  <reason>Matches the current proposal visual language so screen-share and leave-behind feel like the same brand.</reason>
</decision>

## 2026-08-11T13:35:18+0600
<decision>
  <category>UI/UX</category>
  <context>Maestro deck still did not match proposal typography: Bebas titles were sentence-case, Helvetica Neue was named but not embedded, and the PPTX theme still defaulted to Calibri — Keynote often ignored the Bebas embed.</context>
  <action>Rebuilt with (1) all Bebas display text forced uppercase + tight tracking like proposal CSS, (2) Bebas installed to ~/Library/Fonts for local Keynote, (3) Helvetica Neue Regular/Bold extracted at build time from the macOS TTC and embedded, (4) theme major/minor patched from Calibri to Bebas Neue / Helvetica Neue.</action>
  <reason>Proposals look like proposals because titles are uppercase Bebas; the deck must do the same or it reads as a different brand.</reason>
</decision>

## 2026-08-11T16:03:15+0600

<decision>
 <category>CRO</category>
 <context>Busy doctors can be fully booked for weeks ahead. The wizard only checked the next sitting per schedule; when that day was full the card went grey and patients could not reach later open dates — online booking looked closed when it was not.</context>
 <action>Date-first booking step: `GET /api/bookings/open-dates` + `BookingService::openDatesFor()` scan the next 60 days in bulk (grouped counts, both cap modes) and the wizard shows only real open dates soonest-first (`step-when` replaces `step-session`). Identity step locks the chosen date with **Change date** back-link. Optional **Tell me if an earlier date opens up** sets `bookings.wants_earlier_date`; staff follow up from **Waiting for earlier date** in tenant admin (WhatsApp tap per row, no auto SMS).</action>
  <reason>Patients book by when they can come, not by internal schedule names; skipping full weeks without a dead-end matches how full chambers actually work, and the waiting list captures value when someone cancels without burning SMS credits.</reason>
</decision>

## 2026-08-11T16:26:40+0600

<decision>
 <category>UI/UX</category>
 <context>The date-first step can list many open dates, so Back/Continue fell below the fold. Sticky was phone-only, and the when step had Back with no Continue.</context>
 <action>Sticky `.btn-group` on solo and clinic book shells at all widths. Date step: tap a card to select, then sticky **Continue** (disabled until a date is picked); Back stays beside it.</action>
  <reason>Long open-date lists are exactly when patients need the actions pinned; select-then-Continue lets them change their mind without jumping away.</reason>
</decision>

## 2026-08-11T16:33:43+0600

<decision>
 <category>UI/UX</category>
 <context>Date step felt busy with Back/Continue; details step allowed Confirm before name/phone were ready; some patients use WhatsApp on a different SIM than the reception phone.</context>
 <action>Date step: tap a card to advance — no footer buttons. Details: **Confirm Booking** stays disabled until name (or household pick), a valid BD mobile (`01[3-9]` + 8 digits), and optional WhatsApp (same rule) when **I have a different WhatsApp number** is checked. Store nullable `bookings.whatsapp_phone`; staff WhatsApp links prefer it over `patient_phone`. Format check only — no OTP.</action>
 <reason>Matches how patients actually book (pick a day, then fill details carefully) and keeps staff messaging on the number that actually rings WhatsApp.</reason>
</decision>

## 2026-08-11T20:14:29+0600
<decision>
  <category>UI/UX</category>
  <context>Maestro deck cards mixed white-on-gray, mint-accent fills, and clay number badges — so slides did not feel like one brand next to Section 4 (prescription).</context>
  <action>Unified content cards to Section 4 style: soft teal-gray fill (#F4F7F7), mint-edge border (#D7E3E2), soft shadow, mint markers with ink labels on white slides. Dark investment/close panels unchanged.</action>
  <reason>One card language across the leave-behind matches the proposal “shared card” look and stops the deck reading like several templates glued together.</reason>
</decision>

## 2026-08-11T23:15:08+0600
<decision>
  <category>Code</category>
  <context>Chamber restore upserted every table on the bare primary key across a shared database, then force-wrote `tenant_id` onto whatever row that id already belonged to. `users.id` is a plain auto-increment integer, so a chamber admin could hand in a ZIP reusing another chamber's — or the central Super Admin's — row id and have those rows rewritten into their own chamber. `BelongsToTenant` could not help: these are Query Builder writes and never reach Eloquent's guard.</context>
  <action>`DataImportService::assertPayloadBelongsToScope()` runs before every upsert chunk and **throws** when any incoming primary key already belongs to a different tenant (or, in tenant scope, to a null-tenant central row). `prescription_items` carries no `tenant_id`, so it is checked through its parent prescription in both directions — the row it would overwrite and the prescription it would attach to.</action>
  <reason>Refusing loudly beats skipping quietly: a backup containing somebody else's rows is corrupt or hostile, and a half-applied restore the admin cannot see is worse than a failed one they can. The whole import is one transaction, so the throw rolls everything back.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>"Replace" restore wiped the chamber in its own committed transaction and then imported outside it, so any mid-import failure left the chamber emptied with nothing to roll it back. Separately, `TENANT_TABLES` listed `live_sessions` before `bookings` although `live_sessions.current_booking_id` is a foreign key to `bookings.id` — the list is read forwards to import and reversed to delete, so one wrong order broke both directions.</context>
  <action>Wipe and import now share a single `DB::transaction`. `bookings` moved ahead of `live_sessions` in `TENANT_TABLES`, and the wipe nulls `live_sessions.current_booking_id` for the tenant before deleting anything.</action>
  <reason>A restore must either land completely or change nothing. A chamber left empty because a restore half-ran is the worst outcome this feature can produce, and it is unrecoverable without an off-server backup that does not exist yet.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>`VisitRecordService::saveForCompletedBooking()` gated medicine/condition learning on `$booking->status === 'completed'`. Both completion helpers save the notes while the booking is still `in_chamber` and close it on the next line, so the gate answered "not completed" on the only path a doctor actually presses — `condition_usages` and `medicine_usages` were never written by a real consult. The existing test passed because it called the service directly after setting the status by hand.</context>
  <action>Added an explicit `bool $completingVisit = false` parameter, passed as `true` by `CompleteBookingWithVisitNotes::finish()` and `::completeCurrentSessionPatientWithoutAdvancing()`. The gate is now `$completingVisit || $booking->status === 'completed'`.</action>
  <reason>Intent is passed in rather than inferred, because the mid-consult **Write prescription** button — which must NOT record usage, since the doctor may still change the prescription — is indistinguishable by status from the completion path at the moment of the save. Reordering to "complete first, then save" would have advanced the queue before the notes were written.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>`LiveSessionService::markAbsent()` cancelled every non-terminal booking including the patient who was `in_chamber`, and returned nothing. `endSession()` already completed the mid-consult patient and returned the cancelled bookings so staff got a WhatsApp link per person. The asymmetry meant the *doctor-is-absent* path — where telling patients matters most, because every one of them would otherwise travel for nothing — was the one that told nobody.</context>
  <action>`markAbsent()` now mirrors `endSession()`: completes `in_chamber`, cancels only waiting/called/skipped, clears the session's current-booking pointer, and returns the cancelled bookings. `LiveQueueControl::markAbsentAction()` names the count and the patients in its confirmation and populates `cancelledByEndSessionIds` so the existing **Tell cancelled patients** hand-off appears.</action>
  <reason>A consult that already happened must not be recorded as a cancelled appointment — it discards the visit and any notes written during it. Cancelling silently is the failure the `endSession()` return value was introduced to prevent; the same rule has to hold on both paths.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>`PatientService::mergePatients()` moved `bookings.patient_id` and then deleted the duplicate patient. `visit_records.patient_id` and `prescriptions.patient_id` are `nullOnDelete` foreign keys, so both were silently set to NULL. Consult Screen reads history by `patient_id`, so after a routine staff merge the doctor was told "no history" for a patient whose allergy note was still stored, with nothing pointing at it and no screen able to re-link it.</context>
  <action>`repointPatientOwnedRows()` moves bookings, visit records and prescriptions in one transaction before the delete. `moveBookingToPatient()` likewise carries the booking's visit record and prescription with it. Migration `2026_08_11_130000_relink_orphaned_visit_records_and_prescriptions` repairs rows already orphaned in production.</action>
  <reason>The backfill is deterministic, not guesswork: a visit record knows its booking and the old merge moved `bookings.patient_id` correctly, so the link is rebuilt from data that is already right. Only rows with `patient_id IS NULL` are touched.</reason>
</decision>

## 2026-08-11T23:24:42+0600
<decision>
  <category>UI/UX</category>
  <context>The prescription is written in a Filament action modal (`writePrescription` / `completeVisit`) at the default `Width::FourExtraLarge` — a single-column stack of ten sections. On a desktop monitor the doctor scrolls past Vitals, Diagnosis, Clinical notes, Advice, Tests, Reports, Follow-up, Voice and Photo to reach anything, and the overlay hides the left column of Consult Screen — Last visit and Past visits — which is exactly what a doctor consults while prescribing. "Copy from last visit" is therefore a blind button: it passes the items as a JS payload and never shows which medicines they are.</context>
  <action>At ≥1024px on Consult Screen, while a patient is `in_chamber`, the prescription becomes the page rather than a dialog over it: a sticky patient bar (identity, age/sex, visit count, allergy pill, inline weight/BP, Complete), then a 34/66 split — clinical column left (C/C, H/O, O/E, Dx, Inv, plus "you usually prescribe" and last-visit repeat), Rx table right, with Advice and Follow-up as a strip beneath it. Voice, photo and reports collapse to an icon row. The page is set to `MaxWidth::Full`; Filament's default caps content well below the viewport. Below 1024px, and on Daily Roster, Live Queue Control and staff prescription entry, the existing modal is unchanged.</action>
  <reason>Desktop is where doctors write; the modal was a mobile-first shape applied to a screen with room to spare. Scoping the new pad to ≥1024px on one page contains the blast radius — no existing flow changes shape, and the sticky-bar / header-actions breakpoint pairing recorded in `bug_history.md` stays untouched. The owner chose this over keeping the current two-column layout and confining the pad to the right half: history you glance at is worth less than drug rows you type into, and the last-visit repeat line covers the common case.</reason>
</decision>

<decision>
  <category>UI/UX</category>
  <context>Each medicine is a Filament repeater card stacking brand, generic, dose chips, dose-other, frequency chips, frequency-other, duration chips and duration-other — roughly 300px of vertical space per drug. Two `bug_history.md` entries exist solely to fight that height: auto-collapsing finished rows, and scrolling the newly added row back into view. Every one of those fields is `->live()`, so each chip tap is a server round trip that rebuilds the whole options array for every row.</context>
  <action>Medicines become a real table — one ~44px row per drug: index, medicine (brand on line one, generic and indication on line two), frequency, duration, timing, reorder handle, remove. The chips are not deleted; they become the *edit* state of a cell, shown on focus. A shorthand row sits at the bottom (`seclo 20 1+0+1 7d ac`, Enter to commit). Client-side state in one Alpine component, posted once on save, replacing the per-field Livewire round trips.</action>
  <reason>Eight rows fit the vertical budget that one card consumes today, which makes the collapse and scroll-into-view hacks unnecessary rather than merely better-tuned. Removing `->live()` from the hot path removes both the latency and the per-row catalogue rebuild in the same change. Shorthand is table stakes in this market, not an innovation — a direct Bangladeshi competitor already ships `1+0+1`, `5d`, `af`.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>`visit_records` carries one `clinical_notes` blob, captioned "whatever you would write on the left of a paper pad" — an accurate admission that the structure of a Bangladeshi prescription was skipped. `prescription_items` has no indication and no timing field, so "after food" — on essentially every prescription in the country, and the instruction patients most often get wrong — is unrepresentable and must be jammed into `duration` or dropped. `condition_usages` and `medicine_usages` learn independently; neither records what was prescribed *for* what.</context>
  <action>Structure the record to match the pad: `visit_records` gains `chief_complaint`, `history`, `on_examination` (`clinical_notes` retained for existing rows); `prescription_items` gains `indication`, `timing`, `instructions`; `medicines` gains `indications`; a condition↔medicine co-occurrence dimension is added and backfilled from completed visits; `prescription_templates` + items are added. `timing` is stored as a key from a closed vocabulary (after food, before food, empty stomach, at night, with food), never as free text.</action>
  <reason>You cannot automate off a blob. Structuring the left column is the prerequisite for diagnosis-predicts-prescription, complaint-predicts-indication and any safety check — not a separate nicety. The co-occurrence backfill needs no external data: every completed visit with a `condition_id` and a prescription is already a training row. Everything proposes and nothing commits — auto-filled values render as auto-filled and clear in one keystroke, extending the existing `_prefilled` / `medicine-prefill-hint` pattern.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>Owner decision, 2026-08-11: dose timing must print in Bangla, with English kept as well. `App\Support\Bilingual` already renders fixed labels in both languages with the tenant's locale leading, and its docblock restricts that treatment to fixed labels — anything a human typed is stored in one language and passed through untouched.</context>
  <action>Because `timing` is stored as a key from a closed vocabulary rather than free text, it qualifies as a fixed label and renders through `bilingual()` on the printed sheet — "After food / খাবারের পর" — alongside the existing bilingual headings. No parallel translation mechanism, and no per-doctor language toggle unless one is asked for later.</action>
  <reason>Reusing the documented mechanism keeps one rule about what may be translated, instead of two that will drift. It also satisfies both halves of the request at once — the patient's family reads the Bangla, the pharmacist reads the English — which is the same reason the print sheet is bilingual today. This is the argument for storing timing as a key: free text could not have been translated at all.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>Competitive review of the Indian and Bangladeshi market (HealthPlix, Eka Care, Doctors Canvas, PrescribeRx, ProtonEMR, Prescriber/MedicBD) plus the global ambient-scribe frontier. Two gaps outrank the pad redesign. The medicine catalogue is 460 CSV rows against a market where a competitor advertises 200,000 drugs, and `bug_history.md` already records both a specialist unable to find their own brands and a production launch with an empty `medicines` table. Separately, a direct Bangladeshi competitor is offline-first on RxDB, citing load shedding and unstable connections; ChamberQ is Livewire round trips end to end, so the pad stops working when the chamber's connection drops.</context>
  <action>Build order: (1) catalogue depth, and prefill from the doctor's own `MedicineUsage` last dose/frequency/duration instead of the hardcoded `'1+1+1'` / `'5 days'`, wiring the picker to the already-written but entirely unconsumed `MedicineService::search()` — `/api/medicines/search` and `/api/conditions/search` have no front-end callers; (2) the desktop Rx pad above; (3) structured C/C, indication and timing; (4) templates and shorthand; (5) duplicate-generic and allergy checks at point of prescribing; (6) offline resilience; (7) voice-to-structured-draft. Multi-language beyond Bangla and English is explicitly out of scope — the owner ruled it unnecessary for this market. Drug-drug interaction checking requires a licensed database and will be bought or skipped, never hand-rolled.</action>
  <reason>A doctor who cannot find their brand on the second patient abandons the tool, and no pad redesign survives that — so depth precedes polish. Stage 1 is also the smallest change with the largest per-drug saving and removes the per-row catalogue rebuild, so it pays for itself before any UI moves. The learning data is already being collected and then ignored at the one moment it would matter, which makes it the cheapest win available.</reason>
</decision>

## 2026-08-11T23:29:27+0600
<decision>
  <category>Business_Logic</category>
  <context>Refines the stage 1 build order agreed earlier today. Prefilling from the doctor's own `MedicineUsage` was going to *replace* the catalogue as the source of prescription defaults. The owner corrected this: prefill must learn from the doctor but still come from the database. That exposed a gap — there is no database default for frequency or duration at all. `MedicinePickerFields::prescriptionMedicineSelect()` hardcodes `'1+1+1'` and `'5 days'` in PHP, and `data/medicine-list-draft.csv` has no such columns, so the only per-drug fact the catalogue can currently supply is `default_strength`.</context>
  <action>Prefill resolves in three layers, stopping at the first hit: (1) the prescribing doctor's own history for that exact brand, (2) the catalogue row — `default_strength` plus new `default_frequency`, `default_duration` and `default_timing` columns on `medicines`, populated in the CSV, (3) blank. A hardcoded literal is never layer 3; a field with no catalogue default and no history is left empty for the doctor to fill.</action>
  <reason>The catalogue is the floor and the doctor's habit is the refinement, so a doctor who has never prescribed a brand still gets a sensible starting point and a doctor who has gets their own. This is also a clinical correctness fix rather than plumbing: `'1+1+1'` is wrong for most drugs — a PPI is `1+0+0` before food, an antihistamine `0+0+1` at night — so a single global literal was mis-prefilling every drug it did not happen to suit. Leaving layer 3 blank rather than guessing keeps the existing principle that auto-filled values must be defensible; an empty box is honest, a wrong default gets signed.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>`MedicineUsage` records only `last_dose`, `last_frequency` and `last_duration`, overwritten on every save. "Learning from the doctor" against a single last-value field means one atypical prescription permanently replaces the doctor's habitual pattern — a doctor who always writes Napa `1+0+1 · 5 days` but once writes `1+1+1 · 3 days` for a particular patient is prefilled with the outlier from then on, silently, on every subsequent patient.</context>
  <action>Learning uses the most common value per doctor per brand, not the most recent: keep a small per-value tally alongside the existing counters and prefill the mode, falling back to `last_*` only to break a tie. `last_used_at` and `use_count` keep their present roles in search ranking.</action>
  <reason>A prefill is a claim about what this doctor usually does, and "usually" is the mode, not the last sample. The failure mode of last-value learning is silent and compounding — it looks correct on the day it is set and is never obviously wrong afterwards — which makes it exactly the kind of thing that must be designed out rather than noticed later. Note for whoever builds this: free-text brands added via `createOptionUsing` have no catalogue row, so layer 2 does not exist for them and the doctor's history is the only source. That set of hand-added brands is also the best available signal of catalogue gaps and should drive stage 1 expansion.</reason>
</decision>

## 2026-08-11T23:44:17+0600
<decision>
  <category>Business_Logic</category>
  <context>The app watched consultations and built a per-doctor profile from them: every completed visit bumped `medicine_usages` and `condition_usages`, and both pickers were ranked by those counters. Owner decision: doctors already curate their own shortlist in **My medicines**, so there is no reason for the app to infer one. **Supersedes the `completingVisit` decision recorded earlier today** — that fix made learning fire correctly on the real completion path; learning itself is now gone, so the parameter went with it.</context>
  <action>Removed `VisitRecordService::recordUsagesFromSubmission()` and the `completingVisit` parameter; removed `ConditionService::recordUsage()` and its `usageBoostMap()`; removed `MedicineService::usageBoostMap()`. `MedicineService::recordUsage()` became `saveDoctorMedicine()` — called only from My medicines, and no longer touching `use_count` / `last_used_at`. Both the personal list and the diagnosis picker now order deterministically: My medicines A–Z, conditions by text-match score.</action>
  <reason>A curated list a doctor can see and edit beats a silent one that reorders itself from behaviour they cannot inspect — and prescribing habits are exactly the kind of derived profile a clinical system should not accumulate without being asked. The doctor's own saved entries still rank above the shared catalogue on an equal match, because that is their explicit choice showing through, not inference.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>With learning gone, `condition_usages` has no writer at all and `medicine_usages.use_count` / `last_used_at` are vestigial. Dropping either is irreversible on live clinical data.</context>
  <action>No destructive migration. All reading and writing code was removed; the tables and columns stay as they are, still included in chamber backups. `ConditionUsage` keeps its model with a docblock stating it is retired and must not be re-pointed at without an owner decision.</action>
  <reason>Removing the code is fully reversible; dropping the table is not. Historical rows cost nothing to keep and are the only record of what was learned before the switch. Schema cleanup can happen later as a deliberate, separate step.</reason>
</decision>

## 2026-08-12T00:01:07+0600
<decision>
  <category>Business_Logic</category>
  <context>**Supersedes the 2026-08-10 curated-catalogue decision.** That entry chose ~460 verified-enough brands and explicitly rejected "a 24k MedEx dump", citing licensing risk, prescribing UX, patient safety (MedEx multi-form ambiguity — `ACE IV` vs `ACE 500 mg tablet`) and keeping third-party data out of the repo. The conflict was raised with the owner before any change; the owner reaffirmed — include the data regardless of the previous decision, on one condition: "as long as drug data is proven somehow". Also corrected in the same conversation: the two `bug_history.md` entries previously cited as evidence that 460 was too small are nothing of the kind — one was `resolvePrescribingDoctor()` returning null for clinic specialists, the other a production server where `medicines:load` was never run. Both were already fixed and neither concerned catalogue size.</context>
  <action>The catalogue is now the full Bangladesh market: 24,491 SKUs across 16,029 brands, from **BDDrugBank v1.0.0** (Zenodo, DOI 10.5281/zenodo.20749707), which is **CC BY 4.0** and therefore redistributable with attribution — `data/ATTRIBUTION.md` carries it, as the licence requires. "Proven" is served two ways: the source is a citable academic deposit rather than a scrape, and `is_essential` is set from the Bangladesh NEML 2016 (597 generics) and WHO EML 2025 (642 generics), both shipped inside the same deposit. The hand-reviewed 460 is preserved verbatim as `data/medicine-curated-seed.csv` and **overrides the source wherever the two disagree**.</action>
  <reason>The three objections the CC BY licence does not answer are addressed rather than ignored. **Dropdown UX**: the picker no longer holds a static option list at all — it is `getSearchResultsUsing()` over `MedicineService::search()`, capped at 20 ranked results, measured at 5–50 ms and one query. **Multi-form ambiguity**: safety moved from exclusion to ranking. Five priority tiers, with `tierBoost()` spreading 32 down to 0 — deliberately wider than the gap between a prefix and a substring text match — so a hand-verified tablet outranks an IV infusion of the same brand that matches the needle equally well. Nothing is hidden; only the order changes. **Repo hygiene**: the 3.8 MB derived CSV is committed, the 77 MB source archive is not, and the build script downloads it to `/tmp` on demand.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>Keying the catalogue on `brand_name` alone — as the loader had always done via `updateOrCreate(['brand_name' => …])` — collapsed 24,491 source SKUs to 16,029 rows, silently discarding 8,656. The loss was not evenly spread: NAPA alone ships a 500 mg tablet, a 120 mg/5 ml syrup, 80 mg/ml paediatric drops, three suppository strengths and an IV infusion, and whichever row the CSV happened to list first won. Measured against the old curated set, syrups were 15 rows and paediatric drops 10 — the single biggest real gap in the catalogue, and precisely what brand-only keying would have thrown away again.</context>
  <action>One catalogue row per **brand + strength + form**. `medicines.brand_name` was already indexed rather than unique, so no constraint had to change; the loader upserts on the triple and `medicines_sku_index` covers it. `Medicine::displayLabel()` now includes the form, because a 500 mg tablet and a 500 mg suppository of the same brand were otherwise the same string. The picker still offers **one entry per brand** (search dedupes on brand name), so the choice between a brand's forms lives on the dose chips — `doseOptionsForBrand()` lists every strength the brand ships in, each labelled with its form, tier-ordered so the verified adult strength leads.</action>
  <reason>Syrups went from 15 to 2,743, drops from 10 to 1,237, inhalers from 1 to 168, distinct generics from 141 to 1,578. That — not the row count — was the actual deficiency: 362 of the old 460 rows were tablets, so a GP seeing a child had essentially nothing to prescribe. Putting the form choice on the dose chips rather than in the picker keeps the picker short while leaving the paediatric syrup one keystroke away instead of unreachable; `MedicinePickerTest::test_dose_options_offer_every_form_a_brand_ships_in` fails if that regresses, because the failure mode is silent — the doctor simply free-texts it and nobody learns.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>The BDDrugBank deposit also ships `interaction_edges.csv` — 3,310 directed drug–drug interaction edges across 983 generics — which would be the cheapest possible route to the interaction checking that HealthPlix, Eka Care and Doctors Canvas all advertise.</context>
  <action>Deliberately **not** imported. Recorded here so the next person does not find the file and assume it was an oversight.</action>
  <reason>Those edges are text-mined from the `interaction` free-text field of manufacturer marketing copy. They carry no severity grading, no mechanism, and no evidence level, and their recall depends on how a given company chose to write its label. A warning a doctor learns to dismiss is worse than no warning, and a *missing* warning presented by a system that claims to check interactions is worse still — the doctor stops checking themselves. Interaction checking needs a licensed clinical database (or DDInter 2.0, which does carry severity); until there is one, the product should not imply it has one.</reason>
</decision>

## 2026-08-12T00:42:21+0600
<decision>
  <category>UI/UX</category>
  <context>Owner confirmed Option B (full Rx desk) for the next build, implementing the already-logged 2026-08-11 desktop pad decisions that were not yet in code.</context>
  <action>Shipped the desktop Consult Screen Rx pad at ≥1024px while a patient is `in_chamber`: sticky bar, 34/66 C/C·H/O·O/E·Dx·Inv / medicine table, Alpine shorthand + one-shot `saveRxDesk`, schema columns for pad fields and item timing/indication/instructions, bilingual timing on print and share drug lines. Modal path kept for phones and other screens.</action>
  <reason>Matches the paper pad doctors already know; contains blast radius to one screen and one breakpoint so Daily Roster / Live Queue / staff entry stay stable.</reason>
</decision>

## 2026-08-12T00:50:43+0600
<decision>
  <category>UI/UX</category>
  <context>Tenant admin sidebar reused the same rectangle-stack icon for Chambers, Schedule Sessions, Slot Blocks (and other resources), so items looked identical; the expanded labels also ate desk space during consult days.</context>
  <action>Gave each nav item a unique related Heroicon (map pin / calendar days / no-symbol for the three that collided; likewise Doctors, Lab tests, Lab collection slots, Web pages). Enabled Filament `sidebarCollapsibleOnDesktop()` and seed the Alpine sidebar store closed on load so desktop shows icons only, with the item name on hover.</action>
  <reason>Doctors scan the rail by shape during a busy sitting; identical icons force reading every label. Icon-only with hover names keeps the rail thin without hiding where things are.</reason>
</decision>

## 2026-08-12T01:47:03+0600
<decision>
  <category>Business_Logic</category>
  <context>The Rx pad was structurally right but still made the doctor type everything. Its own prefill was two literals hardcoded in PHP — `1+1+1` and `5 days` — applied to every drug alike, which is wrong for a PPI, an antihistamine and anything taken long term. No drug database sells the missing piece: BDDrugBank is a product list, and even licensed clinical databases leave the dose to the prescriber, which is why Zilsoft and ProtonEMR ship a per-doctor "save as my default" and nothing more. Owner's instruction was explicit: "fuck doctor approval. ship something that makes them want to buy it, as long as it is harmless."</context>
  <action>Three-layer prefill — the doctor's own saved default, then a per-drug catalogue default, then blank — resolved field by field inside `MedicineService::search()` so the Alpine pad and the Filament modal cannot drift. Layer 2 ships **on by default** as 171 hand-written generic dosing rows (`data/dosing-defaults.csv`, reaching 9,862 SKUs), with a `hold` column to veto a row rather than an approval gate to unlock one. Both hardcoded literals deleted.</action>
  <reason>Shipping the defaults off-by-default would have meant every new chamber gets the blank version of the product and judges it on that. It is defensible because it is strictly safer than what it replaced: a class-appropriate default beats one literal for all 24k SKUs, it only fills a row the doctor is already writing, every cell stays editable, and the doctor signs the document. Blank remains a real third layer — a guess is never substituted for "nothing knows".</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>"Automate the prescription" could mean anything from filling a duration to proposing a drug regimen for a diagnosis. The line had to be drawn once, in code, rather than re-argued per feature.</context>
  <action>The product ships **advice and investigations** per diagnosis (58 conditions, English + Bangla, `data/condition-presets.csv`) but **never ships a medicine**. Drug sets attached to a diagnosis exist only as packs the doctor saves from a prescription he wrote himself. Enforced by `RxAutomationTest::test_shipped_presets_never_carry_a_medicine` and stated at the top of both CSVs.</action>
  <reason>"Drink plenty of water, avoid spicy food" is a thing the doctor says out loud anyway, and printing it in Bangla helps the patient more than it helps him. A drug proposed for a diagnosis is a clinical recommendation, and a product that makes those needs a liability position, a clinical author and a licensed source — none of which exist here. The same instinct as the 2026-08-11 refusal to import BDDrugBank's text-mined interaction edges.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>The pad needed to fill the left column too, but the 2026-08-11 "no learning from consultations" decision rules out deriving anything from what the doctor prescribes or diagnoses.</context>
  <action>Fill only from data the chamber already holds and can point at: H/O seeded from the patient's stored conditions/medicines, last visit offered as explicit one-tap chips (Same medicines / Dx / Inv / advice), C/C from a fixed chip list. **Vitals are never pre-filled** — last visit's weight and BP appear as grey reference beside the boxes instead. "Save as my default" (★ on a row) writes through the existing `saveDoctorMedicine()` into My medicines.</action>
  <reason>Every one of these is data the doctor can see the source of and correct, which is what separates it from inference. Vitals are the exception because they are a measurement taken today: carrying a number forward would put a reading the doctor never took onto a document he signs. The ★ keeps My medicines the single visible, editable home of a doctor's shortlist — it adds a door, not a second writer.</reason>
</decision>

## 2026-08-12T01:59:32+0600
<decision>
  <category>UI/UX</category>
  <context>C/C started as chips appending into one textarea, with one shared duration on the whole line. That is not how ZilSoft-style pads work, and it made Fever for 3 days and Cough for 1 week awkward (and briefly buggy when duration was attached).</context>
  <action>C/C on the desktop Rx desk is now a row list: each chip adds a complaint row with its own duration chips; a second tap on the same complaint is a no-op; free text still works via an Enter line. Stored as plain text, one complaint per line (`Fever — 3 days`), via ComplaintChips::format/parse so print and the phone modal need no schema change.</action>
  <reason>Matches the paper/ZilSoft mental model doctors already know — one complaint, one duration — without inventing a new database table for a text field that print already shows.</reason>
</decision>

## 2026-08-12T02:12:16+0600
<decision>
  <category>UI/UX</category>
  <context>The live Rx desk worked but looked busier and less paper-like than the Option B mockup the owner preferred — stacked chips, vitals in the sticky bar, one Save button, and Investigations as a textarea.</context>
  <action>Restyle the desktop pad toward the mockup: sticky bar actions become Preview / Save & print / Save only / Complete visit; C/C is a mini-table with duration dropdowns; H/O uses on/off toggles; O/E is a vitals table including Pulse and SpO₂ (new nullable columns); Inv is a clean list with a chip picker. saveRxDesk returns the print URL so Preview and Save & print can open it in one trip.</action>
  <reason>Doctors already know that paper/ZilSoft layout; matching it makes the product feel familiar without changing clinical rules (vitals still never auto-fill; packs and presets still explicit taps).</reason>
</decision>

## 2026-08-12T12:51:30+0600

<decision>
  <category>Business_Logic</category>
  <context>Follow-up dates were captured on visit records but patients were never reminded. Owner chose 3 days before, message template B, SMS auto-send when the doctor's follow-up SMS toggle is on, and WhatsApp as a staff-confirm queue (never auto-send). Letterhead upload was explicitly declined.</context>
  <action>`Doctor::NOTIFY_FOLLOW_UP` with defaults SMS on / WhatsApp off. `follow-ups:send-reminders` runs daily; `FollowUpReminderService` finds visits with `follow_up_date` = today + 3, auto-SMS through `SmsService` (1 credit, idempotent via `follow_up_reminder_sms_sent_at`), queues WhatsApp rows and notifies staff (or admin/doctor if no staff). **Operations → Follow-up reminders** lists pending WhatsApp confirms. Allergy and duplicate-generic checks on the Rx pad warn only — never block save.</action>
  <reason>Matches how chambers actually work: SMS can run unattended; WhatsApp stays human-tapped like every other outbound channel here. Warn-only safety respects that a doctor may override with good reason.</reason>
</decision>

## 2026-08-12T13:09:34+0600

<decision>
 <category>UI/UX</category>
 <context>Operational Reports filter bar felt sparse (dropdown + date floating left, period text stranded on the right), and the status breakdown used cramped Filament badges that did not match the KPI cards above.</context>
 <action>Polish only those two blocks in `operational-reports.blade.php`: replace Period select with Day/Week/Month segmented tabs plus a tinted "Showing" summary chip for the resolved date range; restyle status chips as mini KPI cards (icon, label, large number, left accent) with a 7-up desktop grid. Extended `getStatusMeta()` with accent CSS variables. KPI row and day/week/month tables unchanged.</action>
 <reason>Staff glance the same numbers faster — period choice is one tap, and status counts now scan like the headline cards — without changing report logic or duplicating totals.</reason>
</decision>

## 2026-08-12T13:12:00+0600

<decision>
 <category>UI/UX</category>
 <context>After polishing filters and status chips, the four KPI cards and the Status breakdown still sat as two separate white boxes — same day story told twice with a gap between them.</context>
 <action>Merged KPIs and status chips into one `ops-summary` panel: headline cards on top, a light divider, then Status breakdown underneath. Numbers and report logic unchanged.</action>
 <reason>One glance answers “how was the day?” then “where did each booking land?” without jumping between two modules.</reason>
</decision>

## 2026-08-12T13:20:29+0600

<decision>
 <category>UI/UX</category>
 <context>Stacking four KPI cards above a seven-chip Status breakdown still felt like two modules, and Completed appeared twice.</context>
 <action>Replace both blocks with one 3×3 grid: Total bookings, Completed, Still in queue, Waiting, Called, In chamber, Skipped, No-show, Cancelled. Dropped the Needs attention card and Status breakdown heading. Scoped `.ops-grid` CSS (phone 1-col, tablet 2-col, desktop 3×3); removed the Operational Reports `card-grid.css` link.</action>
 <reason>Nine cells, each number once — staff see the day as one picture. Queue and problem detail are visible without a second section repeating Completed.</reason>
</decision>

## 2026-08-12T13:25:02+0600

<decision>
 <category>UI/UX</category>
 <context>Called, In chamber, and Skipped on the Operational Reports headline grid were mid-flow / niche outcomes; Still in queue already covers the live ones, and staff did not need three extra cards to judge the day.</context>
 <action>Remove those three from the summary grid. Grid is now Total, Completed, Still in queue, Waiting, No-show, Cancelled (3×2 on desktop). Week/month breakdown tables and the day booking list still show full statuses including called / in_chamber / skipped.</action>
 <reason>End-of-day glance should answer booked / finished / still here / waiting / no-show / cancelled — not duplicate Live Queue Control states.</reason>
</decision>

## 2026-08-12T13:37:00+0600
<decision>
  <category>Business_Logic</category>
  <context>Competitors advertise drug-clash warnings and we have none. Before costing one, two questions had to be answered: which interaction database is usable, and whether our own drug names can be matched to any of them. **Correction to earlier advice in the same conversation: DDInter 2.0 was twice recommended here as the free option with severity grading. Its licence is CC BY-NC-SA 4.0 — the NonCommercial clause rules it out for a product that is sold, and ShareAlike would force any derivative to carry the same terms.** The owner also pushed back on every option initially offered on the grounds that they were all American, which turned out to be correct in a measurable way rather than only a cultural one.</context>
  <action>Built `drugs:coverage-report` — a read-only measurement, not a feature — and ran it over all 24,486 catalogue rows. It splits combination generics, strips salt words, applies a small alias map for British spellings, and resolves each ingredient against RxNorm. Result: **92.9% of rows fully checkable**, 1.6% partly, 5.5% not at all. Of the misses, 1,190 rows are devices, supplements and ORS that cannot interact; **906 rows (3.7%) are real medicines with no RxNorm entry under any spelling** — doxophylline, rupatadine, bilastine, mirogabalin, roxadustat, cilnidipine, favipiravir, dexibuprofen, omarigliptin. No interaction feature was built.</action>
  <reason>The failure mode decides the design: an unmatched drug produces no warning, and a doctor seeing no warning concludes there is nothing to worry about — so a checker that silently skips drugs is worse than none, because it replaces the doctor's own caution with false confidence. 92.9% is workable but only with the screen stating what it could **not** check. The 3.7% blind spot is structural rather than a cleaning task: those drugs are not FDA-approved, are ordinary prescriptions in a Bangladeshi chamber, and will be missing from any US-derived database. That is the evidence for anchoring future content on the Bangladesh National Formulary and a short doctor-approved pair list rather than a bulk Western import — which would under-warn precisely on the drugs that distinguish this market. Cheap spelling fixes were worth making (`levofloxacin hemihydrate`, `clopidogrel bisulphate`, `b12`, `guaiphenasine` all resolve once normalised, +1.5 points); guessing at the rest was not.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>`ac` and `pc` — the two Latin abbreviations doctors actually type — were mapped backwards in both `App\Support\PrescriptionTiming::shorthand()` and the Alpine `timingShorthand` map in `rx-desk.blade.php`. `ac` (ante cibum, before food) produced "After food" and `pc` (post cibum, after food) produced "Before food". Found while auditing competitor gaps, not by a test.</context>
  <action>Recorded here as an open defect. Not yet fixed at the time of writing — flagged to the owner as the first thing to correct.</action>
</decision>

## 2026-08-12T13:40:40+0600
<decision>
  <category>CRO</category>
  <context>Belle Vue Maestro proposals (Dr. Shamim Ahmed, Dr. Sharfuddin Mahmood) were 11 pages — too long for doctors who only skim on WhatsApp. Owner locked cover (page 1), full competitor comparison, and investment + close as must-keep; allowed tightening price/close copy. Hard ceiling: 6 pages max.</context>
  <action>Collapsed old pages 2–8 into two middle pages in both `Dr-Shamim-Ahmed-ChamberQ-Proposal` and `Dr-Sharfuddin-Mahmood-ChamberQ-Proposal` (HTML + MD + regenerated PDF via `print-doctor-proposals.sh`). New map: (1) cover unchanged, (2) “Your chamber with ChamberQ” — short intro + five outcomes, (3) “A day at your chamber” — patient path + desk loop + Rx home + soft-launch line, (4) comparison unchanged, (5) investment tightened (price-first, 4 bullets, one-line SMS), (6) close tightened (3-step go-live, WhatsApp → walkthrough → lock sittings). Dropped mid-doc letter sign-off, patient story essay, desk/consult table, setup checklist, and separate “kept simple” page.</action>
  <reason>Busy doctors glance; 11 pages read as a brochure. Cover + comparison + price + WhatsApp close carry the sale; the long walkthrough belongs in the Maestro slide deck and live demo, not the leave-behind PDF.</reason>
</decision>

## 2026-08-12T13:45:14+0600
<decision>
  <category>Business_Logic</category>
  <context>The duplicate-generic and allergy checks exist twice: `App\Support\RxSafety` (covered by `RxSafetyTest`, reached from the phone modal through `rx-safety-warnings.blade.php`) and a hand-written Alpine copy in `rx-desk.blade.php::safetyWarnings()` that no test exercises. The desktop pad — the surface a doctor actually uses at a desk — ran only the untested copy, and `ConsultScreen::saveRxDesk()` never re-checked. The two already disagree: the PHP version `break`s at the first allergy token matching a medicine while the JavaScript reports every matching token, and the duplicate-generic rule dedupes raw names in PHP and normalised names in JavaScript. Both differences are cosmetic today, which is precisely how a real gap would start.</context>
  <action>`saveRxDesk()` now calls `RxSafety::allWarnings()` on the submitted payload and raises a persistent warning notification listing anything found. The Alpine copy is untouched — it still gives instant feedback with no round trip, which is why it exists — but the server is now authoritative at the one point every desk save passes through. `DesktopRxPadTest::test_the_server_re_checks_rx_safety_even_if_the_pad_sends_a_clashing_prescription` calls the Livewire method directly, bypassing the client checks entirely, and asserts the duplicate-generic rule still fires.</action>
  <reason>Two implementations of one clinical rule cannot be kept in step by convention — `CLAUDE.md` says to put the guard where the code converges, and `bug_history.md` already records three cases in this repo where two plausible-looking spellings of the same wiring silently disagreed. Re-checking server-side means a change to the JavaScript, a client-side error, or a hand-crafted payload cannot remove a warning, without giving up the responsiveness the pad was built for. It warns **after** the save and never blocks: a doctor may have a good reason to prescribe two brands of one generic, and notes have never been allowed to hold up the queue. The warning is `persistent()` because a safety note that fades in four seconds while the doctor is looking at the patient has not been delivered.</reason>
</decision>

## 2026-08-12T14:04:53+0600
<decision>
  <category>Business_Logic</category>
  <context>Step 2 of the approved interaction plan, after `drugs:coverage-report` measured 92.9% of the catalogue as checkable. The available databases were all unusable or unsuitable: DDInter is non-commercial, FDB/Medi-Span/DrugBank are five figures a year and sold through sales calls, and the measurement showed 3.7% of our catalogue — doxophylline, bilastine, rupatadine, cilnidipine, roxadustat and others — has no entry in any US-derived vocabulary at all, so an import would have gone silent on precisely the drugs that distinguish this market.</context>
  <action>A hand-built list instead: `data/build-drug-interactions.py` expands 22 clinical rules into **221 explicit ingredient pairs**, restricted to drugs verified present in the catalogue (49 of 50 candidates). Stored in `drug_interactions` with severity `avoid` or `serious`, effect, action and mechanism, keyed alphabetically so lookup ignores typing order. `RxSafety::interactionWarnings()` matches on ingredients via the new shared `App\Support\DrugIngredients`, which splits combination generics and strips salts — so `Warfarin Sodium` + `Diclofenac Sodium` still meets the `warfarin`/`diclofenac` pair. Warn only; never blocks save. Both entry points get it: the modal renders `RxSafety` directly, and `saveRxDesk()` re-runs it server-side.</action>
  <reason>Explicit pairs rather than class rules because a doctor reviewing this reads lines, not logic. 221 rows expanded from 22 rules is what a reviewer actually has to judge — 22 decisions, not 221. `RxSafety::uncheckedMedicines()` exists because the failure mode is the whole point: a line with no generic name produces no warning, and silence would let a doctor read "no warning" as "checked and clear" for a drug never examined, so those lines are named as plainly as the clashes. A combination product whose two ingredients both appear in one pair is skipped — that is one product, not two drugs prescribed together. Metformin + iodinated contrast was dropped despite being a real interaction: contrast is given in radiology, never written on a chamber prescription, so it could only pad a list someone has to read.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>The pair list is clinical content generated from general pharmacology by an AI agent, not taken from a licensed clinical database. The approved plan required a named doctor to sign it off before it is relied on. The owner asked to proceed with the build.</context>
  <action>Built and shipped, warning doctors from the moment it loads — but `drug_interactions.reviewed_at` and `reviewed_by` are NULL for all 221 rows, `DrugInteraction::isReviewed()` reports that state, and `interactions:load` prints "This list is a DRAFT. It warns doctors but has not been clinically approved" on every single run.</action>
  <reason>The sign-off requirement is carried as data and as a loud message rather than quietly dropped, because the alternative — shipping clinical warnings that look authoritative with nothing behind them — is the thing the plan was written to prevent. A draft warning about warfarin and ibuprofen is still worth showing, so the list is not disabled; but nothing in the product should describe this as clinically approved until a name is recorded against it. Whoever reviews it should check the 22 rules in `data/build-drug-interactions.py`, not the 221 generated rows.</reason>
</decision>

## 2026-08-12T14:12:07+0600
<decision>
  <category>Business_Logic</category>
  <context>**Supersedes the sign-off decision appended earlier the same day.** That entry held `drug_interactions.reviewed_at` / `reviewed_by` NULL and had `interactions:load` demand a named doctor before the pair list could be relied on. The owner ruled it out flatly: no doctor's name is to be used anywhere in the product, at any time.</context>
  <action>`reviewed_by` dropped by migration and removed from `DrugInteraction`; `isReviewed()` now answers only "has this been checked", never by whom. `reviewed_at` survives without a name. In its place, `RxSafety::DISCLAIMER` — "Reference only, and not a complete list — use your own judgement" — renders beside every warning on **both** surfaces, the Blade partial the modal uses and the Alpine block on the desktop pad, and is included in the server-side notification from `saveRxDesk()`. `RxSafetyTest::test_every_surface_that_shows_a_warning_also_shows_the_disclaimer` fails if either display stops showing it. `interactions:load` now states plainly that this is a reference list rather than a licensed clinical database, and that every warning must carry the disclaimer.</action>
  <reason>The owner's position is sound on its own terms, and stronger than what it replaced. Naming one clinician makes that individual personally answerable for a list the practice ships and profits from; no drug reference works that way — the publisher carries it, not a named reviewer. And the practical effect is better: a name in a column nobody reads protected nobody, while a line the doctor sees on every warning does the actual work of saying how much weight to give it. What must not be lost is the honesty the signature was standing in for — this list is compiled from general pharmacology, has had no clinical review, and is knowingly incomplete (221 pairs, and 3.7% of the catalogue is drugs absent from every vocabulary we could check). The disclaimer is now the only thing keeping the product's claim matched to its backing, which is why it is enforced by a test rather than left to whoever edits the templates next. Anyone adding a third place that shows warnings must extend that test.</reason>
</decision>

## 2026-08-12T14:27:41+0600
<decision>
  <category>UI/UX</category>
  <context>The amber "patients today without notes" banner on Consult Screen interrupted the doctor mid-patient — the same screen where they write prescriptions and complete visits. The product plan (`patient-records-plan.md`) always said catch-up belongs at end of session, not during a consult.</context>
  <action>Removed the catch-up banner and its Fill-in-now modal actions from Consult Screen. Moved the same banner and patient-list flow to Live Queue Control (top of page, doctors only). End-session toasts now point at **Fill in now** on that page instead of "open Consult Screen".</action>
  <reason>Consult Screen is the exam desk; Live Queue Control is the front-desk wrap-up board where staff already end the session. One reminder in the right place beats a distraction while a patient is in the chair.</reason>
</decision>

## 2026-08-12T15:07:10+0600
<decision>
  <category>UI/UX</category>
  <context>The Rx desk let a doctor name and save the current prescription as a reusable pack, and that was the **only** place packs could be created — nothing else in the app touched `prescription_templates`. The owner ruled it out: doctors will not add or change packs from the consult screen. Separately, "+ Add medicine" sat beside the shorthand typing box rather than under the medicine table.</context>
  <action>Pack creation, editing and deletion moved to **My medicines** (`createPackAction` / `editPackAction` / `deletePackAction` + a packs section in the page view), reusing `PrescriptionTemplateService` unchanged. The desk keeps the Packs chips and `applyPack()` and lost the naming box, `savePack()` and `ConsultScreen::saveRxPack()`. "+ Add medicine" is now a full-width dashed button beneath the table. `DesktopRxPadTest::test_the_consult_screen_applies_packs_but_cannot_create_them` asserts the creation path cannot come back.</action>
  <reason>Assembling and naming a set of medicines is preparation; doing it with a patient in the chair is admin work interrupting clinical work, and the consult screen is the one place in the product where every second is in front of someone. My medicines was already the doctor's curation surface — `decisions.md` calls it "the one sanctioned writer of a doctor's shortlist" — so packs belong beside it rather than on a second, competing editing surface. Removing creation without providing it elsewhere would have left packs applicable but unmakeable, so the move had to happen in the same change. The button moved because it continues the list, so it belongs where the list ends rather than competing with the typing box for the same row.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>Two defects surfaced only because the pack move was covered by tests, and both would have shipped silently. `PrescriptionTemplateService::save()` matches a pack by name, so editing one and changing its name wrote a *second* pack and left the original. And the pack list is exposed as a Livewire computed property, which Livewire caches for the whole request, so a pack just created re-rendered from the list captured before the write.</context>
  <action>`editPackAction()` deletes the original row when the name changed. Every pack write calls `MyMedicines::forgetPacks()`, mirroring `ConsultScreen::forgetQueueState()`. Both are covered: `test_renaming_a_pack_does_not_leave_the_old_one_behind` and `test_a_pack_can_be_created_here_and_shows_on_the_page`. The pack repeater also starts at `defaultItems(0)` — the medicine field is required, so a pre-seeded blank row made the form refuse to save until the doctor filled or deleted it.</action>
  <reason>All three fail quietly rather than loudly, which is what makes them worth recording. A duplicate pack looks like the doctor's own mistake and is only noticed once there are six of them and nobody can tell which one the desk is offering. A save that appears to do nothing teaches a doctor the feature is broken and they stop using it. A required blank row reads as the form being wrong rather than the row being empty. None would have been found by using the page casually.</reason>
</decision>

## 2026-08-12T15:17:03+0600
<decision>
  <category>UI/UX</category>
  <context>Staff needed to tell waiting patients the doctor was running late, but Mark Late lived only under Live Queue Control → Session actions. That felt like they had to start the queue first, even though Mark Late actually works before Start.</context>
  <action>Kept Mark Late on Live Queue Control. Added the same action on Daily Roster (table header): pick session when more than one runs today, pick delay minutes, same `LiveSessionService::markDelay()` path, same SMS cost warning and optional WhatsApp "Tell waiting patients" hand-off. Hidden once that session is already active/paused/delayed/finished.</action>
  <reason>Daily Roster is where staff already look in the morning (walk-ins, who is booked). Putting Mark Late there matches "doctor called — stuck in traffic" without opening the queue runner screen. One service path keeps outdoor screen, ticket delay banner, and SMS behaviour identical from either door.</reason>
</decision>

## 2026-08-12T15:26:35+0600
<decision>
  <category>UI/UX</category>
  <context>Owner asked for the waiting-room queue to call the patient's name as well as the serial. Names cannot be pre-recorded like `number-N.wav`, and prior bugs forbade browser SpeechSynthesis for the serial (ghost voice). Owner said try it anyway — English or Bangla reading does not matter.</context>
  <action>Keep Karen WAVs for the serial. After each of the three number passes on the outdoor screen, speak `now_serving_name` via browser `speechSynthesis` (`speakName()`), gated on the same sound-unlock / mute / `announceSequence` cut as the WAV loop. Live Queue Control plays the number WAV then the name once. Locale still follows `call_announce_locale` (bn-BD / en-US) with best-effort voice pick; missing Bangla voices fall back to English. Serial must never fall back to TTS.</action>
  <reason>A try-it path that ships today without API keys or a name-recording workflow. The ghost-voice complaint was about hollow *number* speech; names are short and variable, so quality is "good enough to try" rather than settled. If chambers hate the name voice, replace with check-in name recording or cloud TTS without touching the Karen serial path.</reason>
</decision>

## 2026-08-12T15:38:21+0600
<decision>
  <category>Business_Logic</category>
  <context>The owner's complaint about the Rx desk was about typing, not looks: full medicine name by hand, then dose, then frequency, then duration, then timing, with dose chips that showed the same five numbers for every drug — "who makes 5 mg NAPA?". Two of the three causes were defects rather than design: the desk carried a hardcoded dose list, and it had no input for the per-medicine Reason (`indication`) that the phone modal and the printed script have always had.</context>
  <action>`MedicineService::doseOptionsForBrand()` becomes the one lookup for a brand's strengths; `VisitNotesFormSchema::doseOptionsForBrand()` now delegates to it and only adds the "Other" escape, and the desk reads it over a new `GET /api/medicines/doses` — on picking a medicine and again when the dose cell is focused, cached per brand, because a row reopened from a saved prescription never ran a search. Chips show the form in the label (`500 mg tablet`) and write the bare strength. A brand with no catalogue row shows no chips. `pickMedicine()` also fills the single strength when a brand ships only one. Reason is now an inline input under the brand on each desk row, never pre-filled.</action>
  <reason>The catalogue already knew every strength each brand ships in — the desk was the only place guessing, and guessing wrongly in both directions at once, offering doses that do not exist while hiding the paediatric syrup. Chips fetched on focus rather than bundled with search results is what makes them work on a reopened prescription, which is the case a doctor hits when a patient comes back. Reason is deliberately left blank rather than seeded from the catalogue's `indications` column: that column holds a drug-class hint and sometimes marketing prose ("Maxpro is indicated: To relieve from chronic heartburn…"), and pre-filling it would put text the doctor did not write onto a document he signs — the same rule that keeps vitals from carrying forward.</reason>
</decision>

## 2026-08-12T15:47:16+0600
<decision>
  <category>UI/UX</category>
  <context>Two complaints about the Rx desk, both about it behaving like two screens rather than one: the doctor saw two Complete visit buttons, and Preview threw him into a separate tab while a patient was sitting in front of him.</context>
  <action>Hide the Filament page header actions at ≥1024px whenever the desk is on screen, leaving the desk's own sticky bar as the single copy. Preview now saves and mounts `previewPrescriptionAction()`, a modal that loads the real `prescriptions.print` route in an iframe with a Print button inside it; Save & print still opens a tab, because that action is explicitly handing the page to the browser's print dialog.</action>
  <reason>The desk bar keeps Complete visit because it stands beside Preview / Save & print / Save only, which is the order the doctor actually works in — the header copy is the orphan. Framing the print route rather than re-rendering a summary means the preview is the printed script rather than an approximation of it: a second rendering path would be a second thing to keep in step, which this codebase has repeatedly failed to do. The URL is built on the server rather than passed from the pad so nothing the browser says decides what gets framed.</reason>
</decision>

## 2026-08-12T16:01:00+0600
<decision>
  <category>UI/UX</category>
  <context>Screenshots of the desk showed the pad opening as headings over nothing, a grey slab under those headings, a red-looking focus ring on the typing box, and `napa` + Enter producing an empty NAPA line. Together they made the fastest path through the pad the least helpful one.</context>
  <action>The typing box now searches the catalogue as you type (same `/api/medicines/search` the Brand cell uses), with arrow-key navigation and Enter to take the highlighted suggestion. Enter on a bare name prefills only from an exact brand match; anything else must be picked from the list. Prefill logic is shared with the Brand cell through `applyPrefill()` and `fillOnlyStrength()`. The pad opens with one blank row, which the new medicine replaces rather than stacking above. Desk inputs get an explicit primary focus ring, and the table scrollbar is thin.</action>
  <reason>Two doors onto the same pad should carry the same knowledge; the shortcut being dumber than the long way is what made the desk feel worse than the phone form. Exact-match-only on Enter is the safety line — a prescription is signed, and `nap` resolving to NAPA EXTRA is a different drug, so the near-miss case is pushed onto the suggestion list where the doctor sees what he is choosing. The blank opening row is what the paper pad does; it is furniture only, and both the client payload and `VisitRecordService` drop it if untouched.</reason>
</decision>

## 2026-08-12T16:08:33+0600
<decision>
  <category>UI/UX</category>
  <context>Owner screenshots showed typing ENTI / na / Nap with no dropdown, and ENTASID with an empty dose cell — so the previous "suggestions work" claim looked like nothing had changed.</context>
  <action>Stop clipping: table wrap `overflow: visible`, brand suggestions absolutely positioned with a shadow. Medicine API URLs relative to the current host. Tabbing out of a typed exact brand name now runs the same catalogue prefill as picking a row. ENTASID-style freehand names that are not in the catalogue still get no dose chips — that is correct, not a blank UI bug.</action>
  <reason>A search that runs but cannot be seen is worse than no search: the doctor invents workarounds (typing the full name) that then also skip prefill. Clipping was a CSS rule interaction, not missing data — NAPA and ENTIFLOX were in the catalogue the whole time.</reason>
</decision>

## 2026-08-12T16:17:49+0600
<decision>
  <category>Code</category>
  <context>Owner browser console showed repeated 404s for /api/medicines/search on 127.0.0.1:8000 — the reason suggestions never appeared locally.</context>
  <action>Wire the desk medicine search, dose, and condition URLs through tenant_web_url(), matching the voice-upload endpoint. Custom domains still get root paths; path tenants get /{slug}/api/….</action>
  <reason>A relative /api/… looks portable but skips the tenant slug on central hosts. That failure mode is invisible on solo.localhost (domain tenancy) and only shows up on the path-tenant local workflow the owner actually uses.</reason>
</decision>

## 2026-08-12T16:46:44+0600
<decision>
  <category>Business_Logic</category>
  <context>Owner asked what about frequency, duration and timing after search started working — the screenshot showed NAPA with 80 mg/ml filled and those three cells still empty, with frequency chips only appearing after a manual click.</context>
  <action>Prefer the catalogue SKU that actually carries a starter pattern when collapsing a brand. Expose brand-level defaults on GET /api/medicines/doses. Desk backfills empty frequency/duration/timing from that on pick and when a dose chip is tapped. Timing gains the same focus chips as frequency/duration.</action>
  <reason>Choosing drops for a child must not erase the adult pattern the pad already knows — empty cells get the brand line, filled cells stay as typed. Chips remain the fast edit path; auto-fill is what stops the one-cell-at-a-time grind.</reason>
</decision>

## 2026-08-12T17:23:06+0600
<decision>
  <category>UI/UX</category>
  <context>Owner screenshot showed NAPA / HISPASIN / RESERVIX with frequency and duration filled but Timing stuck on a dash, despite catalogue defaults carrying after_food / at_night.</context>
  <action>Render Timing select options in Blade so they exist before Alpine writes the prefilled value. Keep on-focus chips as a fast tap path.</action>
  <reason>Same data path as frequency — the wipe was a control bug, not missing defaults. Fixing the select avoids teaching doctors to re-tap Timing on every line.</reason>
</decision>

## 2026-08-12T18:00:31+0600
<decision>
  <category>UI/UX</category>
  <context>Real Bangladeshi chamber pads put clinical notes on the left and medicines on the right under a letterhead; ChamberQ's printout was a top-to-bottom report that did not look like the paper doctors and pharmacists already trust. Separately, if staff forgot to send the SMS/WhatsApp prescription link, the patient had no backup — portal and ticket deliberately showed no clinical data.</context>
  <action>Restyle doctor print and patient copy as one shared BD pad sheet (letterhead, patient band, left clinical / right Rx, chamber footer + bilingual "bring this prescription when you visit again"). Expand the patient copy to the full clinical pad (diagnosis, notes, Inv, meds, chamber; still no voice/photo). Override the Stage 4 portal ban: `/portal` lists up to 2 recent prescriptions with medicines and opens them via phone-gated `GET /portal/prescriptions/{id}?phone=` (durable, no 48h expiry). SMS `/p/{token}` stays as the send channel and still expires in 48h.</action>
  <reason>Patients and pharmacists recognise the two-column pad; matching it reduces friction at the pharmacy counter. Portal access accepts that anyone who knows the phone can see those two prescriptions — the same gate bookings already used — in exchange for the patient still getting something when staff forget to tap Send.</reason>
</decision>

## 2026-08-13T01:16:27+0600
<decision>
  <category>Business_Logic</category>
  <context>Owner unlocked cross-chamber patient sharing after reviewing Singapore (opt-out national file) vs India (opt-in consent). Patients are expected to agree in almost all cases; a new phone currently creates a disconnected patient file with no staff prompt. The previous rule was that records stay inside one practice and other ChamberQ doctors never see individual clinical data.</context>
  <action>Override the practice-only records rule for ChamberQ-to-ChamberQ clinical continuity. On booking, add a checkbox (default ON): patient opts to share their details with other ChamberQ doctors — scope is everyone on the platform (any ChamberQ chamber/doctor the patient later visits), not limited to the same clinic. Sharing is consent-gated by that checkbox (can be unticked), not silent. Super Admin / marketers still do not browse individual patient files; Research data stays counts-only. Signup/privacy copy (including patient-records-plan Appendix B “records belong to your practice”) must be rewritten before this ships to patients. Exact shared payload (basic demographics vs full visit notes/Rx) and the cross-tenant identity model are implementation follow-ups — default intent is clinical continuity for treating doctors, not a public directory.</action>
  <reason>Owner explicit unlock (“unlock it. share with everyone”) after confirming default-ticked booking consent matches real chamber behaviour (patients almost always say yes). India-style one-tap consent is preferred over Singapore-style silent national open access; “everyone” means any ChamberQ treating doctor, not platform staff. Continuity across chambers and SIM changes is a product bet the owner is willing to take against the earlier practice-silo promise.</reason>
</decision>

## 2026-08-13T01:29:48+0600
<decision>
  <category>Business_Logic</category>
  <context>Owner chose Option B for cross-chamber share payload after unlocking ChamberQ-to-ChamberQ clinical continuity.</context>
  <action>Shared payload is the full clinical file: demographics, allergies/conditions/medicines on the patient row, visit notes (including C/C, H/O, O/E, diagnosis, vitals, advice, tests), and prescriptions/medicine lines. Voice notes and prescription photos are never shared across chambers. Identity for v1 is normalized phone + normalized name. Booking checkbox defaults ON; untick revokes on that patient row. Consult Screen may show other chambers’ visits only when the current patient’s share flag is true and matching remote patients also have share on. Index + short TTL cache required so the Consult Screen 3s poll does not re-query every tick.</action>
  <reason>Owner selected full-file continuity over a basic safety pack; excluding media keeps cross-tenant URLs and private disk objects out of foreign chambers while still giving the treating doctor the paper-folder equivalent.</reason>
</decision>


## 2026-08-13T01:59:39+0600
<decision>
  <category>Business_Logic</category>
  <context>Cross-chamber share and same-chamber continuity break when a patient changes SIM; phone+name alone cannot reconnect them. Owner chose optional NID rather than a required national ID field.</context>
  <action>Add nullable `patients.nid` (10 or 13 digits, normalized via `BdNid`). Optional on online booking and walk-in; staff can fill it on the Patients form from the card. Match order: NID first when present, else phone + name. Never put NID on tickets or SMS (bookings still denormalize only name/phone). Unique per tenant when set. Cross-tenant clinical share uses the same NID-first rule.</action>
  <reason>Optional keeps booking conversion high; NID is the durable key for SIM changes and cross-chamber continuity without government verification in v1.</reason>
</decision>

## 2026-08-13T02:05:58+0600
<decision>
  <category>UI/UX</category>
  <context>The book-appointment identity step felt long: long field labels, helper paragraphs under phone/NID/WhatsApp/share, and an earlier-date waitlist checkbox most patients would not use.</context>
  <action>Simplified the identity step to short labels (Date, Name, Phone, NID optional, Different WhatsApp, Who for?, Share with other ChamberQ doctors), removed helper copy under those fields, removed the WhatsApp “only if…” line and the share explanation, and removed **Tell me if an earlier date opens up** from the wizard. API/`wants_earlier_date` and the staff Waiting for earlier date page remain for legacy flagged bookings.</action>
  <reason>Fewer words and fewer choices on the last booking step reduce friction before Confirm; earlier-date follow-up was staff-heavy and not worth the patient UI cost.</reason>
</decision>

## 2026-08-13T02:08:02+0600
<decision>
  <category>UI/UX</category>
  <context>Seat counts (“15 left”) and “Pay at the clinic” on the booking details step added noise without helping the patient choose.</context>
  <action>Removed seat-count badges from open-date cards and the identity review block, and removed “Pay at the clinic”. Capacity is still checked before Confirm (button stays disabled if the day filled); payment remains at chamber by product policy, just not restated on this step.</action>
  <reason>Keep the last booking screen focused on name/phone only.</reason>
</decision>

## 2026-08-13T02:12:11+0600
<decision>
  <category>UI/UX</category>
  <context>Booking details fields felt tall, and separate labels above each box made the last step feel sparse.</context>
  <action>Shorter inputs (~42px) with floating labels inside Name / Phone / NID / WhatsApp / Date; Change stays beside Date.</action>
  <reason>Looks more like a compact phone form and puts less scrolling between date and Confirm.</reason>
</decision>

## 2026-08-13T02:24:42+0600
<decision>
  <category>UI/UX</category>
  <context>The Date field + Change control duplicated the dark booking summary and competed with Your details.</context>
  <action>Identity step order is summary strip → **Your details** → name/phone fields. Removed the Date input and Change button; patients use Back to pick another day.</action>
  <reason>One place shows the appointment; the heading sits with the fields it introduces.</reason>
</decision>

## 2026-08-13T02:36:15+0600
<decision>
  <category>Business_Logic</category>
  <context>Clients want to buy only a portfolio site, only queue, only prescription, or combinations — not always the full Solo/Clinic bundle. Solo vs Clinic remains the size tier (doctors/chambers/labs).</context>
  <action>Three sellable product modules in `feature_flags` (default ON when absent): **Front door** (`front_door` — website + booking + Daily Roster day list; ticket shows sitting name + window, no come-around); **Live queue** (`live_queue` — outdoor TV, Call next, live ticket ETA/now serving); **Prescription** (`prescription` — consult/Rx). Super Admin checkboxes on tenant create/edit. Routes gated with `tenant.module:*`. Front-door-only roster gets Arrived / Done / No-show (no Call next). Booking confirmation SMS stays optional (credits + doctor toggle), not a module.</action>
  <reason>Matches WhatsApp sales reality (many doctors only want a site + serials) while keeping Solo/Clinic for scale; defaults preserve existing full-product chambers.</reason>
</decision>

## 2026-08-13T02:39:04+0600
<decision>
  <category>Business_Logic</category>
  <context>Module packages need clear Solo list prices so Super Admin snapshots and marketer commissions match what sales quotes on WhatsApp.</context>
  <action>Solo à la carte in `config/marketing.php` `modules`: Front door ৳7,500 setup / ৳1,000 mo; Prescription ৳2,500 / ৳0; Live queue ৳7,500 / ৳2,000. All three = bundle ৳15,000 / ৳3,000 (setup discount vs ৳17,500 unit sum; monthly equals the sum). Partial combos sum unit prices. `PlanPricingService::listPricesForModules` / `listPricesForTenant` drive billing snapshots; Clinic tier still uses Clinic list price. Super Admin amount preview follows the module checkboxes.</action>
  <reason>One source of truth for sales quotes, tenant due amounts, and partner commissions; Prescription as a cheap/no-monthly attach keeps Rx easy to add after Front door.</reason>
</decision>

## 2026-08-13T02:39:57+0600
<decision>
  <category>Business_Logic</category>
  <context>Marketers need a clear script when a doctor asks for a feature or module in a meeting, phone call, or SMS — without inventing free timelines or surprise fees.</context>
  <action>Policy for partners (logged in `docs/ChamberQ-Marketing-Playbook.md` + BN): capture the request and escalate; we try to include reasonable asks for free as soon as capacity allows; we always say whether it is possible; if it is out of budget we may charge extra and disclose the fee before work starts. Partners must not promise “free next week” without central confirmation.</action>
  <reason>High-touch BD sales will always surface custom asks; a shared free-first / honest-fee policy protects trust and stops partners over-promising.</reason>
</decision>

## 2026-08-13T03:17:53+0600
<decision>
  <category>CRO</category>
  <context>Rising Star overlapped Front door / module pricing and confused sales. Owner agreed the cleanest story is modules only, with Maestro as the full one-doctor bundle.</context>
  <action>Retire **Rising Star** as a named plan. Marketing pricing shows two cards — **Maestro** (৳15,000 / ৳3,000, all three modules) and **Clinic** — plus a modules table under the cards (website, +Rx, +queue, all three = Maestro). Internal `plan_tier` `solo` unchanged. Playbook updated; no Rising Star card or brand on the homepage.</action>
  <reason>One product language for marketers and buyers; à la carte stays a footnote, not a competing lite brand.</reason>
</decision>

## 2026-08-13T02:42:37+0600
<decision>
  <category>UI/UX</category>
  <context>Patients still need an obvious way to pick another day after the Date field was removed.</context>
  <action>Put a **Change date** text control on the dark booking summary strip (not a second date input). Your details stays below.</action>
  <reason>Keeps one summary of the appointment while restoring the familiar Change date label.</reason>
</decision>

## 2026-08-13T10:08:50+0600
<decision>
  <category>Code</category>
  <context>The repo was marked "pre-production" on 2026-08-08. It is now live-ready, and the gap that kept showing up was verification: agents (including this one) repeatedly reported work "done" on a green SQLite run, while production is MySQL. SQLite hides exactly the failures this codebase has already been bitten by — foreign-key ordering, `ONLY_FULL_GROUP_BY`, datetimes in date columns, and uncontended row locks.</context>
  <action>`CLAUDE.md` now states the project is live and requires a production-shaped run: any change touching migrations, foreign keys, transactions, row locking, date columns or `GROUP BY` must pass against local MySQL (exact command documented in `CLAUDE.md`) and the report must name the engine(s) it passed on, finishing with `app:production-check --strict`. Separately, `~/AGENTS.md` §3 gained the general discipline: record the pre-existing failures before starting, watch every new regression test fail without its fix, and confirm a revert actually reverted before trusting a "still passes" result.</action>
  <reason>A local MySQL server is already running on this machine, so "I could only test on SQLite" was never a real constraint — just an unstated default. Making the engine explicit in the report is what turns a green tick into evidence. The `~/AGENTS.md` half is deliberately generic because those three habits are not ChamberQ-specific; the MySQL specifics stay here, next to the MySQL CI job.</reason>
</decision>

## 2026-08-13T10:20:51+0600
<decision>
  <category>Business_Logic</category>
  <context>Doctors still take cash at the desk. They also spend money the same day (rent, tea, salary). Competitors show a billing tick; ChamberQ had none. This is not online booking payment.</context>
  <action>Desk khata: income and expense in `chamber_cash_entries`. Daily Roster Collect fee records one income row per booking (default from the doctor's `default_fee_taka` plus labs; cash/bKash/Nagad/card at the desk; waive allowed). Cashbook Add expense / Add income for everything else. Day/week/month totals: income, expense, net. Patients still pay at the chamber — no payment gateway on booking.</action>
  <reason>Like the red exercise book at the counter: money in and money out in one place. A fee-only tick would hide the day's real leftover. Online checkout stays locked until the owner asks.</reason>
</decision>

## 2026-08-13T10:23:15+0600
<decision>
  <category>Business_Logic</category>
  <context>Bangladesh chambers lose internet during load shedding, and doctors also sit in rooms that barely have signal (camps, visiting days). A competitor ships offline-first. ChamberQ is a cloud Livewire app, so the pad used to hang on Save. A PC in every chamber would be a second product (updates, two-way sync, home booking dying with the box).</context>
  <action>Same cloud app, with a travel bag on the laptop. Chamber outage: yellow banner; pad Save/Print queue in IndexedDB (`rx_save`) and print locally; Call next / walk-ins freeze — never replayed later. Visiting / camp: Pack bag on good internet, write a walk-in list (not Live Queue), print, upload `visiting_visit` when signal returns. SMS waits until upload. Sync is idempotent (`visit_records.offline_sync_id`). Queue mutations are not accepted offline.</action>
  <reason>The patient in the chair still gets a printed pad. The TV and online booking stay in the cloud so patients at home can still book while the chamber line is down. A local server would lose that and own hardware support.</reason>
</decision>

## 2026-08-13T10:29:34+0600
<decision>
  <category>UI/UX</category>
  <context>Cashbook only said how many fees were waived, not how many taka were left on the table. Staff need the money figure the way the red khata would show it.</context>
  <action>A waived fee keeps the uncollected amount (what staff typed, or the doctor's default + labs if they left it blank). Category stays `waived`, so it is not income and not an expense. Cashbook shows a Waived ৳ card plus patient count. Daily Roster shows Waived ৳… on the row.</action>
  <reason>Counting patients hides whether you waived ৳200 or ৳2,000. The amount is what was not taken; it must not shrink net cash in the drawer.</reason>
</decision>

## 2026-08-13T10:33:22+0600
<decision>
  <category>Business_Logic</category>
  <context>Patients could only book on each doctor's own website (name + phone, no account). Finding another ChamberQ doctor meant hunting a separate site. The 2026-07-27 rule was "no patient login accounts."</context>
  <action>Two booking doors. Door 1 unchanged: doctor's website, name + phone, no account. Door 2: central `/find` lists every Front door doctor who `acceptsBookings()`, and optional phone-OTP login (`patient` guard, `patient_accounts`) unlocks `/me` serials and `/me/history` across clinics. Booking never requires login. OTP SMS is ChamberQ-paid (not the doctor's wallet). Book from Find reuses `BookingService` via `/{slug}/book`. Share-clinical-history still gates *other doctors*; a logged-in patient always sees their own visits. Central `/` stays doctor sales (WhatsApp CTA) with Find / Patient login in the nav.</action>
  <reason>Like a mall directory plus a membership locker: anyone can walk into a shop, and the card is only for people who want every receipt in one place. Overrides "no patient login" for Door 2 only.</reason>
</decision>

## 2026-08-13T10:38:47+0600

<decision>
 <category>UI/UX</category>
 <context>The homepage hero photo field was a paste-a-link box. Chamber staff do not keep Unsplash URLs; they have a photo on the phone or computer and expect a Choose file control, like attaching a picture in WhatsApp.</context>
 <action>Hero Banner uses Filament FileUpload (Laravel's public disk, folder `webpage-hero/{tenant_id}/`). The saved value is a `/storage/…` path so the locked homepage templates still use it as the image source. A previously pasted https link still shows until staff upload a replacement. Clinical photos stay on the private disk; this is website chrome only.</action>
 <reason>Laravel's default public disk plus Filament's image picker is the stock way to put a file on the website. A URL field made staff think they needed a web address first.</reason>
</decision>

## 2026-08-13T10:41:56+0600

<decision>
 <category>UI/UX</category>
 <context>Latest Educational Videos had an "upload" choice that still asked for a URL. Staff need to drop in a cover photo and a short file, like putting a poster and a clip on a notice board, while the homepage cards stay the same design.</context>
 <action>The video block offers a cover-image FileUpload and, when Media is "upload", a video FileUpload (MP4/WebM/MOV, 20 MB) on the public disk. On save, the file path is copied to `video_url` so the existing cards still open the clip. YouTube/Facebook links stay available. Homepage markup is unchanged.</action>
 <reason>The section already looked right; the missing piece was a real file picker. Changing the locked patient cards would have been a redesign, not a media fix.</reason>
</decision>

## 2026-08-13T10:46:29+0600

<decision>
 <category>Code</category>
 <context>PHP 8.4+ warns when `tempnam()` cannot write Laravel's facade cache folder and drops the file in `/tmp`. Debug mode turns that warning into a red error page, so the Web Pages editor died the moment a facade was compiled. Livewire also had no temp folder for the new image/video uploads.</context>
 <action>`RuntimeDirectories::ensure()` on app register creates the cache, session, view, Livewire tmp, and website-media folders at 0775. Livewire's temp upload cap matches the 20 MB video field. PHP-FPM upload limits are set in `public/.user.ini`.</action>
 <reason>A chmod someone must remember will be forgotten. Creating the folders at boot is the same class of fix as putting GSM flattening inside `SmsService::send()`.</reason>
</decision>

## 2026-08-13T11:00:26+0600

<decision>
 <category>Code</category>
 <context>Website image upload looked like it worked in the admin picker, then the homepage stayed blank. Files were being written where the browser cannot see them, and a leftover shortcut still pointed at the old SolDoc folder.</context>
 <action>Keep the `public` disk at `storage/app/public` (tenant id in the folder name, not a hidden tenant storage root). Stage Livewire picks on a `livewire-tmp` disk. Repair `public/storage` when it points elsewhere. Convert FileUpload paths to `/storage/…` only when the Web Page is saved.</action>
 <reason>Like putting posters in the shop window instead of a locked back room: the file has to sit on the path the website already uses. Clinical photos stay on the private tenant disk.</reason>
</decision>

## 2026-08-13T11:15:22+0600

<decision>
 <category>UI/UX</category>
 <context>Opening a long Web Page in admin showed a wall of open fields. Staff needed a table of contents of closed lids, not extra public-site blocks.</context>
 <action>Page sections and nested lists start collapsed. Closed labels are type plus headline (e.g. Hero — …). Block numbers off. Clone and drag-reorder stay on. Inner pages get gray Collapse all / Expand all and a narrower form (`max-w-5xl`); homepage (`/`) uses full width without those two buttons. Save changes sits at the bottom right, not sticky; closing the tab still warns if the form is dirty. No SEO or grid-vs-flex layout fields were added — ChamberQ does not have those.</action>
 <reason>Like a packed moving-box list: each lid shows what is inside, and you only open the box you are packing. Public homepage blades stay locked.</reason>
</decision>

## 2026-08-13T12:08:55+0600

<decision>
 <category>Business_Logic</category>
 <context>The no-login portal only listed the two most recent prescriptions. A returning patient who needed an older slip (for example last month's course, not today's) could not get it without the expired SMS link or a ChamberQ login.</context>
 <action>Remove `PORTAL_PRESCRIPTION_LIMIT`. `/portal` now lists every prescription that has medicines for that phone, newest first. Phone gate, durable `/portal/prescriptions/{id}?phone=` links, and the 48h SMS `/p/{token}` channel are unchanged. Empty prescriptions (no medicine lines) still stay off the list. Bookings on the same page stay capped at 10.</action>
 <reason>Owner asked to drop the cap. The portal is already the backup when staff forget to send the link; hiding older pads after visit three made that backup incomplete. Anyone who knows the phone could already open those two slips — listing the rest uses the same gate.</reason>
</decision>

## 2026-08-13T12:26:34+0600

<decision>
 <category>Code</category>
 <context>Developer handoff PDFs were mixed into the ChamberQ git folder (`docs/`), which is easy to lose among code, proposals, and slides. Future handoffs need one obvious place outside the project.</context>
 <action>Keep all developer-handoff PDFs in `~/developer-handoff/<ProjectName>/`. ChamberQ copies live in `~/developer-handoff/ChamberQ/`. New products get their own named folder under `~/developer-handoff/`. Agents must write new handoff PDFs there (a repo `docs/` copy is optional).</action>
 <reason>Like a labelled drawer on the desk, not a PDF buried in a filing cabinet of source code. Finder can open it without opening the project. Other projects can share the same parent folder without mixing files.</reason>
</decision>

## 2026-08-13T12:34:58+0600

<decision>
 <category>Code</category>
 <context>Handoff PDFs in `~/developer-handoff/ChamberQ/` needed a clear version stamp so a later export does not silently replace an earlier one, and so the owner can see when it was made.</context>
 <action>Filename must include local date and 24-hour time with no colon: `<Name>-YYYY-MM-DD-HHmm.pdf` (10:00 PM is `2200`). Each save is a new file; do not overwrite an older dated PDF.</action>
 <reason>Like writing the date and time on a printed pack before you hand it over. 24-hour time avoids AM/PM mix-ups (2200 is clearly night).</reason>
</decision>

## 2026-08-13T12:38:12+0600

<decision>
 <category>Code</category>
 <context>The full `YYYY-MM-DD-HHmm` stamp on handoff PDFs was longer than needed. Owner asked for `1308-2200` only.</context>
 <action>Filename stamp is day+month then 24-hour time: `<Name>-DDMM-HHmm.pdf` (13 August 10:00 PM → `1308-2200`). No year. Each save is still a new file.</action>
 <reason>Shorter to read in Finder. Day and month plus clock time is enough to tell packs apart.</reason>
</decision>

## 2026-08-13T12:48:03+0600

<decision>
 <category>Code</category>
 <context>Chamber admin crashed on the default Laravel cache driver because Stancl tenancy always uses cache tags, which database and file stores cannot do.</context>
 <action>Keep tagged cache when the store supports it (tests' array store, Redis later). On database/file, isolate chambers by prefixing cache keys with the tenant id instead of requiring Redis to open the panel.</action>
 <reason>Like putting each chamber's papers in a labelled folder rather than demanding a filing cabinet that can colour-code them. Redis is still better in production for a one-chamber flush, but the panel must work on the cache driver we actually ship.</reason>
</decision>

## 2026-08-13T13:09:33+0600

<decision>
 <category>Business_Logic</category>
 <context>Doctors already speak the prescription out loud (Napa 500 one plus one plus one five days). Typing the same words onto the pad is extra work in a busy chamber. An earlier decision (2026-08-07) deferred speech-to-text because Whisper-on-audio was the wrong tool for mixed Bangla-English brand names, and because no free LLM was trusted to fill a BM&DC-signed pad.</context>
 <action>Ship mic-to-prescription on the desktop consult pad. Chrome/Edge listens in the browser (bn-BD or en-US) and does not save audio. Groq (`openai/gpt-oss-120b`) turns the words into a draft of catalogue-matched medicine rows. The doctor checks the draft, then Save / Print. Unmatched brands are marked uncertain. Diagnosis is never auto-coded from speech. Patient name and phone are never sent to Groq. ChamberQ absorbs the Groq cost (no doctor AI wallet). Existing 20-second visit voice notes stay as a separate recorder. This supersedes the 2026-08-07 STT deferral for this flow only — do not restore the Whisper-1 audio pipeline.</action>
 <reason>Like a compounder who writes what the doctor just said, then the doctor reads the pad before it is signed. Listening is free in Chrome. The paid step is only the fill, on a model that is still available after Llama 8B/70B shut down. A wrong drug name on a signed pad is a patient-safety failure, so the draft is never auto-printed or SMS'd.</reason>
</decision>

## 2026-08-13T16:32:23+0600
<decision>
  <category>UI/UX</category>
  <context>A full re-read of the Rx pad. Most of the earlier gap list is built — shorthand search with prefill, per-brand dose chips, Bangla/English dictation, a Reason box, clash and allergy warnings, vitals trends, follow-up reminders, packs on My medicines, an offline shell. What remained was the thing a doctor feels forty times a day: the drugs he always prescribes still had to be typed. His curated **My medicines** list only surfaced if he typed into the search box, so a drug he had chosen and saved was no closer to hand than one he had never used.</context>
  <action>The doctor's own shortlist now sits above the prescription table as one-tap chips (`ConsultScreen::getMyMedicinesProperty()` → `addFromMine()`), filling the row from **his saved line** rather than the catalogue. Alongside it: ↑/↓ reordering on every medicine row, Enter moving through the dose/frequency/duration/reason cells and adding a row off the end, "Packs" relabelled **Use a pack** with its panel chrome removed, and the Add medicine control rebuilt as a real button with proper spacing.</action>
  <reason>The chips are the highest-value change left because a chamber doctor prescribes the same small set all day, and this is the one place where a tap replaces typing without the app guessing anything — the list is hand-curated, so the no-auto-learning decision (2026-08-11) is untouched. Alphabetical and capped at 8 on purpose: ordering by frequency would make the strip rearrange itself from behaviour the doctor cannot see, and buttons that move between patients are worse than no buttons. Row order matters because it is the order the patient reads and the pharmacist dispenses, and until now getting it wrong meant deleting and retyping. `nextCell()` walks the table's real inputs rather than a row/column map because cells appear and disappear — the Reason box only exists once a brand is chosen — so an index map would go stale exactly when the doctor is typing fastest.</reason>
</decision>

<decision>
  <category>UI/UX</category>
  <context>The owner reported that pack creation was still on the consult screen. It is not — the naming box, `savePack()` and `ConsultScreen::saveRxPack()` were removed earlier and a test asserts they cannot return. What is still there is the **Packs** button and a bordered panel, which read as a workspace rather than a picker.</context>
  <action>Relabelled to **Use a pack** and the panel's border/padding removed, leaving a row of chips. Nothing on the consult screen now resembles a place to build one.</action>
  <reason>Recorded because the report was about perception rather than code, and the fix is therefore a labelling and framing one. A control that *looks* like it edits something invites the doctor to hunt for the edit and conclude the product is confusing when they cannot find it. Worth noting for whoever reads the earlier entry: if a save-a-pack control genuinely appears on screen again, the page is stale rather than the code wrong — clear compiled views and rebuild assets before changing anything.</reason>
</decision>

## 2026-08-13T16:58:43+0600
<decision>
  <category>UI/UX</category>
  <context>Design review of the Rx desk. Nine cards rendered with identical chrome — white, 1px gray-200, radius 0.75rem, an 11px uppercase gray-500 heading — so the prescription shouted at exactly the same volume as Advice and Follow-up. Almost all type sat between 11px and 13px, with the brand name the same size as its own duration. Seven columns were separated by a single hairline with no hover, and chips that *insert* a row looked identical to chips that *toggle* a state.</context>
  <action>Table type to 14px and the brand to 15px; the ℞ card given a tinted heading strip and a primary-tinted border; row hover added; the six left-hand cards stripped of their individual borders and radii so the column reads as one continuous sheet (CSS only — no section markup moved); chips that add something marked `--add` with a leading `+` and a faint primary fill. The **Last visit** card keeps its tint so it still reads as reference rather than somewhere to type.</action>
  <reason>The screen is read at arm's length by people who are frequently over 45, on a monitor with width to spare, so 13px was costing legibility for nothing. Nine equally-weighted cards give the eye nowhere to land first; the prescription is the point of the screen and should look like it. Merging the left column in CSS rather than markup keeps the change reversible and avoids touching six sections that other work is actively editing. The chip distinction is learnable rather than discoverable — previously the only way to find out whether a chip toggled or inserted was to tap it.</reason>
</decision>

<decision>
  <category>UI/UX</category>
  <context>The desk's sticky bar carried four actions — Preview, Save & print, Save only, Complete visit — with two saturated buttons (primary and success) side by side and nothing indicating which one ends the consult. Keeping **Complete visit** there also forced a third breakpoint rule hiding `.fi-header-actions-ctn` at ≥1024px, on top of the ≤767px header rule and the ≥768px sticky-bar rule, all of which had to stay in step.</context>
  <action>**Save only** removed — *Save & print* already saves. **Complete visit** returned to the Consult Screen's own page header, and the ≥1024px hide rule deleted. The desk bar now holds Preview and Save & print, one of them filled, with the bar's existing `justify-content: space-between` putting patient identity at one end and the actions at the other. `DesktopRxPadTest::test_the_desk_leaves_only_one_complete_visit_button_on_screen` was rewritten: it previously asserted the header was hidden, which after this change still passed for the wrong reason (it matched the *mobile* rule's identical CSS string), and now asserts the desk template mounts no `completeVisit` at all.</action>
  <reason>Completing a visit closes it and advances the queue — a page action, not something a prescription pad does — so the pad carrying it was a category error as well as a visual one. Removing it collapses three interacting breakpoint rules into one complementary pair (header below 768px off, sticky strip above 768px off), which is the arrangement `bug_history.md` records as the fix for the two-Complete-visit-buttons bug; three rules was the shape that caused it in the first place. Two grey buttons and one filled one leaves exactly one obvious next action.</reason>
</decision>

## 2026-08-13T17:20:31+0600

<decision>
  <category>UI/UX</category>
  <context>Complete visit is the green “done” button on Consult Screen and Live Queue Control. Filament’s success palette cannot put white on its default green and still pass contrast, so it paints a pale chip with dark green type and a grey icon — which reads as muted or disabled, not as the action that ends the consult.</context>
  <action>Force a solid `--success-600` fill with white label and white icon via `.cs-complete-visit-btn` on the Consult Screen header action, the phone sticky copy, and the Live Queue Control card button.</action>
  <reason>A filled green button with white writing is what “this is the go action” looks like at arm’s length. Leaving Filament’s contrast fallback made the most important button on the page look quieter than Preview.</reason>
</decision>

## 2026-08-13T18:03:32+0600

<decision>
  <category>UI/UX</category>
  <context>The doctor print and the patient copy of a prescription used bilingual labels, but English led whenever the admin panel locale was English — which is always, because staff UI is English-only. The paper a patient takes to the pharmacy therefore opened with “Patient / রোগী” and Helvetica, which does not look like a Bangladeshi pad. Timing already printed both languages; the rest of the chrome did not feel Bangla-first.</context>
  <action>Printed/shared pad is Bangla-focused: `Bilingual` always leads with Bangla and renders English quieter (`.pad-l-en`); `html lang="bn"`; Hind Siliguri; dates via Carbon `bn`; print/share views set locale `bn` so follow-up phrases and empty states match. English stays on every fixed label for the pharmacist. Names, brands, and anything the doctor typed still pass through as written. Supersedes the 2026-08-11 “tenant locale leads” rule for this helper only — the helper exists solely on this sheet.</action>
  <reason>The patient and their family read the paper. A pharmacist still needs the English. Leading with English because the doctor types in English was the wrong reader. Like a school report card printed for parents in Bangla with the English subject names kept smaller, not the other way around.</reason>
</decision>

## 2026-08-13T18:25:48+0600
<decision>
  <category>UI/UX</category>
  <context>Premade advice for 58 diagnoses already existed, but doctors looking at the Advice box saw a blank textarea. The chip was a generic "Add advice" under Diagnosis, and saving the pad wiped it.</context>
  <action>The starter sentence now appears as a tap chip in the Advice card (the actual text, not the label "Add advice"). It is rehydrated from the coded diagnosis on every pad render so a save cannot hide it. Nothing auto-fills the box — tap still copies the line in, same as before. Free-text diagnoses still have no preset.</action>
  <reason>Advice chips belong where advice is written, the way C/C chips sit on the C/C table. A proposal that disappears after save trains the doctor that the feature does not exist.</reason>
</decision>

## 2026-08-13T20:18:58+0600

<decision>
  <category>UI/UX</category>
  <context>Many Bangladeshi doctors print onto pads that already carry their name, qualifications and chamber. ChamberQ's headed sheet would stamp a second letterhead on top of theirs. A second "Print on my paper" button would sit next to Save & print and force a choice every consult.</context>
  <action>One quiet **My paper** tick beside **Save & print**. Ticked: doctor print (`GET /prescriptions/{id}/print?paper=1`) hides the ChamberQ letterhead and leaves ~40mm at the top so their printed name is not overprinted. Unticked: the headed sheet, unchanged. The tick is remembered in this browser (`cq-print-on-my-paper`). Preview uses the same URL, so the iframe cannot disagree with the printer. Patient share and portal never omit the letterhead, even if `?paper=1` is pasted onto those URLs. Offline print on this computer follows the same tick.</action>
  <reason>Like choosing "print on letterhead" vs "print on blank" in Word — a setting, not a second Print button. The doctor who always uses clinic pads ticks once and forgets it. The patient copy still needs ChamberQ's heading because they are not holding the clinic's paper.</reason>
</decision>

## 2026-08-13T20:32:16+0600

<decision>
  <category>UI/UX</category>
  <context>The remaining ZilSoft speed gaps on the existing two-column pad: the Reason box made the doctor invent words; Advice was a blank box plus one diagnosis starter; O/E had no temperature and no finding taps; History's More list lacked COPD and Allergy; medicines could only be reordered with ↑↓.</context>
  <action>Keep the pad layout. (1) Label the reason box **Why?** and suggest as he types — curated English reasons immediately, then `/api/conditions/search` after three letters. Still free text. Never a popup, never pre-filled from the catalogue's drug-class / marketing text, and not ranked by what this doctor has diagnosed (2026-08-11 no-learning). (2) Five advice chips above the box (English labels, Bangla lines inserted); ★ saves the last line as "mine" in this browser (`cq-my-advice`), same class of explicit tap as My medicines, not inferred from past pads. (3) Temp °F as one extra vitals row (`visit_records.temperature_f`, grey last-visit, never pre-filled); four finding chips write into Other findings. (4) COPD and Allergy added behind History **More**. (5) Drag handle on the left of each medicine row; ↑↓ stay.</action>
  <reason>Steal their taps, not their Windows form. Google Maps does not open a window to pick a place; the list appears under the field. Ranking Why? by past diagnoses would have rebuilt the silent profile the owner ruled out. Bangla advice lines are what the patient reads; English chip labels match the English-only staff panel. Temperature is a measurement taken today, so it gets the same grey-reference rule as weight.</reason>
</decision>

## 2026-08-13T20:45:05+0600

<decision>
  <category>UI/UX</category>
  <context>On examination on the desktop pad listed Wt, BP, Pulse, SpO₂ and Temp as five full table rows. That hospital-form stack ate the left column so diagnosis and investigations sat below the fold while the doctor was still writing vitals.</context>
  <action>Put the five vitals on one wrapping paper-pad line: Wt [ ] kg · BP [ ]/[ ] · P [ ] · SpO₂ [ ]% · T [ ]°F. Finding chips and Other findings sit tight underneath. Last visit stays grey beside each box and is never copied into the box. Trend charts still appear only when past visits have data. Phone modal vitals are unchanged.</action>
  <reason>A paper pad writes vitals across one line, not down a form. Five stacked rows were furniture, not clinical content — like printing each vital on its own page of a chart.</reason>
</decision>

## 2026-08-13T21:07:28+0600

<decision>
  <category>UI/UX</category>
  <context>The desktop pad's patient strip (name, Preview, My paper, Save & print) is sticky so Save & print stays in reach while writing a long prescription. It used the same seat as Filament's topbar — viewport top, z-index 30 — so the strip painted over the menu and Complete visit.</context>
  <action>Stick the patient strip at `top: 4rem` (Filament topbar is `min-h-16`) with z-index 20, below the topbar's 30. It still pins while scrolling; it no longer covers the chrome. Pinned by `DesktopRxPadTest::test_the_patient_strip_sticks_below_the_filament_topbar`.</action>
  <reason>A sticky note belongs on the chart, not over the toolbar. Sharing `top: 0` / z-index 30 with `.fi-topbar-ctn` made the later element win, which hid Complete visit — the one button that ends the consult.</reason>
</decision>

## 2026-08-13T21:11:44+0600

<decision>
  <category>UI/UX</category>
  <context>The 21:07:28 source CSS change did not reach the browser: Filament loads the compiled Vite theme, which had not been rebuilt, so the patient strip still cut through "Dr. Shamim Ahmed".</context>
  <action>Rebuild `npm run build` so `theme-*.css` carries `top: 4rem` / z-index 20 on the strip. Also set `.fi-topbar-ctn { z-index: 40 }` so the menu bar wins if the two ever share pixels. The test now asserts both rules.</action>
  <reason>Editing the `.css` source is not what the doctor sees. The panel paints the built file. Raising the topbar's stack order is the backstop if sticky offset is a pixel short.</reason>
</decision>

## 2026-08-13T21:43:23+0600

<decision>
  <category>UI/UX</category>
  <context>ChamberQ's tenant admin still looked like stock Filament (top bar + default heading) while Getwebfield's admin used a designed content header, Geist type, Save-vs-Delete placement, and full-width padding. The doctor liked ChamberQ's collapsed icon sidebar and ChamberQ blue, and wanted the rest of Getwebfield's chrome.</context>
  <action>Turn the tenant admin topbar off. Each page gets a sticky content header: title left (uppercase Geist Mono), back arrow on Create/Edit, primary Save/Create (or Complete visit) on the right. Delete moves to an outlined danger action at the bottom of the form. Geist Sans/Mono, gray-50 content well, 16/40/80px inline padding, outlined table row actions, nav groups Operations / Website / Settings. Keep `sidebarCollapsibleOnDesktop()` forced closed, and keep `Color::Blue` (do not use Getwebfield `#2173BD`). The Rx pad patient strip still sticks at `top: 4rem` under that header. Super Admin and marketer panels are unchanged.</action>
  <reason>A chamber desk needs one place for the page's main button and a quiet place for Delete — not two bars fighting the prescription strip. The collapsed sidebar is already the right density for all-day use; swapping ChamberQ blue for Getwebfield blue would make the admin look like a different product.</reason>
</decision>

## 2026-08-13T22:08:16+0600

<decision>
  <category>UI/UX</category>
  <context>Reports the patient brought lived on the right of the desktop pad next to Voice / photo, so the visit column (C/C, H/O, O/E, Dx, Inv) was missing the papers they actually walked in with, and the prescription column mixed chart work with the Rx.</context>
  <action>Put Reports on the left: a typed note plus photos of the papers (lab printout, X-ray). Remove Voice / photo from the pad — voice stays in Complete visit. Cap 8 photos, stored separately from the handwritten-slip photo.</action>
  <reason>A paper chart keeps the reports with the examination, not with the medicines. Voice is a doctor-only note after the visit, not something the pad needs mid-consult.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>Staff who type up a paper prescription already photograph the slip. Patients also bring lab reports and X-rays that reception can scan before the doctor sees them, but staff must not write clinical notes.</context>
  <action>Staff Daily Roster entry may attach report photos (`report_photo_paths`) on the private disk. Typed `reports_seen`, diagnosis, advice and voice stay doctor-only. Existing doctor-attached photos are re-filled on reopen so a later staff save does not wipe them. Consult Screen stays doctor-only.</action>
  <reason>Photographing a paper the patient handed over is clerical, like photographing the slip. Writing what the report means is a clinical judgement staff are not allowed to make.</reason>
</decision>

## 2026-08-13T22:34:01+0600

<decision>
 <category>Business_Logic</category>
 <context>Voice-to-writing on the consult pad (Mic → Groq fills medicine rows) was not ready to keep running. The owner asked to put it aside and to make sure Groq is not called in the meantime. Doctors still type the prescription as they always could.</context>
 <action>Stash the pipeline unloaded under `docs/deferred/prescription-dictation/` (service, controller, Groq config, tests, Mic markup). Remove the Mic from the pad, drop `POST /api/prescriptions/dictate`, drop `GROQ_*` from env/phpunit/production-check. `PrescriptionDictationDeferredTest` fails the build if the route, Mic, or Groq config come back. 20-second visit voice notes stay. Do not restore the Whisper-1 audio pipeline from `docs/deferred/voice-transcription/` as a substitute. This supersedes the 2026-08-13T13:09:33+0600 “ship mic-to-prescription” decision until the owner asks to bring Mic back.</action>
 <reason>Like taking the dictation machine off the desk and locking it in a cupboard: the pad is still there for typing, and nobody can accidentally send a patient's visit words to Groq while it is off. Stashing rather than deleting keeps the working fill recoverable when we want it again.</reason>
</decision>

## 2026-08-13T23:35:10+0600

<decision>
 <category>UI/UX</category>
 <context>The Rx pad held the whole prescription in Alpine memory and only reached the server from Preview or Save & print. Complete visit is a page header action that fills its form from the stored record, so a doctor who wrote the script and then tapped the green button ended the visit with whatever had last been saved — usually nothing — and was told the visit completed. Nothing on screen said the pad was unsaved.</context>
 <action>The pad saves itself. `x-effect="queueDraftSave()"` debounces 1.5s after any change to the payload, `flushIfClickedAway()` flushes immediately on any pointerdown outside the pad, `visibilitychange` flushes, and `beforeunload` warns while dirty. Drafts go to a separate `ConsultScreen::autosaveRxDesk()` that skips the toast and the RxSafety re-check. A three-state badge (Unsaved / Saving… / Saved) sits with the actions, hidden while the pad is untouched. The signature is snapshotted at the end of `init()` so a pad nobody touched still saves nothing.</action>
 <reason>Autosave is the only fix that also closes the other two holes — a stale record read by Complete visit, and a remount discarding typed work — without asking the doctor to remember a button mid-consult. It is a separate entry point rather than a flag so the client cannot request the quiet version of an explicit save; a safety warning fired every two seconds is one the doctor learns to dismiss, and it rides the same toast channel as the allergy and duplicate-generic checks.</reason>
</decision>

<decision>
 <category>Code</category>
 <context>Dropping `updated_at` from the pad's `wire:key` stopped unrelated writes remounting it mid-consult, but that timestamp was also what made the post-save remount clean: a changed key replaces the element, so Alpine re-initialises the subtree consistently. With a stable key Livewire morphs instead, and `x-data="rxDesk({...})"` is rendered from the record — its attribute string changes after every save, re-running init against nodes whose effects are already torn down. Every x-show on the pad went dead: the complaint picker, the brand suggestions, the timing chips.</context>
 <action>`wire:ignore` on the pad, keyed on the booking alone, with `saveRxDesk()` and `autosaveRxDesk()` both `#[Renderless]`.</action>
 <reason>Alpine already owns this subtree outright, so the morph has nothing to contribute between patients — and when the patient does change, the key changes with them and Livewire replaces the element wholesale, which is the one moment a fresh mount is wanted. Renderless drops the HTML diff, not the dispatches or the return value, so notifications and the print URL still arrive.</reason>
</decision>

<decision>
 <category>UI/UX</category>
 <context>The Rx desk started at 1024px. Everything below fell back to the phone modal, which has none of the desk's speed work — no shorthand line, no My medicines, no packs, no complaint / history / investigation / advice chips. A tablet on the consult desk is an ordinary chamber setup, so those doctors were getting the older, slower pad.</context>
 <action>Desk turns on at 768px, the same breakpoint the rest of Consult Screen already uses for its grid, header actions and thumb strip. Its two columns stack to one below 1024px with the prescription first, and chips, inputs and row actions get touch-sized targets there. Desk follow-up also gained 3 months and Pick a date, which the modal always had.</action>
 <reason>One breakpoint instead of a third rule to keep in step, and one pad to maintain instead of two diverging ones. Stacking rather than scrolling is not a compromise: at 768px stacked, the medicine table gets the full content width, which is wider than the 66% column it gets at 1024px — and a horizontal scroller would clip the brand suggestion dropdown.</reason>
</decision>

<decision>
 <category>Business_Logic</category>
 <context>The printed sheet gave the pharmacist a frequency at one end of the row and a duration at the other, and left them to multiply. Medicines were also unnumbered, so neither doctor nor pharmacist could refer to one by position.</context>
 <action>Number the medicine list, and print a total dose count from `PrescriptionQuantity` — but only when both columns multiply out cleanly. `SOS`, `Continue` and anything free-typed print nothing, and one unreadable slot voids the whole line. Half doses round up.</action>
 <reason>It is arithmetic on the doctor's own instruction, not a clinical judgement. That is exactly why silence is the designed answer rather than a gap: a number on a prescription is read as the doctor's, so inventing one for a line they deliberately left open-ended is worse than leaving it blank.</reason>
</decision>

<decision>
 <category>UI/UX</category>
 <context>The patient's share link is read on a phone essentially always — it arrives by SMS or WhatsApp — but it rendered the doctor's A4 sheet: a 794px frame, 12–13px type, and a nowrap dosing column pressed against the brand. The dose itself was prescriber shorthand; a patient reading `1+0+1` has to be taught the three positions first.</context>
 <action>Below 640px the medicine list stops being a grid: one card per drug, number in the gutter, brand at 16px, dosing on its own row under a dashed rule. Each line carries the dose written out in Bangla via `DoseSchedule`, passed in as `$patientCopy` so the doctor's A4 print never grows the extra line. Added a Send on WhatsApp forward, rendered only on the share-link routes, and a written instruction to screenshot.</action>
 <reason>Same partial and same data as the print, so the two cannot drift — only the presentation changes at phone width. The WhatsApp button is gated because the portal route reaches the same view with the patient's phone number in the query string, and forwarding that would hand their number to whoever receives the prescription. Screenshotting is an instruction rather than a button because capturing the page as an image needs a canvas library the app does not ship, and every phone here already has the gesture.</reason>
</decision>

## 2026-08-13T23:47:28+0600

<decision>
 <category>UI/UX</category>
 <context>The ChamberQ sales homepage at `/` looked like a cream/teal flyer (Inter Tight, orbit rings, phone mockup), while the doctor’s patient site is a calm clinic: Instrument Serif + DM Sans, white page, pill buttons, pale grey cards. Doctors seeing the sales page did not recognise the websites we build for them.</context>
 <action>Restyle the live marketing homepage in the doctor-site design language without changing the sales story: same sections, copy, prices, WhatsApp CTAs, Maestro featured / Clinic beside / Rising Star hidden. Tokens, type, 1280px shell, square hero photo, Conditions-style cards, split FAQ, and one black About-style value band. Primary buttons stay ChamberQ blue (`#2563eb`). Locked solo patient homepage files were not edited. Find a doctor / Patient login pick up the shared `marketing.css` fonts and header.</action>
 <reason>Same rooms, new paint: the sales page should feel like a sample of the doctor website, not a second brand. Keeping WhatsApp, Maestro, and prices protects CRO; keeping ChamberQ blue (not the tenant sky `#30A9E5`) keeps product chrome distinct from a named doctor’s theme.</reason>
</decision>

## 2026-08-13T23:56:36+0600

<decision>
 <category>UI/UX</category>
 <context>The sales homepage had just been restyled to look like a doctor’s patient site (Instrument Serif, white hero, ChamberQ blue pills). The owner asked to use the ChamberQ proposal look instead — the teal cover, Bebas Neue headlines, and Helvetica Neue body that sales PDFs and the Maestro pitch already use.</context>
 <action>Restyle the live marketing homepage (`/` plus shared Find/login chrome) to the proposal language without changing the sales story: same sections, copy, prices, WhatsApp CTAs, Maestro featured / Clinic beside / Rising Star hidden. Teal cover hero, uppercase Bebas Neue + Helvetica Neue, mint featured Maestro card, teal value band. `--mk-blue` on this page is proposal teal `#0f766e` (not `#2563eb`). Locked solo patient homepage files were not edited. This supersedes the 2026-08-13T23:47:28+0600 doctor-site marketing restyle for this surface.</action>
 <reason>A doctor who already saw a ChamberQ proposal should land on a page that looks like that leave-behind, not like their own clinic site and not like a second brand. WhatsApp, Maestro, and prices stay so CRO is unchanged. Teal here is the sales identity; Filament admin can keep ChamberQ blue.</reason>
</decision>

## 2026-08-14T00:27:36+0600

<decision>
 <category>CRO</category>
 <context>The live sales homepage still opened with a patient benefit (“Give patients their time back”) and walked the visitor through the family’s booking path. The buyer is a solo doctor. The Maestro pitch already leads with order, reputation, and time back in the consult — not patient wait as the headline.</context>
 <action>Rewrite marketing homepage copy to speak to the doctor about their chamber: hero “Keep your chamber in order,” steps from front desk to consult, value as consult time / on-time sittings / your name travels. Same sections, prices, WhatsApp CTAs, Maestro featured / Clinic beside / Rising Star hidden. Find a doctor and Patient login stay in the nav as product doors. Locked solo patient homepage files were not edited. This supersedes the “same copy” part of the 2026-08-13T23:56:36+0600 restyle for this surface.</action>
 <reason>A doctor scanning ChamberQ.com should feel spoken to, the way the proposal already does — not like they landed on a patient waiting-room page. Patient wait still appears as a chamber metric (2 hrs → 15 min), not as the story.</reason>
</decision>

## 2026-08-14T01:12:58+0600

<decision>
 <category>UI/UX</category>
 <context>Half the website image fields in the tenant admin were still "paste a URL" boxes: Photo Gallery slides, testimonial avatars, the FAQ side panel, About Practice cards, Blog featured images, Department cards, public doctor photos, and the Branding logo/favicon. Only the hero and the video block had the picker added in 2026-08-13. A doctor or receptionist with a photo on their phone had no way to use it without first hosting it somewhere, so those sections stayed on stock Unsplash links.</context>
 <action>Every one of those fields is now the same Filament `FileUpload` (`PublicMediaFields::image`) writing to Laravel's `public` disk under a per-tenant folder — `webpage-gallery/`, `webpage-testimonials/`, `webpage-faq/`, `webpage-facility/`, `blog-images/`, `department-images/`, `doctor-photos/`, `branding-logos/`, `branding-icons/`. The disk path is promoted to a same-origin `/storage/…` src at the model boundary (`HasClinicContentFields`, `Doctor::saving`, `WebPage::saving`, `BrandingSettings::save`), never inside the upload component. A link pasted before today still shows until staff replace it. `PublicMediaFields::image` was narrowed from `image/*` to JPEG/PNG/WebP/GIF, and `PublicStoredImage::toPublicPath()` now refuses to prefix `/storage/` onto a value carrying a URL scheme.</action>
 <reason>Staff think in photos, not URLs, and the URL box was quietly costing us every section that needed a real picture of the chamber. Promoting the path on save (not in the field) is the rule `bug_history.md` already set after uploads were wiped by a `dehydrateStateUsing` rewrite. SVG is excluded because these files are served from this app's own origin, where a script inside one would run as us; the scheme guard exists so `javascript:` cannot be dressed up as a same-origin path and walk past `SafeUrl`.</reason>
</decision>

## 2026-08-14T10:04:24+0600
<decision>
  <category>Business_Logic</category>
  <context>Cross-chamber clinical history treated "same normalized phone + same normalized name" as the same person. One mobile is routinely a whole household's here — the booking wizard has a household picker for exactly that reason — and names repeat inside a family, so two relatives cross-matched. That is not only a privacy leak: it put another person's diagnoses and prescriptions in front of a doctor who was prescribing.</context>
  <action>`CrossTenantClinicalHistoryService::isSamePerson()` now also requires age agreement (exact `date_of_birth` when both sides have one, otherwise `displayAge()` within `AGE_MATCH_TOLERANCE_YEARS` = 1 to absorb `age_recorded_at` drift) and rejects a recorded sex that disagrees. **Fails closed** — no age on either side means no match. The NID path is unchanged and still matches on its own. To make the rule affordable, the booking wizard now asks for **age in whole years** (optional, not a date of birth), carried through `BookingController` → `BookingService` → `PatientService::resolveForBooking()`; it fills a missing age and never overwrites one a chamber recorded.</action>
  <reason>Age is what actually separates the people this feature kept colliding — a father and son sharing a name differ by decades. A doctor-confirmation step was considered and rejected by the owner: a chamber working through forty patients will not stop for an identity dialog, and a prompt everyone reflex-clicks is worse than none. Age was chosen over date of birth because patients here reliably know "42" and often not the date, and it is one keypad entry. Fail-closed costs history for chambers that never record an age, which is the correct direction to be wrong about a wrong-patient hazard.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>`add_share_clinical_history_to_patients_table` added the column with `->default(true)`, which backfilled every patient row that already existed to "sharing on". Those people registered before the consent checkbox existed, so their `true` was a column default rather than an answer — and what it opted them into was another chamber's doctor reading their diagnoses.</context>
  <action>Migration `2026_08_13_235900_reset_share_clinical_history_for_pre_consent_patients` sets the flag false for rows created strictly before the consent checkbox went live. `down()` deliberately does nothing.</action>
  <reason>Scoped by `created_at` so anyone who has booked since — and therefore saw the checkbox and had their real answer written by `PatientService` — keeps it. Reversing would re-assert consent nobody gave, which is the bug. Costs some cross-chamber history until those patients are asked properly at their next booking.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>`isOwnedReportPhotoPath()` validated stored report-photo paths, but `voice_path` and `photo_path` — written from the same browser form state — were streamed unchecked. Separately, `CrossTenantClinicalHistoryService` blanks media paths on live `VisitRecord` models and Consult Screen merges those foreign rows into the same collection as the chamber's own.</context>
  <action>Generalised the guard to `isOwnedMediaPath(/Users/chowdhuryjoy/.npm-global/bin /Users/chowdhuryjoy/.kimi-code/bin /usr/local/bin /System/Cryptexes/App/usr/bin /usr/bin /bin /usr/sbin /sbin /var/run/com.apple.security.cryptexd/codex.system/bootstrap/usr/local/bin /var/run/com.apple.security.cryptexd/codex.system/bootstrap/usr/bin /var/run/com.apple.security.cryptexd/codex.system/bootstrap/usr/appleinternal/bin /pkg/env/global/bin /opt/homebrew/bin /Users/chowdhuryjoy/.local/bin /Users/chowdhuryjoy/Library/Application Support/Claude/local-agent-mode-sessions/3f62bf5a-3551-4514-938f-aafdcd7d8231/18ba12dd-ec53-48a6-97c8-da4f76eb96f3/rpm/plugin_0155zZVATbJU3jHUmPP9NvMC/bin /Users/chowdhuryjoy/Library/Application Support/Claude/local-agent-mode-sessions/3f62bf5a-3551-4514-938f-aafdcd7d8231/18ba12dd-ec53-48a6-97c8-da4f76eb96f3/rpm/plugin_01Eeb9y5m4iFuY3yRtytYfdc/bin /Users/chowdhuryjoy/Library/Application Support/Claude/local-agent-mode-sessions/3f62bf5a-3551-4514-938f-aafdcd7d8231/18ba12dd-ec53-48a6-97c8-da4f76eb96f3/rpm/plugin_01VUWKAs3gYLNqeKbDtxv1Xs/bin /Users/chowdhuryjoy/Library/Application Support/Claude/local-agent-mode-sessions/skills-plugin/18ba12dd-ec53-48a6-97c8-da4f76eb96f3/3f62bf5a-3551-4514-938f-aafdcd7d8231/bin, )` with `isOwnedVoicePath()` / `isOwnedPhotoPath()` / `isOwnedReportPhotoPath()` wrappers, applied in `VisitMediaController::voice()` and `::photo()`. Foreign visit records are marked via `VisitRecord::markAsForeignChamberRecord()`, and a `saving` hook throws on them. `nid` removed from `PatientAccount::`.</action>
  <reason>**The media guard is defence in depth, not a live hole** — `FilesystemTenancyBootstrapper` already suffixes the `local` disk per tenant, so a foreign path resolves inside the viewing chamber's own root and is simply absent. It matters if that disk ever moves to S3, where the suffixing does not apply the same way, and it blocks `..` explicitly rather than relying on Flysystem. The read-only marking exists because saving a stripped foreign record would erase the real media paths in the chamber that owns them, and the merge into one collection makes that an easy accident. `nid` is treated as proof of ownership in `PlatformPatientHistoryService`, so an account must never be able to assert its own.</reason>
</decision>

## 2026-08-14T12:34:23+0600
<decision>
  <category>Business_Logic</category>
  <context>Owner changed Solo à la carte one-time setup: website cheaper, live queue the main setup cost, prescription a small add-on. Monthly fees were left alone so a doctor already quoted ৳1,000 / ৳2,000 / ৳0 per month is not surprised.</context>
  <action>Update `config/marketing.php` module setup defaults (and `.env.example`): Front door ৳3,000, Live queue ৳12,000, Prescription ৳2,000. Monthly stays ৳1,000 / ৳2,000 / ৳0. All-three Maestro bundle stays ৳15,000 setup / ৳3,000 mo (now ৳2,000 off the ৳17,000 unit sum). Partial combos still sum units. Clinic unchanged. Existing tenant `setup_amount_due` snapshots are not rewritten unless Super Admin changes that tenant's plan, discount, or modules.</action>
  <reason>The public pricing table, Super Admin helper, and billing snapshots all read this config. Keeping the bundle sticker means "full package" sales copy does not move. Doctors already billed keep their quoted setup until someone deliberately re-prices them.</reason>
</decision>

## 2026-08-14T12:55:46+0600

<decision>
  <category>CRO</category>
  <context>Owner wants a deadline pull to convert solo doctors during the launch window: anyone who commits to the website before 31 August gets the prescription module free for life.</context>
  <action>Launch offer copy on the Maestro pricing story: "Get your website by 31 August and Prescription is free for life (৳2,000 setup waived)." Written into the Maestro sales proposal (`docs/proposals/Maestro-ChamberQ-Proposal.md` + `.html` — module table with all-three = Maestro bundle row, plus the offer note) and the live marketing homepage pricing section (`resources/views/marketing/partials/pricing.blade.php` with a new `.mk-offer` band in `public/css/marketing.css`). MarketingLandingPageTest asserts the offer copy. Config and billing prices are unchanged — the offer is sales copy, not a config change; discount handling still flows through the normal discount code path.</action>
  <reason>A dated offer gives sales and the WhatsApp close a concrete reason to act now; it rides the existing modules table (website + prescription are both rows there) without moving any sticker price or billing logic. Kept as copy rather than config because it is a temporary promotion, and config stays the single source for list prices.</reason>
</decision>

## 2026-08-14T12:59:37+0600

<decision>
  <category>Business_Logic</category>
  <context>Owner re-priced the prescription module (৳2,000/৳0 → ৳5,000 one-time / ৳250 monthly) and expanded the launch offer: the website-by-31-August deal now waives the prescription monthly fee as well as the setup fee — free for life means both.</context>
  <action>`config/marketing.php` + `.env.example`: `prescription` module setup ৳5,000, monthly ৳250. Maestro bundle stays ৳15,000 / ৳3,000 — the setup discount vs the ৳20,000 unit sum is now ৳5,000 (was ৳2,000 off ৳17,000), and monthly bundle (৳3,000) is ৳250 below the ৳3,250 unit sum. Offer copy everywhere reads "Prescription free for life (৳5,000 setup + ৳250/month waived)" for website signups by 31 August. Updated: Maestro proposal (`.md` + `.html`), marketing homepage pricing partial (`.mk-offer` band + modules table via config), Super Admin module helper text, Client Guide, Marketing Playbook, Developer Handoff, CHANGELOG, `architecture.md`, `ModulePricingTest` (prescription-only 5000/250; website+prescription 8000/1250; tenant-read 5000/250), `MarketingLandingPageTest` offer assertion. Existing tenant `setup_amount_due` / monthly snapshots are not rewritten unless Super Admin re-prices that tenant.</action>
  <reason>The owner set both numbers; the bundle sticker is untouched so "full package" sales copy and already-quoted tenants do not move. Because the offer now waives a recurring fee, it is a real billing caveat, not just copy — but it is still executed as sales copy, not config: the pricing source of truth keeps one shape, and a waived ৳250/month is the sales team's call to honour, exactly like a discount code.</reason>
</decision>

## 2026-08-14T13:03:37+0600

<decision>
  <category>CRO</category>
  <context>Owner wants a second dated incentive aimed at annual commitment: confirming one year of payment upfront earns 50% off the one-time setup fees, for signups confirmed before 30 September.</context>
  <action>Added a **Prepaid-year offer** next to the launch offer on the Maestro proposal (`docs/proposals/Maestro-ChamberQ-Proposal.md` + `.html`) and the marketing homepage pricing section (a second `.mk-offer` band in `resources/views/marketing/partials/pricing.blade.php`): "Confirm one year of payment before 30 September and every one-time setup fee is 50% off (Maestro setup ৳15,000 → ৳7,500)." MarketingLandingPageTest asserts the copy. No config or billing change — it is executed as sales copy, with the actual discount handled through the normal discount path (like the launch offer).</action>
  <reason>An annual-prepayment sweetener converts doctors who are already sold on the package but hesitating on when to start, and the 30 September deadline pairs with the 31 August launch offer to keep both close dates on the page without changing sticker prices. Copy-only keeps the config as the single source for list prices.</reason>
</decision>

## 2026-08-14T13:38:25+0600

<decision>
  <category>Business_Logic</category>
  <context>A prescription-only client has no Live Queue Control and an empty Consult Screen, so they cannot reach Print / WhatsApp / SMS (`prescription-share-actions`). Visiting / camp only offers Save & print. Owner confirmed the printed sheet is enough — do not add WhatsApp/SMS send on that page.</context>
  <action>Keep Visiting / camp print-only. Share buttons stay on Live Queue Control and Consult Screen after complete (Maestro / live-queue consult). Upload stores the visit; it does not send SMS. Drop the leftover “SMS waits until then” copy on Visiting / camp so the screen does not promise a send that never happens.</action>
  <reason>Paper in the patient’s hand is the real camp/Rx-only handoff. WhatsApp/SMS from the desk belongs to the queued consult, where the patient is still in the room after Complete visit.</reason>
</decision>

## 2026-08-14T14:15:38+0600

<decision>
  <category>Business_Logic</category>
  <context>Owner lowered the prescription module's one-time setup from ৳5,000 to ৳2,500 so the Rx-only entry price is a smaller add-on for doctors who are not buying the full Maestro bundle. Monthly fee stays ৳250.</context>
  <action>`config/marketing.php` `modules.prescription.setup` default and `.env.example` `MARKETING_MODULE_PRESCRIPTION_SETUP` 5000 → 2500. Monthly unchanged. Maestro bundle sticker (৳15,000 / ৳3,000) unchanged — the all-three setup discount vs the ৳17,500 unit sum is now ৳2,500. Offer copy ("Prescription free for life — ৳2,500 setup + ৳250/month waived"), Super Admin module helper text, Maestro proposal (`.md` + `.html`), Client Guide, Marketing Playbook, and tests updated (`ModulePricingTest` prescription-only 2500/250, website+prescription 5500/1250, tenant-read 2500/250; `MarketingLandingPageTest` offer line). Existing tenant `setup_amount_due` / monthly snapshots are not rewritten unless Super Admin re-prices that tenant.</action>
  <reason>Lower one-time cost lowers the barrier for the Visiting/camp Rx-only workflow (verified sellable standalone) while keeping recurring revenue at ৳250/mo; the bundle sticker is untouched so quoted Maestro tenants and "full package" copy do not move.</reason>
</decision>

## 2026-08-14T22:39:27+0600

<decision>
 <category>Business_Logic</category>
 <context>Sales quotes Maestro/modules plus two launch offers, but Super Admin still said “Solo Doctor”, showed list/due without the partner’s cut, and could not tick the offers — so confirming payment at the sticker over-paid commissions. Changing modules before the doctor paid also left the pending commission on the old amount.</context>
 <action>Super Admin tenant form labels `solo` as **Maestro**. Module helper text reads `config/marketing.php`. Two tenant flags: `offer_prescription_lifetime_free` (waive Rx units on Solo when Prescription is included) and `offer_prepaid_year_setup` (50% off setup after other discounts). Live preview shows list → due plus partner setup/monthly commission. `PlanPricingService::quote()` is the single path; Clinic list is unchanged by Rx-free. `syncPendingCommissions()` updates **pending** rows only. Header action **Confirm 12 months prepaid** after setup is paid. Doctors list and partner referred list show Maestro/Clinic, module chips, and due amounts. Existing tenant snapshots are not rewritten unless that tenant is edited.</action>
 <reason>The back-office cashbook has to match the WhatsApp quote. Offer ticks persist so monthly commission stays waived for life; a percent discount code cannot waive one module. Recalculating pending (never owed/paid) keeps the partner’s ledger honest when the package changes before the doctor pays.</reason>
</decision>

## 2026-08-14T22:39:16+0600

<decision>
 <category>Business_Logic</category>
 <context>Sales quotes Maestro, modules, and two launch offers on WhatsApp, but Super Admin still said “Solo Doctor”, showed no partner commission until after save, and could not tick the offers — so due amounts and commissions often matched the sticker instead of the deal.</context>
 <action>Super Admin tenant form labels `solo` as Maestro; module helper text and amount preview read live config; preview includes partner setup/monthly commission. Two saved ticks: Prescription free for life (waive Rx units on Solo when Rx is included) and prepaid-year 50% off setup. Quote order: module list → Rx-free → percent discount code → 50% setup. Pending commissions refresh on re-price. Header action confirms up to 12 monthly payments + owed commissions after setup is paid. Doctors list and partner referred list show Maestro/Clinic, module chips, and due amounts. Prescription list price stays ৳2,500 / ৳250. Existing tenant snapshots are not rewritten unless that tenant is edited.</action>
 <reason>The back-office cashbook has to match the WhatsApp quote. Offer ticks persist so monthly commission stays waived for life, which a one-off “amount paid” box cannot do. Percent discount codes cannot waive one module, so the ticks are not a discount-code workaround.</reason>
</decision>

## 2026-08-14T23:45:02+0600

<decision>
 <category>UI/UX</category>
 <context>Super Admin on a phone put Restore and Delete next to Confirm paid — same red weight, easy to tap the wrong one. The dashboard said SOLO in all-caps, colourless stats, a login-account card, and Client Health listed clinics with no way to open them.</context>
 <action>Restore and Delete sit in a **Dangerous** overflow (same pattern as Live Queue Control’s Session actions). Platform restore defaults to dry-run; the live button is labelled **Upload and restore platform data** and still needs REPLACE. Download/restore buttons disable while working. Dashboard order is finance → platform totals → latest 8 tenants (Maestro/Clinic, no AccountWidget). Amber and sky colours are registered so overdue/SMS stats tint. Tenants sit first under **Platform** with filters and extra columns hidden on phones. Client Health names link to tenant edit and show phone. Copy referral link writes the clipboard.</action>
 <reason>Like a cash drawer: everyday buttons stay in reach; the shredder lives in a marked folder. Dry-run-first means a mis-click checks the ZIP instead of wiping the platform. Maestro on the dashboard matches the quote the salesperson already sent.</reason>
</decision>

<decision>
 <category>Business_Logic</category>
 <context>Super Admin platform restore opened with dry-run off and replace on, so submitting the form without reading the checkboxes would wipe central tables.</context>
 <action>`DataBackup` and `TenantBackupActions` default `dry_run` to true; a missing checkbox fails closed as dry-run. The REPLACE confirmation field only appears for a live replace. Chamber Admin Data backup is unchanged (still opt-in dry-run).</action>
 <reason>A Super Admin restore is a platform-wide write. Checking the ZIP first is the safe default; writing stays a deliberate uncheck plus typed confirmation.</reason>
</decision>

## 2026-08-15T00:23:45+0600

<decision>
  <category>UI/UX</category>
  <context>A Super Admin UX pass had just landed. Testing it in the browser rather than reading it showed three things still wrong: both backup cards had lost their inner padding, the new danger colour on the platform restore never actually painted, and the Tenants list still put Edit off-screen at both 1280 and 375.</context>
  <action>Restore the `.backup-card-body` padding rule. Key the platform restore submit on the dry-run state (`wire:key="restore-submit-dry|live"`) so Livewire replaces the button instead of morphing it, and add a red callout plus a `wire:confirm` naming exactly what a live replace wipes. On the Tenants list, move Edit + Download chamber backup into a row `ActionGroup`, start Modules / Marketer / Setup due / Monthly due / Domains toggled off, push Tier and Billing to `sm`, and wrap the name column.</action>
  <reason>The colour cue alone was not survivable: the class was correct and the paint was stale, so the guard has to be something rendered fresh (callout) or handled outside CSS (confirm dialog). For the table, `visibleFrom` is viewport-based and cannot account for the ~380px sidebar, so the only reliable lever was reducing total column width — and every column now hidden is still one toggle away and already visible on tenant edit or Client Health.</reason>
</decision>

## 2026-08-15T02:15:06+0600

<decision>
  <category>UI/UX</category>
  <context>A visual pass on Super Admin Create/Edit Tenant found mismatched two-column fieldsets leaving hundreds of pixels of dead space, a 250-character module-price helper that outranked the checkboxes, and Research filters that did not match Filament control height.</context>
  <action>Stack the tenant form as one column of fieldsets (each fieldset still uses its own two-column grid for its fields). Put module unit prices on each checkbox via `PlanPricingService::modulePriceDescriptions()` and leave the helper as the Clinic caveat only. Shorten the longest remaining helpers. Render Research date/plan filters with Filament `.fi-input-wrp` / `.fi-input` / `.fi-select-input` instead of a second hand-rolled control style.</action>
  <reason>Pairing a tall Referral block with a short Appearance block made the money preview look like leftover space. Putting prices next to each module is scannable; a run-on helper is not. Reusing Filament chrome means Research cannot drift to a third height.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>Create Tenant declared defaults for Plan Tier, Slot Cap, Billing, SMS, theme, and locale, but Super Admin still saw “Select an option” / empty boxes because referral prefill called `form->fill($prefill)`.</context>
  <action>Move referral prefill to `afterFill()` and apply it with `fillPartially(..., shouldFillStateWithNull: false)`. Do not call `fill()` with a partial array after the parent has already hydrated defaults. Custom-domain repeater starts at zero rows so an unused optional domain cannot fail Create.</action>
  <reason>`fill()` replaces the whole Livewire state; `getState()` during mount would also validate empty required fields. `fillPartially` is the Filament API for “overlay these keys only.”</reason>
</decision>

## 2026-08-15T02:49:20+0600

<decision>
  <category>UI/UX</category>
  <context>The 2026-08-13T21:43:23 Getwebfield-style tenant-admin shell closed with “Super Admin and marketer panels are unchanged.” Super Admin at `/admin` was still stock Filament (Inter, global topbar, expanded sidebar, no `--fi-shell-*` tokens). The owner now wants Super Admin to follow that shell. Marketer (`/partner`) was not asked for.</context>
  <action>Port the tenant-admin chrome to Super Admin only: extract `resources/css/filament/shared/admin-shell.css`, add `superAdmin/theme.css`, `viteTheme()`, `topbar(false)`, `maxContentWidth(Width::Full)`, `sidebarCollapsibleOnDesktop()` with the same localStorage/Alpine closed-by-default hooks, and outlined ungrouped table row actions. Do not port `HasPrimarySaveAndDangerDelete` (tenant edit already has Confirm setup/monthly/prepaid, Top up SMS, Download backup, and Restore/Delete behind Dangerous), database notifications, the offline-shell hook, or page-builder button styling. Marketer panel stays stock Filament.</action>
  <reason>One operator desk should not learn two admin chrome languages. Tenant edit’s six contextual actions do not fit a Save-as-header / Delete-in-footer page. Changing `/partner` without being asked would be scope creep.</reason>
</decision>

<decision>
  <category>UI/UX</category>
  <context>Super Admin dashboard registered custom `amber` and `sky` palettes. Live `--amber-600` was byte-identical to Filament `--warning-600`, and `--sky-600` sat a hair from `--info-600`, so “Commissions owed” (a liability) and “Net platform revenue” (profit) painted the same colour, and two unrelated counts shared the other.</context>
  <action>Drop the custom keys; keep only `'primary' => Color::Blue`. Reassign the nine dashboard stats onto Filament’s six stock keys: warning only on commissions owed, primary only on net platform revenue, gray for plain counts, success/info unchanged. Custom-domains badge on Recent Tenants becomes gray beside the info platform-path badge.</action>
  <reason>Colour should carry meaning. Repeating gray on three plain counts is the same category; repeating amber/warning across a liability and a profit figure was an accident.</reason>
</decision>

## 2026-08-15T09:49:04+0600

<decision>
  <category>UI/UX</category>
  <context>The collapsed-sidebar control next to the page title (DASHBOARD) was Filament’s default right-pointing chevron. Staff read that as “go forward”, not “open the menu” — the three-line hamburger is the pattern they already know from phones.</context>
  <action>Register `Heroicon::OutlinedBars3` for Filament’s sidebar expand/collapse icon aliases (including RTL) on every admin panel: tenant (path + domain), Super Admin, and marketer. Shared in `UsesHamburgerSidebarToggle`. Nav-group chevrons stay chevrons. Marketer chrome is otherwise unchanged.</action>
  <reason>One menu button, one meaning. A chevron next to a title looks like breadcrumb navigation; a hamburger looks like a menu. Putting the mapping on every panel so a later shell change cannot reintroduce the chevron on one desk.</reason>
</decision>

## 2026-08-15T10:30:45+0600

<decision>
  <category>Business_Logic</category>
  <context>Patients leave for tea with the ticket minimised and the phone locked. SMS will not be used for “you’re next”, and WhatsApp will not be used for this case either (human-tapped wa.me stays for other stages). A Play Store patient app is out of scope — the ticket is the app.</context>
  <action>Open ticket: Bangla banners plus vibrate (and a chime if the browser allows) at two people away, next (ahead ≤ 1), and called. Closed/locked phone: one Allow tap stores a Web Push subscription on that ticket UUID; Call next then pushes Bangla copy. The service worker skips the system notification when a visible /bookings/ tab already exists, so the page buzzes once. If Allow is blocked, iPhone without Add-to-Home-Screen, or VAPID keys are missing, copy is honest: come at ticket time or sit by the TV. Staff still only tap Call next. Front-door-only tickets omit this. SendQueueApproachPushes uses afterResponse because production has no queue worker. Same stage is never sent twice.</action>
  <reason>The realistic patient pockets the phone. SMS costs credits and was ruled out for this case; WhatsApp Business API was ruled out. Web Push on Android Chrome after one permission tap is the only way to reach a locked phone from the ticket. Bangla is forced on this copy so an English admin locale cannot send English into a patient’s pocket.</reason>
</decision>

## 2026-08-15T13:06:00+0600

<decision>
  <category>Business_Logic</category>
  <context>Evening sittings slip in real life. Staff still press Start — the ticket is the board. Patients should see the published sitting window unchanged, but Estimated Time and the yellow delayed banner must tell the truth once someone actually begins. Staff need one sticky note when a sitting is overdue, not a repeating alarm or patient SMS.</context>
  <action>Ticket follows the chair: humans Start (no auto-Start at sitting time). `LiveSession::effectiveStartTime()` — not started + delayed uses sitting + announced delay; started uses max(sitting, started_at) + pause; `delay_minutes` kept as “what we told them”. `SittingPrompt` (10-minute grace) drives sticky callouts on Daily Roster, Live Queue Control, and Consult Screen. Start after sitting time asks Mark Late / Just start / Cancel; inside an announced delay asks Start now / Wait. Mark Late on `delayed` sittings only with a larger total (Add time). No new DB columns; no staff push.</action>
</decision>

## 2026-08-15T13:55:38+0600

<decision>
  <category>Business_Logic</category>
  <context>Five queue gaps after honest late sitting: staff could not see when a schedule was unrealistically tight; booking SMS gave serial + date but not come-around; desk could not seat walk-ins after the published cap; Pause still let Call next run; sticky notes only helped when the panel was open.</context>
  <action>Shipped in order: (1) `ScheduleSessionPace` + live “about X minutes each” on the sitting form (amber under 5 min). (2) `PublishedComeAround` on booking SMS, wizard confirm flash, and ticket before a live row — one SMS segment; overflow gets “After serial N”. (3) `walk_in_overflow_cap` on sittings (default 0) + `bookings.is_overflow`; online stays at published cap; staff walk-ins pass `allowOverflow`; Call next finishes published serials before stools; Call now can jump a stool. (4) Pause renamed **Doctor stepped out** / **He's back**; service blocks Call next/Call now while paused; Consult Screen gets the same taps; `idle_after_start` sticky after Start when nobody called (10-minute grace). (5) Staff pocket buzz: `staff_push_subscriptions`, `POST /api/staff/push`, `SendStaffSittingPromptPushes` + Filament bell when a sticky note appears or changes kind — card on Daily Roster and Live Queue Control. Honest late sitting and ticket-follows-chair unchanged.</action>
  <reason>Each piece matches how a real Friday evening runs: Fatima needs come-around in her pocket before Start; the neighbour’s child needs serial 31 without breaking online “full”; prayer break must freeze the line; the runner’s phone should buzz when the laptop is closed. Staff push is a new surface — it does not replace the 13:06 decision that v1 honest late sitting had no staff push.</reason>
</decision>

## 2026-08-15T14:16:09+0600

<decision>
  <category>Business_Logic</category>
  <context>Friday evening in Chattogram: the line drops while forty people watch the TV and reception still has the laptop open. The 2026-08-13 offline kit froze Call next entirely — correct for two unsynced machines, wrong when one desk laptop drives the TV over HDMI or the runner is signed in on the TV browser.</context>
  <action>Outdoor screen keeps last-known-good state (localStorage + corner chip), self-hosted Inter/Hind Siliguri fonts, and SW precache of fonts/announce clips (`clinic-shell-v8`). Queue runner may Call next / arrived / skip / complete-without-advance offline on **this computer** via `chamberq-queue-offline.js` + `GET /api/offline/queue/{session}`; events replay through `POST /api/offline/sync` with `expected_current_booking_id` conflict stop (`offline_queue_events`). Walk-in, Mark Late, End session, and SMS stay frozen offline. Tenant admin follows `tenant.default_locale` (Filament `bn` + `lang/bn.json`, Hind Siliguri, user-menu language switch); Branding relabelled **Chamber language**.</action>
  <reason>One machine can honestly run the room without inventing a LAN product. Two machines still cannot share one offline queue — conflict means refresh. Bangla desk matches how solo chambers actually operate; patient homepage content stays the paid add-on.</reason>
</decision>

## 2026-08-15T14:26:41+0600

<decision>
 <category>Code</category>
 <context>The sales homepage assembled prices, WhatsApp numbers, and partner referral codes inside the Blade template. Clinic Departments/Blog set the sidebar folder with a trait property that collides with Filament's inherited `$navigationGroup`. Blocked URLs showed Laravel's grey Forbidden/Not Found screens.</context>
 <action>`MarketingController@home` prepares a sanitised payload (digits-only WhatsApp, allowlisted marketing images, referral/discount suffixes only when they match `[a-z0-9-]{1,50}`) and passes it to the existing marketing blades. `LocaleController` owns `/lang/{locale}` on central and tenant (same-host Referer only). `ClinicWebsiteResource` uses `getNavigationGroup()` instead of a trait property. Branded HTML pages live at `resources/views/errors/{403,404,419,429,500,503}.blade.php`; JSON 403/404 stay JSON.</action>
 <reason>A shop window should not reach into the back office. Filament's documented trait pattern is a method, not a colliding static property. A locked door should still look like ChamberQ, not a framework stamp.</reason>
</decision>


## 2026-08-15T14:46:39+0600 — production audit

<decision>
  <category>Code</category>
  <context>A full-codebase audit before serving real patients. The findings that mattered were all the same shape: a guard that existed on one path and was assumed everywhere. `PlatformPatientHistoryService` opted out of the tenant scope and then built its filter conditionally, so an account with nothing to match on got an unfiltered `select * from bookings`. Clinic HTML was sanitised in a model hook that `DataImportService` bypasses. Push endpoints were validated for URL *shape* while the server treated them as a *destination*.</context>
  <action>Fail closed and guard where the code converges. `bookingsForAccount()` returns empty when it has neither a valid phone nor a matching patient id. The three clinic detail blades call `HtmlSanitizer::clean()` inline, matching `rich_text.blade.php`, so the render boundary holds regardless of how the row was written. `App\Support\PushEndpoint` gates both push-subscribe routes (https, no userinfo, port 443, no private/reserved IP literal, no `localhost`/`.local`/`.internal`/single-label host). `InitializeTenancyForTenantHosts` restricts its `Referer` fallback to `livewire/*` on this same host and no longer lets a database fault turn a 404 into a 500. Patient logout invalidates the session. `PatientOtpService` prunes that phone's spent codes on each send. New migration `2026_08_15_160000_add_phone_lookup_indexes` adds `bookings (patient_phone, tenant_id)` and `patients (phone, tenant_id)`.</action>
  <reason>This repo's own rule is that a rule a future author must remember has already failed. Every fix here moves the check to the place the code has to pass through — the render call, the shared validation rule, the early return — rather than adding a second place to remember. Phone-first index order is the one that serves both the cross-tenant locker and the tenant-scoped portal from a single key.</reason>
</decision>

<decision>
  <category>Code</category>
  <context>Web Push endpoints are attacker-chosen URLs the server later POSTs to, and the patient route is unauthenticated because booking a serial is public. The two robust options were an allowlist of known push hosts (FCM, Mozilla, Apple, WNS) or a deny-rule for internal destinations.</context>
  <action>Deny-rule, not allowlist, and **no DNS resolution** at validation time.</action>
  <reason>An allowlist silently kills pocket buzz the day a browser ships a new push host, and this feature already degrades quietly (missing VAPID keys no-op through `NullWebPushSender`) so nobody would notice. Resolving DNS at subscribe time is a race, not a control — the connect-time address is what matters — and it would make subscribing fail whenever DNS is slow. The residual risk (a public hostname whose record points inside) is recorded in `PushEndpoint`'s docblock rather than papered over.</reason>
</decision>

## 2026-08-15T21:12:17+0600

<decision>
  <category>Code</category>
  <context>Supersedes nothing in substance, corrects the mechanism: the 2026-08-05 entry above kept `AuthDebugProvider` installed, gated behind `AUTH_DEBUG`, to capture the real cause of the owner's repeated sign-outs after measurement disproved idle expiry. That gate was `env()` at the call site, and `config:cache` stops `.env` being loaded — so the instrumentation was permanently dark in production, the one environment the symptom has ever been reported in. The owner was offered deletion and chose to make it work instead.</context>
  <action>Both diagnostics stay. The flag moves to `config/diagnostics.php` and both call sites read `config('diagnostics.auth')`. `.env.example` documents `AUTH_DEBUG=false` and says to turn it back off after use. `SourceHygieneTest` now fails any `env()` read outside `config/` so this cannot recur anywhere.</action>
  <reason>The logged decision was right about needing evidence; the implementation quietly guaranteed there would be none. Deleting it would have left the next occurrence to be guessed at again, which is the exact fix/revert ping-pong that decision existed to stop. Note `.env` on the owner's machine currently has `AUTH_DEBUG=true`, so that box is logging every request — intended while chasing the report, not a standing state.</reason>
</decision>

<decision>
  <category>Business_Logic</category>
  <context>The booking confirmation SMS was sent inside the patient's own request, after the serial was committed. `HttpSmsGateway` waits ten seconds for the aggregator, so on a slow evening every patient watched a spinner on a booking that had already worked — and a second tap on Confirm is what that uncertainty produces.</context>
  <action>`SendBookingConfirmation`, dispatched from `BookingService` with `->afterResponse()`. It re-reads the booking and skips one cancelled between the response and the job running, and swallows a throwing gateway into the log so an outage cannot surface as a 500 on a booking that succeeded. The wallet debit moves with the send.</action>
  <reason>`->afterResponse()` rather than the queue, matching `SendDoctorLateNotices`: **no queue worker runs**, so a queued confirmation would never be delivered and no patient would learn their serial — a silent failure far worse than the spinner it replaced. `ShouldQueue` and the tenant id are carried so adding a worker later is deleting one call. The two HTTP booking tests are what prove the SMS still goes out; direct service calls in tests must `$this->app->terminate()`, as `NotifyChannelsTest` already does.</reason>
</decision>

## 2026-08-15T23:41:08+0600

<decision>
 <category>Code</category>
 <context>Local demo logins used the Laravel default `password`, which is easy to forget and longer than needed on a single-operator laptop.</context>
 <action>Every seeded staff login (Super Admin, Solo, Clinic, Nusrat Urmi) now uses `pass`. Seeders `updateOrCreate` the user so re-seeding actually applies the new password instead of leaving the old hash on an existing row. README demo table matches. Tests and the user factory still use `password` so the automated suite is unchanged.</action>
  <reason>Owner asked to type `pass` at every demo login. A four-character password is only for this local demo machine — production and any shared chamber must still rotate it.</reason>
</decision>

## 2026-08-15T23:46:24+0600

<decision>
 <category>UI/UX</category>
 <context>Switch to Bangla started applying, but the desk still looked English: Filament prints sidebar labels as painted signs, and `lang/bn.json` only had a handful of staff strings.</context>
 <action>Traits run nav / title / model labels through `__()` at render. Live Queue, Daily Roster, dashboard widgets, and sitting notes use `__()` at the call site. Desk strings added to `lang/bn.json`. `StaffDeskBanglaTest` fails CI if one is missing. Rx-pad clinical shorthand (C/C, H/O, O/E) and Branding / Web Pages form labels stay English this round.</action>
 <reason>Like unlocking the language drawer but leaving the signs on the walls in English. Daily desk (sidebar, queue, roster, today's numbers) is what staff stare at for hours. Machine-translating the whole Rx pad needs a doctor's read before it should ship.</reason>
</decision>

## 2026-08-16T00:01:07+0600

<decision>
 <category>UI/UX</category>
 <context>Owner correction: sidebar, topbar, and buttons do not need translation. Staff already know Call next / Finish / End Session in English. The 23:46 pass had translated those knobs as well as the reading copy.</context>
 <action>Sidebar group labels, page titles (`$navigationLabel` / `$title`), and action buttons stay hardcoded English. Filament vendor chrome (Save / Search / Sign out) stays English via `EnglishFilamentLoader` (when locale is `bn`, `filament*` namespaces load `en`). Reading copy still follows chamber language: Waiting / Seen / No-show, session badges, empty states, sitting-note text, dashboard widget headings, table column headers, form field labels, notification bodies. Traits `TranslatesStaffChrome` / `TranslatesResourceChrome` removed. `BanglaStaffPanelTest` asserts the Finish / End Session button stays English on a Bangla desk.</action>
 <reason>Like a Bangla recipe card next to a stove whose knobs still say Start / Off. Translating the knobs makes a trained operator hunt for a new name for a control they already know. Supersedes the 23:46 action that ran nav / title labels through `__()`.</reason>
</decision>
