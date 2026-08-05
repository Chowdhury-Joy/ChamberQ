# Bug History

## 2026-07-27

<bug>
 <category>Code</category>
 <symptom>Booking wizard could inject scripts if doctor/chamber/session names contained HTML (DOM XSS via `innerHTML`).</symptom>
 <root_cause>Session and lab-slot cards were built with template strings assigned to `innerHTML`, interpolating DB-backed names.</root_cause>
 <prevention_rule>Never build booking/screen UI cards with `innerHTML` + untrusted strings; use `textContent` / `createElement` only.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Patient portal could return other people's bookings when searching by partial phone.</symptom>
 <root_cause>Lookup used loose `LIKE '%phone%'` matching without normalization or tight throttling.</root_cause>
 <prevention_rule>Portal phone lookup must use exact match on normalized BD variants (`01` / `88` / `+88`) and stay rate-limited.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Setting a feature flag value to the string `"false"` still enabled the feature.</symptom>
 <root_cause>PHP `(bool)"false"` is `true`; Filament KeyValue stores booleans as strings.</root_cause>
 <prevention_rule>Always parse feature flags with `filter_var(..., FILTER_VALIDATE_BOOLEAN)` (or equivalent), never raw `(bool)` casts on flag values.</prevention_rule>
</bug>

## 2026-07-28

<bug>
 <category>UI/UX</category>
 <symptom>Live Queue Control labeled a called patient as "Currently serving" while the badge correctly showed "Called — Waiting for Patient".</symptom>
 <root_cause>The section heading was hardcoded to "Currently serving" for every current booking, ignoring status (`called` vs `in_chamber`).</root_cause>
 <prevention_rule>Never label a patient as "Currently serving" unless their booking status is `in_chamber` (staff clicked Patient arrived).</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>Operational Reports looked like an unstyled flat list of labels and numbers; summary counts were also duplicated.</symptom>
 <root_cause>Blade used Tailwind utilities that are not present in the Filament panel's precompiled CSS (no `viteTheme()`), so layout classes were dropped; cards and status row repeated the same totals.</root_cause>
 <prevention_rule>Custom Filament panel pages without a Vite theme must use scoped CSS on Filament CSS variables — never assume arbitrary Tailwind utilities exist in the panel.</prevention_rule>
</bug>

## 2026-07-31

<bug>
 <category>Code</category>
 <symptom>Super Admin dashboard at `/admin` crashed with `TenantScope applied to bookings outside an initialized tenancy context`.</symptom>
 <root_cause>`SuperAdminStatsOverview` counted `Booking`, `Doctor`, and `WebPage` on the central domain without bypassing `TenantScope`, but those models require initialized tenancy during HTTP requests.</root_cause>
 <prevention_rule>Any Super Admin aggregate over tenant-scoped models must use `withoutGlobalScope(TenantScope::class)` — central panels never run inside initialized tenancy.</prevention_rule>
</bug>

## 2026-07-31

<bug>
 <category>Code</category>
 <symptom>Visiting `/{tenant}/admin` (e.g. `/solo/admin`) crashed with `Missing required parameter: tenant` when redirecting to Filament login.</symptom>
 <root_cause>Laravel middleware priority ran `Authenticate` before `SetPathTenantUrlDefaults`, so login URL generation had no `{tenant}` default. Early `SetPathTenantUrlDefaults` also called `session()` before `StartSession`.</root_cause>
 <prevention_rule>Path-tenancy URL defaults must be registered in high-priority middleware (before Filament `Authenticate`) and must guard all `session()` calls with `$request->hasSession()`.</prevention_rule>
</bug>

## 2026-07-31

