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