<bug>
 <category>Code</category>
 <symptom>Platform path URLs (`/{slug}/bookings/{uuid}`, `/{slug}/api/queue/...`) returned 500 while the same routes on custom domains worked. Outdoor screen on path silently stayed `"scheduled"`.</symptom>
 <root_cause>`routes/tenant.php` called `->middleware()` twice on the path group; Laravel's RouteRegistrar assigns (does not merge) middleware, so the second call dropped the entire `web` group (session, CSRF, SubstituteBindings, Localization).</root_cause>
 <prevention_rule>Never call `->middleware()` twice on the same RouteRegistrar chain — merge into one array. Verify path vs domain middleware lists when adding tenant routes.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Tenant slugs that merely start with a reserved word (`bookna`, `screening`, `uposhom`, …) 404 forever after Super Admin creates them.</symptom>
 <root_cause>`TenancyUrl::tenantSlugPattern()` used `(?!admin|partner|…$)` where `$` only anchored the last alternative, so any prefix match failed the lookahead.</root_cause>
 <prevention_rule>Always wrap reserved path segments as `(?!(?:a|b|c)$)` — never `(?!a|b|c$)`.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>SMS booking confirmation for path-only tenants (no Domain row) linked to `/{bookings/uuid}` on the central host — a route that does not exist.</symptom>
 <root_cause>`SmsService::ticketUrl` fell back to `url('/bookings/'.$id)` when no Domain was found, ignoring path tenancy.</root_cause>
 <prevention_rule>Path tenants must use `route('path.bookings.show', ['tenant' => …, 'booking' => …])`; custom domains keep host-based URLs.</prevention_rule>
</bug>

## 2026-07-31 (audit fixes)

<bug>
 <category>Business_Logic</category>
 <symptom>Re-confirming setup/monthly payment in Super Admin moved a marketer commission from `paid` back to `owed`.</symptom>
 <root_cause>`CommissionService::markCommissionOwed()` always set `status => owed` on `updateOrCreate`, even when the row was already paid.</root_cause>
 <prevention_rule>Never downgrade commission status on payment re-confirm — skip status update when existing row is `paid`.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Referral links with uppercase codes (`?ref=JOY20`) never attached the marketer; direct-sale tenants could not confirm payments without a marketer.</symptom>
 <root_cause>Marketer codes were stored verbatim while `CaptureReferralParams` lowercased lookups; payment buttons required `marketer_id` even for direct sales.</root_cause>
 <prevention_rule>Normalize marketer codes to lowercase on save; use case-insensitive lookup for legacy rows; setup/monthly payment actions must not require a marketer.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Changing plan tier or discount on tenant edit did not refresh `setup_amount_due` / `monthly_amount_due`.</symptom>
 <root_cause>`applyPricingToTenant()` ran only in `CreateTenant::afterCreate`, not on edit.</root_cause>
 <prevention_rule>Re-run pricing when `plan_tier` or `discount_code_id` changes on save; count discount redemptions only when the code actually changes.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Daily Roster "Call to Chamber" / "Mark Completed" desynced Live Queue (no `current_booking_id`, missing timestamps).</symptom>
 <root_cause>Roster actions updated booking `status` directly instead of `LiveSessionService`.</root_cause>
 <prevention_rule>Roster queue actions must go through `LiveSessionService::bringBookingToChamber()` and `completeBooking()`.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Walk-in and online bookings for the same phone/session/date could duplicate; walk-in phones stored with `+88` prefix broke portal lookup.</symptom>
 <root_cause>`BookingService` stored raw phone input and had no duplicate guard; only `BookingController` normalized phones.</root_cause>
 <prevention_rule>Normalize BD phones inside `BookingService::createBookingForBookable()` and reject duplicate active bookings for same phone + bookable + date.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>`markAbsent` cancelled `no_show` patients while `endSession` preserved them; platform finance "owed" ignored period filter; PWA icon SVG interpolated unescaped theme/initial.</symptom>
 <root_cause>Inconsistent status exclusions in `LiveSessionService`; `$owed` query omitted period scope; `PWAController::icon()` built SVG via string interpolation.</root_cause>
 <prevention_rule>`markAbsent` must exclude `no_show`; finance owed must respect period filters; PWA SVG must validate hex colors and escape text nodes.</prevention_rule>
</bug>

## 2026-07-31 (audit residuals)

<bug>
 <category>Code</category>
 <symptom>`LiveQueueControl` kept a dead `endSession()` method alongside the header `endSessionAction()`; easy to wire the wrong one later.</symptom>
 <root_cause>Duplicate Livewire action left after Filament header actions took over session end.</root_cause>
 <prevention_rule>One end-session entry point per page — header Action only; delete unused Livewire methods.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>`LiveSession::with('bookings')` returned empty or wrong-date bookings when multiple session dates shared a schedule.</symptom>
 <root_cause>`bookings()` baked `$this->session_date` into the relation definition, so eager load could not match per-parent dates.</root_cause>
 <prevention_rule>Use `HasManyByScheduleAndDate` (match on `schedule_session_id` + `session_date`/`booking_date`) — never `whereDate(..., $this->session_date)` on a HasMany meant for eager load.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Re-confirming a paid setup/monthly payment rewrote `base_amount` / `commission_amount` while leaving status `paid`, so "Commissions paid out" shifted retroactively.</symptom>
 <root_cause>Status guard skipped only `status`; amount fields still updated unconditionally.</root_cause>
 <prevention_rule>When commission status is already `paid`, skip the entire `markCommissionOwed` update (status and amounts).</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Path-tenant SMS ticket links used `CENTRAL_DOMAINS[0]` (e.g. `127.0.0.1`) instead of the canonical `APP_URL` host.</symptom>
 <root_cause>`route('path.bookings.show')` resolves against the first registered central domain, not `config('app.url')`.</root_cause>
 <prevention_rule>Build path-tenant ticket URLs from `config('app.url')` + `/{tenant}/bookings/{id}`; keep custom-domain hosts for Domain rows.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Dead `filament_path_tenant` session fallback in `SetPathTenantUrlDefaults` looked like a working safety net but never ran usefully and risked cross-tenant URL bleed if reordered after `StartSession`.</symptom>
 <root_cause>Middleware runs before `StartSession` in the path panel stack; session write/read of that key was misleading dead code.</root_cause>
 <prevention_rule>Path URL defaults come only from route `{tenant}` or initialized tenancy — never from a shared central-domain session key.</prevention_rule>
</bug>

## 2026-08-01T21:09:08+0600

<bug>
 <category>Code</category>
 <symptom>On custom tenant domains (e.g. `solo.localhost:8040`), Filament Live Queue Control showed a browser alert every ~3 seconds: “This page has expired. Would you like to refresh the page?”</symptom>
 <root_cause>Livewire polls POST to `/livewire/update` through the global `web` middleware only. Filament panel routes initialize tenancy before session, but Livewire update did not — CSRF/session context broke on tenant hosts while the queue table polled every 3s.</root_cause>
 <prevention_rule>Prepend `InitializeTenancyForTenantHosts` to the `web` group (skip central domains) so every web request on a tenant domain initializes tenancy before `StartSession` and CSRF — including Livewire polls.</prevention_rule>
</bug>

## 2026-08-01T21:15:16+0600

<bug>
 <category>Code</category>
 <symptom>Schedule Sessions (and other tenant admin pages) on `solo.localhost` still showed “This page has expired” every few seconds after the first web-middleware tenancy fix.</symptom>
 <root_cause>Two gaps: (1) the domain Filament panel did not register session/tenancy middleware as Livewire-persistent (unlike the path panel), so `/livewire/update` lost tenant + CSRF context on polls; (2) local `SESSION_DRIVER=database` on SQLite caused session row lock contention when multiple Livewire components polled concurrently.</root_cause>
 <prevention_rule>Domain tenant panels must use `->middleware([...], isPersistent: true)` for the full session + tenancy stack (mirror `TenantAdminPathPanelProvider`); use `SESSION_DRIVER=file` locally with SQLite; keep `InitializeTenancyForTenantHosts` for central-host path URLs via referer.</prevention_rule>
</bug>

## 2026-08-01T22:40:00+0600

<bug>
 <category>UI/UX</category>
 <symptom>Services / Conditions section on the doctor homepage showed General Medicine and Chronic Disease Care stacked full-width instead of side-by-side on tablet/desktop.</symptom>
 <root_cause>Card sections were switched to `<x-card-grid>` / `.card-grid`, but tenant pages (CDN Tailwind + `theme.css`) never `<link>`ed `public/css/card-grid.css` — only marketing home and Operational Reports did — so the grid class was inert HTML.</root_cause>
 <prevention_rule>Any Blade layout that renders `<x-card-grid>` without Vite/`app.css` must also `<link>` `css/card-grid.css` (same as marketing home).</prevention_rule>
</bug>

## 2026-08-01T23:08:41+0600

<bug>
 <category>UI/UX</category>
 <symptom>Solo homepage section titles (H2s) rendered at different sizes across Conditions, About, Videos, FAQ, and Testimonials.</symptom>
 <root_cause>Each section Blade hard-coded its own Tailwind font-size utilities (`text-[2.35rem]`, `lg:text-[2.75rem]`, `lg:text-[3.5rem]`, etc.) instead of sharing the Figma Heading/H2 token (64px).</root_cause>
 <prevention_rule>Solo section titles must use the shared `.solo-h2` class from `tenant/solo/webpage.blade.php` — never per-section `text-*` / `lg:text-[…rem]` size overrides on those h2s.</prevention_rule>
</bug>

## 2026-08-01T23:11:02+0600

<bug>
 <category>UI/UX</category>
 <symptom>Solo homepage typography was inconsistent beyond H2s — hero H1, card H3s, “Including:” labels, FAQ questions, testimonial quotes, and body copy each used different Tailwind sizes.</symptom>
 <root_cause>Each section Blade hard-coded its own `text-sm` / `text-lg` / `text-[1.125rem]` utilities instead of shared Figma tokens (H1 88px, H3 36px, body 16px, medium 18px, tagline 18px uppercase).</root_cause>
 <prevention_rule>Solo homepage copy must use `.solo-h1` / `.solo-h2` / `.solo-h3` / `.solo-body-lg` / `.solo-body` / `.solo-tagline` / `.solo-label` from `webpage.blade.php` — no ad-hoc `text-*` font sizes in section blades.</prevention_rule>
</bug>

## 2026-08-04T20:07:03+0600

<bug>
 <category>CRO</category>
 <symptom>On platform path URLs (e.g. `/solo/`), clicking “Book Appointment” opened `/book` and showed Not Found — booking CTA was dead.</symptom>
 <root_cause>Hero/about/cta section blades used `SafeUrl::href('/book')` which keeps a root-relative path; path tenancy requires `/{tenant}/book`. Nav portal already used `tenant_web_url()`.</root_cause>
 <prevention_rule>Tenant-authored same-origin CTAs must use `tenant_safe_href()` (SafeUrl + path prefix) — never raw `SafeUrl::href('/book')` or hard-coded `/book` in tenant section blades.</prevention_rule>
</bug>

## 2026-08-05T05:44:58+0600

<bug>
 <category>UI/UX</category>
 <symptom>Solo doctor homepage looked broken: section titles clipped at the top (especially “Meet Dr…”), FAQ questions forced into ALL CAPS, hero credentials spread awkwardly, testimonials heading crushed into a skinny column, sticky header frost blur washed over videos.</symptom>
 <root_cause>Figma token CSS used display line-heights as low as 0.85 and body letter-spacing as tight as -0.04em; FAQ reused `.solo-label` (uppercase); hero used `justify-between` + tall min-height; testimonials put a long H2 in a 1/3 grid cell; header used `backdrop-blur` over scrolling content.</root_cause>
 <prevention_rule>Solo display headings must keep line-height ≥ 1.05; never put multi-line H2s in a narrow grid column; FAQ questions must not use uppercase label styles; sticky chrome should be opaque white without backdrop-blur over media.</prevention_rule>
</bug>

## 2026-08-05T05:52:17+0600

<bug>
 <category>UI/UX</category>
 <symptom>Waiting-room call voice still sounded like a “ghost speaking English” even after TTS tweaks.</symptom>
 <root_cause>Browser SpeechSynthesis quality is device-dependent and often hollow/whispery; ranking voices could not fix bad system TTS engines.</root_cause>
 <prevention_rule>Outdoor-screen English callouts must use pre-recorded WAV clips (`public/audio/announce/number-N.wav`), not live browser TTS, except as a missing-file fallback.</prevention_rule>
</bug>

## 2026-08-05T05:56:46+0600

<bug>
 <category>UI/UX</category>
 <symptom>Call voice still sounded ghostly when testing from Live Queue (admin) — same hollow English as before.</symptom>
 <root_cause>First “recorded” clips were generated with macOS Samantha, which is the same family of voice as browser TTS; admin Live Queue also played nothing of its own, so any leftover TTS fallback still dominated the experience.</root_cause>
 <prevention_rule>Announce clips must use a clear PA voice (Karen), never Samantha; Live Queue Control must play the same WAV on Call; never fall back to SpeechSynthesis.</prevention_rule>
</bug>

## 2026-08-05T06:24:53+0600

<bug>
 <category>UI/UX</category>
 <symptom>After restoring the previous solo homepage, Conditions I Treat cards showed “Including:” inside the grey feature box instead of above it (Figma).</symptom>
 <root_cause>Homepage restore rolled back section blades wholesale, including the Aug 1 card inner structure that had moved the Including label above the grey list.</root_cause>
 <prevention_rule>When reverting homepage visuals, keep Figma Conditions card structure: “Including:” label above the grey feature container — never nest that label inside the grey box.</prevention_rule>
</bug>
