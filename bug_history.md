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
 <prevention_rule>Announce *serial* clips must use a clear PA voice (Karen), never Samantha; Live Queue Control must play the same WAV on Call; never fall back to SpeechSynthesis for the serial. Patient *names* may use browser SpeechSynthesis as a try-it path (see decisions.md 2026-08-12 name announce).</prevention_rule>
</bug>

## 2026-08-05T06:24:53+0600

<bug>
 <category>UI/UX</category>
 <symptom>After restoring the previous solo homepage, Conditions I Treat cards showed “Including:” inside the grey feature box instead of above it (Figma).</symptom>
 <root_cause>Homepage restore rolled back section blades wholesale, including the Aug 1 card inner structure that had moved the Including label above the grey list.</root_cause>
 <prevention_rule>When reverting homepage visuals, keep Figma Conditions card structure: “Including:” label above the grey feature container — never nest that label inside the grey box.</prevention_rule>
</bug>

## 2026-08-05

<bug>
 <category>Business_Logic</category>
 <symptom>On clinic tenants offering both consultations and labs with more than one chamber, choosing "Doctor Consultation" jumped straight to "Select a Doctor" — the location step was never shown, so the patient never chose which chamber and `state.chamberId` stayed null (schedules from every chamber were then listed together). Back could also never return to the booking-type step.</symptom>
 <root_cause>`rebuildFlow()` pushed `step-type` only while `state.type` was null, so selecting a type removed it from the flow array and shifted every later step down one index. `selectType()` then called `nextStep()` on top of that shift, advancing twice.</root_cause>
 <prevention_rule>Never rebuild the wizard flow array in a way that changes the index of the step the patient is currently on — steps may only be added or removed ahead of `currentStepIndex`, and any conditional step must stay in the flow once it has been displayed.</prevention_rule>
</bug>

## 2026-08-05T15:30:52+0600

<bug>
 <category>UI/UX</category>
 <symptom>Tenant admin panel shows a repeating “This page has expired. Would you like to refresh the page?” popup.</symptom>
 <root_cause>`TenantAdminPathPanelProvider` (for `/{tenant}/admin`) did not include `InitializeTenancyForTenantHosts`, so Livewire `/livewire/update` polls lost the correct tenant/CSRF/session context.</root_cause>
 <prevention_rule>Any Filament tenant admin panel that uses path tenancy must include `InitializeTenancyForTenantHosts` in its persistent middleware stack before session + CSRF for Livewire polling.</prevention_rule>
</bug>

## 2026-08-05T15:35:02+0600

<bug>
 <category>Code</category>
 <symptom>Tenant admin Livewire polling fails with HTTP `419` on `POST /livewire/update`, leading to “This page has expired” prompts.</symptom>
 <root_cause>`TenantAdminPathPanelProvider` applied competing tenancy initialization middlewares during persistent Livewire requests, keeping CSRF/session context from matching.</root_cause>
 <prevention_rule>For persistent Filament tenant panels, use a single tenancy initialization middleware for `/{tenant}/admin` (don’t stack both `InitializeTenancyForTenantHosts` and `InitializeTenancyByPath`); ensure it runs before session + CSRF.</prevention_rule>
</bug>

## 2026-08-05T19:00:03+0600

<bug>
 <category>Code</category>
 <symptom>`/{tenant}/admin` crashed with `UrlGenerationException: Missing required parameter: tenant` for `filament.tenantAdminPath.*.auth.login`.</symptom>
 <root_cause>An earlier 419 fix removed `InitializeTenancyByPath` from `TenantAdminPathPanelProvider` and relied only on `InitializeTenancyForTenantHosts`. Path panel login URL generation needs route `{tenant}` + `SetPathTenantUrlDefaults` after stancl path init; Livewire polls already get tenancy from `InitializeTenancyForTenantHosts` on the global `web` group.</root_cause>
 <prevention_rule>Never remove `InitializeTenancyByPath` from the path Filament panel stack — keep it with `SetPathTenantUrlDefaults` for admin routes; leave Livewire `/livewire/update` tenancy to the `web`-group `InitializeTenancyForTenantHosts` middleware.</prevention_rule>
</bug>

## 2026-08-05T19:59:41+0600 (admin panel audit)

<bug>
 <category>Business_Logic</category>
 <symptom>Blocking a date in tenant admin flipped already-**completed** visits to `cancelled`, so finished consultations disappeared from reports and the patient's ticket said the visit was cancelled.</symptom>
 <root_cause>`CreateSlotBlock::afterCreate()` ran a second cancellation pass on top of `SlotBlock::booted()` → `SlotBlockService::cancelAffected()`. The service excludes `cancelled` and `completed`; the page's copy excluded only `cancelled`, so it swept up the completed rows the service had deliberately left alone. It also wrote `status` directly without `slot_block_id`, so those bookings never appeared in the "Notify patients" list.</root_cause>
 <prevention_rule>Slot-block cancellation lives in `SlotBlockService` only, invoked from the `SlotBlock::created` hook. A Filament page may report what the service did (via `cancelledBookings()`) but must never run its own cancellation query.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>A patient could inject script into the tenant admin panel: the "Bookings Cancelled" notification after blocking a date rendered patient names as raw HTML.</symptom>
 <root_cause>`CreateSlotBlock::afterCreate()` built `<li><a …>Notify {$booking->patient_name}</a></li>` and passed it through `new HtmlString(...)`. `patient_name` comes from the public booking form and is validated only as `string|max:255`. Same class of bug as the 2026-07-27 `innerHTML` wizard XSS.</root_cause>
 <prevention_rule>Never interpolate booking-supplied fields into `HtmlString` / notification bodies. Patient-facing data in admin UI goes through a Blade view with `{{ }}` escaping — as `filament.tenant-admin.slot-block-notify` already does.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Attaching a marketer and changing the plan tier in the same tenant edit created no pending setup commission — the partner was never credited for that doctor.</symptom>
 <root_cause>`EditTenant::afterSave()` re-priced the tenant and called `$tenant->save()` a second time. Eloquent re-syncs `$model->changes` on every save that writes rows, so the later `$tenant->wasChanged('marketer_id')` reported the *pricing* save's changes and returned false.</root_cause>
 <prevention_rule>Read every `wasChanged()` answer into a local variable at the top of `afterSave()`, before any code path saves the model again.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Tenant admin could bulk-delete every chamber and the only doctor, despite `ChamberPolicy` keeping at least one chamber and `DoctorPolicy` blocking a solo tenant's only doctor. Deleting them orphaned every schedule and booking.</symptom>
 <root_cause>Filament authorizes bulk actions with `deleteAny()`, not `delete()`. Neither policy defined `deleteAny()`, and Filament's "policy exists but method missing" path returns **allow** — so the toolbar's `DeleteBulkAction` skipped the per-record rules entirely. `TierGatingPolicyTest` kept passing because it only exercised the policy.</root_cause>
 <prevention_rule>Every policy backing a Filament resource with a `DeleteBulkAction` must define `deleteAny()`. When the rule is count-based (keep the last one) it cannot hold across a bulk selection — deny `deleteAny()` and drop the bulk action from that table.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Two marketer/super-admin accounts could be created with the same email; the second could never sign in and password reset always hit the first. Duplicate tenant staff emails and duplicate tenant slugs crashed with a 500 instead of showing a field error.</symptom>
 <root_cause>The users unique index is `(tenant_id, email)`, and SQL treats `NULL` tenant ids as distinct, so central accounts were never constrained. No form carried a matching `unique()` rule: tenant staff email had none, marketer login email had none, and `tenants.id` (the primary key) had none.</root_cause>
 <prevention_rule>Every admin form field backed by a unique index needs a `unique()` rule scoped the same way as the index — `where('tenant_id', tenant('id'))` for tenant users, `whereNull('tenant_id')` for central accounts. Tenant slug also needs `notIn(config('tenancy.reserved_path_prefixes'))`, or the tenant's site is unreachable.</prevention_rule>
</bug>

## 2026-08-05T20:19:38+0600

<bug>
 <category>UI/UX</category>
 <symptom>“This page has expired. Would you like to refresh the page?” on `/{tenant}/admin/login` — the fifth report of this alert, after four middleware fixes (2026-08-01T21:09, 2026-08-01T21:15, 2026-08-05T15:30, 2026-08-05T15:35 + the 19:00 revert) had each been treated as the cause.</symptom>
 <root_cause>**Not the middleware stack this time** — measured on a running server, a fresh login page commits to `/livewire/update` with status 200, and the CSRF token stays stable across `/solo/admin/login` and `/admin/login`. The remaining cause is an ordinary stale token: all three panels sit on one host and share a single session cookie, `Filament\Auth\Pages\Login::authenticate()` calls `session()->regenerate()` (rotating the CSRF token) on every login, and `SESSION_LIFETIME` was 120 minutes. So any admin tab left open while you sign in elsewhere — or idle past the lifetime — submits a dead token and Livewire answers 419.</root_cause>
 <prevention_rule>Before changing tenancy or session middleware in response to a 419, **prove the stack is broken first**: load the page and POST a real Livewire commit with a valid token. If that returns 200, the middleware is fine and the 419 is a stale token — changing middleware will only trade one panel's breakage for another's, as the 15:35 → 19:00 fix/revert pair did.</prevention_rule>
</bug>

## 2026-08-05T20:27:33+0600

<bug>
 <category>Code</category>
 <symptom>Signing in at `/{tenant}/admin/login` landed on `http://127.0.0.1:8040/%7Btenant%7D/admin` — the literal route pattern — and 404'd.</symptom>
 <root_cause>`Filament\Auth\Http\Responses\LoginResponse` redirects to `Panel::getUrl()`. That method returns `route($panel->generateRouteName('home'))` only when such a route exists — and Filament never registers a `home` route (confirmed: no `*.home` route in `route:list`), so it always falls through to `url($panel->getPath())`. For the central panels the path is literally `admin` / `partner`, so the fallback is accidentally correct; for the path panel the path is the *pattern* `{tenant}/admin`, which `url()` emits verbatim. Only reachable when the session held no `url.intended` — i.e. when login started at the login URL directly rather than by being bounced off `/{tenant}/admin` — which is why it looked intermittent.</root_cause>
 <prevention_rule>Never let panel URLs come from `Panel::getUrl()` / `getPath()` for the path panel — its path is a route pattern, not a URL. Resolve the panel's dashboard route by name through `App\Support\FilamentPanelUrl::home()`, which uses Filament's own `generateRouteName()` so the domain segment multi-domain panels add stays correct.</prevention_rule>
</bug>

## 2026-08-06T21:05:01+0600

<bug>
  <category>Business_Logic</category>
  <symptom>A solo practice with a doctor login but no staff login had nobody able to call patients. `queue_runner` defaults to `staff`, and in staff-run mode only a staff user may operate the call/complete controls. The account owner is deliberately excluded from the queue, so the doctor could not run their own chamber and could not fix it either — only an admin can change the setting in Branding Settings. A practice created without an admin login was stuck entirely.</symptom>
  <root_cause>The one-party-per-practice rule was enforced against the *configured* runner without checking that the configured party actually exists in that practice. The column default (`staff`) applies to every new tenant and nothing at tenant creation reconsiders it. The seeded demo tenant has admin + doctor + staff, so every existing test exercised a practice where staff were present and the dead end never appeared.</root_cause>
  <prevention_rule>Any permission resolved from a per-tenant role setting must be checked against role presence, not just configuration — if the chosen party has no users, authority falls back to the other eligible party rather than to nobody. Tests for role-exclusivity rules must build the practice deliberately incomplete (the role under test missing), because the seeded demo tenant has one user of every role and will hide the failure.</prevention_rule>
</bug>

## 2026-08-06T23:37:36+0600

<bug>
  <category>Code</category>
  <symptom>Consultation voice notes and prescription photos were written to the `public` disk, which is symlinked into the web root and served directly by the web server. `VisitMediaController` correctly required a doctor login, but the same bytes were reachable at `/storage/visit-audio/{tenant}/{uuid}` with no authentication at all, contradicting the confidentiality promise made to doctors in the signup agreement. Found during pre-production review; no production files existed yet.</symptom>
  <root_cause>The `public` disk was chosen by analogy with existing uploads (logos, call-audio chimes) without distinguishing branding assets from patient clinical records. Random UUID filenames made the exposure non-enumerable, which masked it: the files were unguessable, so the missing access control never produced a visible failure. No test asserted that clinical media was unreachable over HTTP, only that the controller enforced roles.</root_cause>
  <prevention_rule>Patient clinical data — notes, prescriptions, voice, photos — is never written to the `public` disk or anywhere under the web root; it goes to a private disk and is streamed through an authenticated controller, and the service must expose no URL accessor. Unguessable filenames are never treated as access control. Any new clinical media path must be covered by a test asserting it is not fetchable over HTTP, not merely that a role check exists somewhere.</prevention_rule>
</bug>

## 2026-08-07T01:13:55+0600

<bug>
  <category>Code</category>
  <symptom>The visit-notes form (diagnosis, advice, prescription, voice note, photo) crashed on open everywhere it appeared: Consult Screen's catch-up "Add notes" flow, "Complete & call next", and Daily Roster / Live Queue Control's Mark Completed modal for doctors. The action button did nothing visible, or the modal never rendered.</symptom>
  <root_cause>Two independent bugs stacked. (1) `catch-up-notes-list.blade.php`'s "Add notes" button called `mountAction('catchUpBooking', ...)` while the parent "Patients without notes today" list action was already mounted; Filament resolves a `mountAction` call made while another action is open as a *nested* modal action of that parent, looked up via `$parentAction->getModalAction()` — `catchUpBooking` was never registered there, so resolution silently threw `ActionNotResolvableException`, which `mountAction()` catches by unmounting and returning null with no error surfaced. (2) Once that was fixed by switching to `replaceMountedAction()` (which clears `mountedActions` first so the lookup takes the correct top-level-method path), the shared `VisitNotesFormSchema` still failed while rendering because `visit-voice-recorder.blade.php` had `{{ elapsed }}` — Blade PHP interpolation of a bare, undefined constant — where an Alpine.js `x-text="elapsed"` binding was intended, since `elapsed` is an Alpine data property (`this.elapsed`), not a Blade variable. This threw `Undefined constant "elapsed"` on every render of the shared form.</symptom>
  <prevention_rule>Never call `mountAction()` for an unrelated action from inside a Blade view rendered as another action's `modalContent()` — it will be resolved as a nested action of the currently-open one and fail silently. Use `replaceMountedAction()` to close the current action and open a different top-level one instead. Also: Alpine.js component state (`x-data` properties) must be rendered with `x-text`/`x-bind`, never bare `{{ }}` Blade interpolation — Blade evaluates that as PHP in the *server's* scope, not Alpine's client-side scope, and a bare identifier like `elapsed` is silently type-coerced into a PHP undefined-constant error. This bug shipped invisibly because clicking the broken button produced no console error and no failed network request — verify actions that open forms by asserting the modal's expected field content actually renders (e.g. via `Livewire::test(...)->call('mountAction', ...)` and inspecting the rendered schema HTML), not just that the click returns 200.</prevention_rule>
</bug>

## 2026-08-07T01:50:29+0600

<bug>
  <category>UI/UX</category>
  <symptom>On Live Queue Control the patient the chamber was announcing right then was listed *below* cancelled and completed bookings in the queue table, so the row staff most needed was the hardest to find.</symptom>
  <root_cause>The table's ordering `CASE` in `LiveQueueControl::table()` enumerated only `in_chamber`, `waiting`, `completed` and `cancelled`. Every other status — including `called`, the status of the current patient for the entire window between the announcement and their arrival, plus `skipped` and `no_show` — fell through to the `ELSE 5` bucket at the bottom of the list.</root_cause>
  <prevention_rule>An ordering `CASE` over a status column must enumerate every value the column can hold, with the `ELSE` bucket reserved for genuinely unknown values — never used as a catch-all for statuses that simply were not thought about. When adding a status to a model, grep for `CASE status` / `orderByRaw` and place it explicitly.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>The "Finish / End Session" header action rendered as a pale pink block with red text in dark mode, ignoring the theme entirely.</symptom>
  <root_cause>The action carried `extraAttributes(['style' => 'background-color: #fef2f2 !important; ...'])` — literal light-mode hex values with `!important`, applied unconditionally, on top of Filament's own `danger` colour treatment which already handled both themes.</root_cause>
  <prevention_rule>Never hardcode hex colours in `extraAttributes`/inline styles on Filament components. Use the component's `color()` API; if a bespoke colour is genuinely needed, define it in CSS with a `.dark` counterpart rather than inline.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>Two silent failures around call announcements. (1) When the browser blocked the announcement audio (its normal behaviour until the tab has been interacted with), the failure was swallowed into `console.log` — staff believed the chamber was announcing when it was mute. (2) Skipping a patient advanced the queue to the next serial without playing any announcement at all, because `skipPatient()` never called `dispatchCallAnnounce()` even though the underlying `LiveSessionService::skipPatient()` calls `advanceQueue()`.</symptom>
  <root_cause>(1) `play().catch()` logged and returned, with no UI surface for the blocked state. (2) `dispatchCallAnnounce()` was wired into `startSession`, `nextPatient` and `completeAndCallNextAction` but was missed on the fourth path that also advances the queue.</root_cause>
  <prevention_rule>A rejected `HTMLMediaElement.play()` must set visible state offering the user a one-tap unlock, never just log. And every Livewire method whose service call can advance `live_sessions.current_booking_id` must call `dispatchCallAnnounce()` — when adding such a path, check it against the full set of callers of `advanceQueue()`.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>Completed and called patients kept a stale "Retry After #N" value in the queue table long after their retry slot had been used.</symptom>
  <root_cause>`LiveSessionService::setAsCurrent()` set `status` and `called_at` but left `retry_queue_position` populated when the booking being called was a skipped patient picked up by the retry query, so the column (whose visibility test only checked `whereNotNull('retry_queue_position')`) kept rendering it.</root_cause>
  <prevention_rule>Clear a queue-position/scheduling hint at the moment it is consumed, in the same write that consumes it — do not rely on the display layer filtering stale values out by status.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>The three `x-filament::callout` blocks added during the Live Queue Control rework rendered their heading and icon but no body text at all.</symptom>
  <root_cause>Filament v4's `callout` component template renders only the `heading`, `description`, `footer` and `controls` slots — it never outputs `$slot`. Content passed as the component's default slot is silently discarded, with no error.</root_cause>
  <prevention_rule>Body text for `x-filament::callout` goes in `<x-slot name="description">`, never the default slot. More generally: when a Filament Blade component renders nothing for content you passed, read its template in `vendor/filament/support/resources/views/components/` before assuming the data is wrong — several of these components ignore `$slot` entirely.</prevention_rule>
</bug>

## 2026-08-07T11:01:26+0600

<bug>
  <category>Code</category>
  <symptom>Reopening "Write prescription" (or "Complete visit") for a patient with nothing written yet showed a blank, empty-looking grey box above the Diagnosis section, labelled "visit notes hint" — instead of the intended plain sentence "All fields are optional — leave blank to complete without notes." The same box on the older "Add notes" (catch-up) action showed the sentence correctly, so the two actions visibly disagreed.</symptom>
  <root_cause>Two separate bugs stacked. (1) The hint field used `->label('')` to suppress its label; in this Filament version an empty-string label is falsy and the renderer falls back to an auto-generated label from the field's internal name (`_visit_notes_hint` → "visit notes hint") — `->label('')` does not mean "no label," only `->hiddenLabel()` does. This affected every action using the shared `VisitNotesFormSchema`, including the pre-existing catch-up flow, but was masked there because the field's `->default()` text still rendered underneath it. (2) The newly added `->fillForm()` on `writePrescriptionAction` and `completeVisit` (added this session to let a doctor reopen and re-edit a saved prescription) replaces Filament's normal per-field default-value hydration with exactly the array returned by the closure; `VisitNotesFormSchema::stateFromRecord()` never included a key for the hint field, so on any action with `->fillForm()` the field's `->default()` was never applied and it rendered empty — silently, since `dehydrated(false)` means nothing about it is validated or saved.</symptom>
  <prevention_rule>Use `->hiddenLabel()` to hide a field's label, never `->label('')` — an empty string is falsy and several Filament code paths treat "falsy label" as "no label configured," not "empty label," falling back to the auto-generated one. Separately: the moment an action gains `->fillForm()`, every component in that form relying on `->default()` must have its value included in the state array explicitly (or the default duplicated at the point of use), because `->fillForm()` is a full replacement, not a supplement, of the schema's normal default-filling. Test disabled/instructional fields the same as data fields — a `dehydrated(false)` field being blank causes no validation error and no failed test unless something explicitly asserts its rendered value, so this shipped invisibly.</prevention_rule>
</bug>

## 2026-08-07T12:21:37+0600

<bug>
  <category>Code</category>
  <symptom>Complete visit modal crashed PHP with memory exhaustion the first time a doctor opened it after the summary/edit work; voice recorder Start/Stop buttons did nothing inside the modal; prescription photo uploads could land on the public disk if saved through the form path.</symptom>
  <root_cause>(1) `completeVisit`/`LiveQueueControl::completeVisitAction` form closures called `$this->getMountedAction()` while Filament was already building that same action's schema — infinite recursion. (2) Voice recorder JS used a global `document.querySelector` and was not in a Livewire `@script` block, so it never bound when the modal opened. (3) `VisitNotesFormSchema` `FileUpload` still pointed at a public disk before this pass.</root_cause>
  <prevention_rule>Inside an Action's `->form()` closure, read arguments from the injected `Action $action` parameter — never `getMountedAction()`. Modal Alpine/JS that must run when Filament opens an action goes in `@script` with roots scoped via `x-ref`, not bare `document.querySelector`. Clinical `FileUpload` fields must use `disk('local')` + `VisitMediaService::{voice,photo}Directory()` — verify with `ClinicalMediaPrivacyTest` for both API and form paths.</prevention_rule>
</bug>

<bug>
  <category>Business_Logic</category>
  <symptom>Condition and medicine learning counters incremented while the doctor was still mid-consult (Write prescription), so a draft that never reached the patient still boosted search ranking.</symptom>
  <root_cause>`VisitRecordService::recordUsagesFromSubmission()` ran on every `saveForCompletedBooking()` call regardless of booking status.</root_cause>
  <prevention_rule>Personal learning stats (`condition_usages`, `medicine_usages`) increment only when `$booking->status === 'completed'` at save time — mid-consult saves may persist clinical data but must not train pickers.</prevention_rule>
</bug>

## 2026-08-07T14:32:26+0600

<bug>
  <category>UI/UX</category>
  <symptom>Typing a generic name (e.g. "omeprazole") in the prescription medicine dropdown returned no results even though OMEE was in the catalogue.</symptom>
  <root_cause>The grouped static `Select` search only matches option labels and values. After switching from API search to `groupedSelectOptions()`, labels were brand + strength only (`OMEE 20 mg`) with no generic text, so generic queries never matched.</root_cause>
  <prevention_rule>Any catalogue medicine shown in a searchable Filament static select must include searchable generic text in `displayLabel()` (or use `getSearchResultsUsing()`), and add a regression test on `displayLabel()` when changing picker wiring.</prevention_rule>
</bug>

## 2026-08-07T16:16:01+0600

<bug>
  <category>Business_Logic</category>
  <symptom>On a clinic tenant, a specialist's medicine list fell back to the general-physician catalogue everywhere there was no booking in context — **My medicines** and bare `GET /api/medicines/search`. A dentist or dermatologist could not find their own brands in the picker and had to type them as free text. Solo tenants were unaffected, so it did not show in solo testing.</symptom>
  <root_cause>`doctors` rows and doctor `users` rows had no link between them, so `MedicineService::resolvePrescribingDoctor()` could not identify the signed-in doctor. Its only fallback was `Doctor::first()` behind an `isSoloDoctor()` check — correct for one-doctor practices, unavailable to clinics, which fell through to `null` and then to `PRACTICE_GENERAL`.</root_cause>
  <prevention_rule>Any tier-gated branch (`isSoloDoctor()` / `isClinic()` / `hasFeature()`) that supplies a *value* rather than toggling a feature must have a defined answer for the other tier, and a test that runs on that other tier. Solo-only shortcuts that quietly degrade clinic behaviour are the failure mode — assert the clinic path explicitly.</prevention_rule>
</bug>

## 2026-08-07T17:15:48+0600

<bug>
  <category>Code</category>
  <symptom>While adding a no-JS guard to the clinic homepage, the whole page below the headline went invisible: hero lead, booking card and every revealed block stayed at opacity 0 even after the motion script had added `.is-in`.</symptom>
  <root_cause>The guard class was added to the hiding rule only — `html.has-js [data-reveal-section] [data-reveal-block][data-reveal-kind="fade"]` (specificity 0,4,1) — while its counterpart `[…][data-reveal-kind="fade"].is-in` stayed at (0,4,0). Adding a guard to one half of a hide/show pair silently inverted which rule won, and the failure mode is a blank page, not a visual glitch.</root_cause>
  <prevention_rule>When adding a qualifier (`html.has-js`, a theme class, a feature wrapper) to a rule whose "before" state hides content, add the same qualifier to every rule that reverses it, in the same edit — and verify the end state in a browser, not by reading the cascade.</prevention_rule>
</bug>

## 2026-08-07T17:54:55+0600

<bug>
  <category>Code</category>
  <symptom>After the clinic page shell was replaced (port phase 1), the `image_carousel` section stopped working: slides stacked on top of each other and the arrows and dots did nothing.</symptom>
  <root_cause>The old shell loaded Alpine from a CDN and `image_carousel` was the only section that used it (`x-data`, `x-show`, `@click`). The new shell dropped Alpine, and nothing failed loudly — `x-show` simply never ran, so every absolutely-positioned slide stayed rendered.</root_cause>
  <prevention_rule>When replacing a page shell, grep the views it includes for every library the old shell provided (`x-data`, `wire:`, `data-` hooks, global helpers) before removing a `<script>` tag. A section that depends on a missing library fails silently in the browser, not at build or test time.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>Restyling the shared section blades for the clinic tier also changed the **locked** solo homepage: the solo shell renders Clireo markup for any block type it does not override, and it does not load the clinic stylesheet, so those sections would have appeared unstyled to solo patients.</symptom>
  <root_cause>`tenant/solo/webpage.blade.php` resolves each block as `tenant.solo.sections.{type}` if it exists, else `tenant.sections.{type}`. Solo overrode only 6 of the 18 types, so the other 12 were shared files — a fact the "solo homepage is locked" rule did not make visible from the clinic side.</root_cause>
  <prevention_rule>Before editing anything under `resources/views/tenant/sections/`, check whether the solo shell falls through to it. Shared-by-fallback views are inside the patient-homepage lock; give solo a pinned copy first, then change the clinic file.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>Clinic homepage at `localhost:8765/demo/` rendered as unstyled HTML: Times New Roman, blue underlined links, and duplicated nav labels ("HomeHome", "Book AppointmentBook Appointment").</symptom>
  <root_cause>Clireo shells linked CSS/JS via `asset()`, which resolves to `APP_URL` (`http://localhost` with no port). The browser loaded the page from `:8765` but requested styles from port 80, so `clinic-clireo.css` never applied. The duplicate labels are the `.fx-btn` hover pattern — two `<span>`s in each link — with no CSS to hide the second.</root_cause>
  <prevention_rule>Static files under `public/` that are not tenant-specific must use `public_asset()` (root-relative `/css/...`) in clinic patient shells, not `asset()`. Solo survived longer because it still loaded Tailwind from a CDN absolute URL.</prevention_rule>
</bug>

## 2026-08-08T00:18:18+0600

<bug>
  <category>Business_Logic</category>
  <symptom>A patient could book a doctor's session, or a lab collection window, that had already finished earlier the same day — booking date validation only checked "today or later," never the session's own `end_time`. The confirmation SMS still went out, and the wizard's date field defaulted straight onto the already-finished slot.</symptom>
  <root_cause>`BookingService::availabilitySnapshot()` (via `isDateBlocked()`) checked slot blocks, day-of-week and capacity, but nothing compared `now()` to the bookable's `end_time` when the chosen date was today. The wizard's client-side `nextDateForDow()` had the same gap, so the UI actively steered patients onto the dead slot before the server ever saw the request.</symptom>
  <prevention_rule>Any availability check for a same-day bookable must compare the current time against the bookable's `end_time`, not just its day-of-week — both server-side (`BookingService::sessionAlreadyEndedToday()`) and client-side (`nextAvailableDate()` in the booking wizard), so the two never disagree about what "today" can still be booked.</prevention_rule>
</bug>

<bug>
  <category>Business_Logic</category>
  <symptom>Every booking confirmation SMS debited exactly 1 prepaid credit, but the gateway actually billed 3 — clinics' SMS wallets emptied roughly 3x faster than the balance implied.</symptom>
  <root_cause>`SmsService::confirmationBody()` carried the comment "Keep ASCII/English so one credit = one GSM segment for v1," but the template itself used an em dash (`—`) and a middle dot (`·`), neither in the GSM 03.38 alphabet. Any non-GSM character forces the whole SMS into UCS-2 encoding, where a segment is 70 characters instead of 160 — a body naming the clinic, patient, serial, date, doctor/session and a ticket URL runs long enough to span 3 UCS-2 segments, while `debitOneCredit()` always takes exactly one.</root_cause>
  <prevention_rule>SMS body templates must use only ASCII separators (plain hyphen `-`, not `—` or `·`) so the comment's own stated invariant — one credit per GSM segment — actually holds. A regression test (`SmsConfirmationTest::test_confirmation_body_stays_pure_ascii_so_one_credit_is_one_gsm_segment`) asserts the rendered body round-trips through `mb_convert_encoding(..., 'ASCII', 'UTF-8')` unchanged.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>The composite indexes added specifically for "the booking hot path" (`bookings_roster_index`, `bookings_bookable_date_index`, `slot_blocks_tenant_date_index`) were unusable in production: every query on that path — capacity checks, serial allocation, duplicate-booking checks, the queue status endpoint, Live Queue Control's 3-second poll — used `whereDate('booking_date', …)`, which wraps the column in a SQL `DATE()` function and prevents a B-tree index lookup on MySQL/Postgres. SQLite hid this completely since it ignores column types for comparison, so the regression was invisible locally.</symptom>
  <root_cause>26 call sites used `whereDate()` against `booking_date` / `session_date` / `slot_blocks.date` instead of a plain `where()` equality, even though all three columns are already date-cast. Separately, converting them exposed a second, real cross-driver bug: Eloquent's built-in `'date'` cast reads back a start-of-day Carbon, but writes through the model's generic datetime format (`Y-m-d H:i:s`) on save — real DATE columns (MySQL/Postgres) silently coerce that to date-only on INSERT, but SQLite has no such coercion and stores the trailing `00:00:00` literally, so a plain string-equality `where()` against `'Y-m-d'` stopped matching on SQLite specifically.</root_cause>
  <prevention_rule>Date-only columns must use a cast that also controls the value written to the database, not just the value read back — see `App\Casts\DateOnly`, now applied to `Booking::booking_date`, `LiveSession::session_date`, and `SlotBlock::date`. Never use `whereDate()` on a column that already has (or should have) an index built for equality lookups; use plain `where()` and keep the column genuinely date-only via the cast.</prevention_rule>
</bug>

## 2026-08-08T01:14:33+0600

<bug>
  <category>Business_Logic</category>
  <symptom>`/api/patients/by-phone` returned every patient's real name and visit count for any valid Bangladeshi mobile, with no authentication and a 60/min limit. Anyone could walk the number range and rebuild a clinic's patient list with names and attendance frequency.</symptom>
  <root_cause>The endpoint has to be public — the booking wizard calls it before anyone logs in — and it was built to return `pickerLabel()` (full name + age) because the wizard used the name to fill its own name field. Being unauthenticated was a deliberate, necessary choice; returning the full name was not reconsidered alongside it.</root_cause>
  <prevention_rule>An unauthenticated endpoint keyed on a guessable identifier is an oracle for whatever it returns — decide what it may reveal *because* it is public, not because a caller happens to want it. This one now returns `maskedPickerLabel()` (initials + age) and no name at all, throttled to 10/min, and the booking endpoint resolves the real name from `patient_id` server-side. Any new public lookup gets the same treatment.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>A doctor signed in at one practice could read another practice's prescription photos, consultation voice notes and printed prescriptions by requesting that tenant's URL — `/{otherTenant}/visit-records/{uuid}/photo`, `/prescriptions/{uuid}/print`.</symptom>
  <root_cause>`VisitMediaController`, `PrescriptionController`, `MedicineController` and `ConditionController` checked only the capability (`canViewVisitNotes()` etc.), which is a pure role test — `$this->role === 'doctor'`. The tenant half of authorisation came from Filament's `canAccessPanel()`, which these raw `routes/tenant.php` routes never pass through, and all panels share one host and therefore one session cookie. Route-model binding scoped the *record* to the tenant; nothing scoped the *user*. Only UUID primary keys kept it from being enumerable.</root_cause>
  <prevention_rule>Capability helpers on `User` answer "what may this role do", never "at which practice". Any route outside a Filament panel that serves tenant-owned data must also call `User::belongsToCurrentTenant()`. Covered by `ClinicalMediaPrivacyTest::test_a_doctor_from_another_practice_cannot_read_clinical_media` and `..._cannot_print_a_prescription`, which assert the practice's own doctor still gets 200 so the guard cannot be "fixed" by denying everyone.</prevention_rule>
</bug>

<bug>
  <category>Business_Logic</category>
  <symptom>Ending a live session early cancelled every patient still in the queue and told nobody. The confirmation modal said only "All remaining patients will be cancelled" — not how many, or who — and the first those patients heard of it was arriving at a closed chamber.</symptom>
  <root_cause>`LiveSessionService::endSession()` returned void, so the page had nothing to notify anyone with. Vacation mode (slot blocks) had had a per-patient WhatsApp notify list since it shipped; the same duty on the end-session path was simply never wired up, and the count was never surfaced in the confirmation.</root_cause>
  <prevention_rule>Any code path that cancels a patient's appointment must (1) state the count and the names before it commits, and (2) return the cancelled bookings so the caller can offer the WhatsApp hand-off — the shared `filament.tenant-admin.slot-block-notify` partial now takes an optional per-booking `$messages` override for exactly this. Cancelling silently is never acceptable, whatever triggers it.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>The booking wizard could not be operated by keyboard or screen reader at all. A patient could tab to the date, name and phone inputs but could not select a chamber, doctor, session or booking type — the flow was a dead end.</symptom>
  <root_cause>Every selection card was a `<div onclick="…">` with no `tabindex`, no `role` and no key handler, so only a mouse could activate it. The JS-built session and lab cards were `document.createElement('div')` for the same reason.</root_cause>
  <prevention_rule>Anything a user activates is a `<button type="button">` (or a link), never a `<div onclick>` — native elements are focusable, keyboard-activatable and announced correctly for free. Note the content model: block elements such as `<h4>`/`<p>` inside a button are invalid and read badly, so the cards use `.sc-title` / `.sc-sub` spans, with the old element selectors kept in both shells' CSS so nothing can silently lose styling.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>A patient of a past-due / suspended / read-only practice could pick a doctor, a session and a date and type their name and phone number, and was only told booking was closed after tapping Confirm.</symptom>
  <root_cause>`EnsureTenantAcceptsBookings` was applied to `POST /api/bookings` only. `GET /book` never consulted `acceptsBookings()`, so it rendered the full wizard for a tenant that could not accept the booking.</root_cause>
  <prevention_rule>When a gate blocks a submission, the page that collects the submission must check the same gate on render and say so up front. `BookingController::create()` now folds `acceptsBookings()` into `$canBookConsultation` / `$canBookLab` and passes `bookingClosedForBilling` so the existing empty state gives the honest reason ("call the clinic") instead of the schedules-not-published copy.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>The clinic homepage hero form submitted the patient's name and phone number by GET, putting them in the address bar — and therefore in browser history, web-server access logs, and the `Referer` header of every asset the booking page then loaded.</symptom>
  <root_cause>The hero was ported from a static HTML reference whose booking card was a mock; making it real meant pointing it at `/book`, and `method="get"` was the path of least resistance because the wizard already read its prefill from `URLSearchParams`.</root_cause>
  <prevention_rule>Patient identifiers never travel in a URL. The hero POSTs to `BookingController::prefill()`, which flashes the values to the session and redirects (Post/Redirect/Get — a refresh does not re-submit either), and the wizard now reads prefill from a server-rendered `config.prefill` instead of the query string. Deep links that carry no PII (`?doctor=`, `?test=`, `?session=`) still work, resolved server-side by `prefillFrom()`.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>Daily Roster's "Call to Chamber" could take the doctor off a patient who was mid-consultation, knocking them off the outdoor screen and their own ticket. Four other queue mutations (`reinstatePatient`, `markDelay`, `pauseSession`, `resumeSession`) wrote without the row lock every sibling mutation uses. Separately, a paused session whose `paused_at` was null could never be resumed — the button silently did nothing.</symptom>
  <root_cause>`callSpecificPatient()` had the "never interrupt a consult" guard; `bringBookingToChamber()`, added later for the roster, did not, and returned void so the UI could not report a refusal either. The unlocked mutations predate the locking convention. `resumeSession()` wrapped its whole body in `if ($liveSession->paused_at)`, conflating the elapsed-time accounting (which needs the timestamp) with returning to `active` (which does not).</root_cause>
  <prevention_rule>Every queue mutation goes through `DB::transaction` + `lockSession()`, no exceptions. A guard that protects a patient mid-consult belongs on every path that can move the current booking, not just the one it was written for — `bringBookingToChamber()` now returns bool and Daily Roster surfaces the refusal as a warning. A recovery action must never be gated entirely on optional state; compute what needs it, and always perform the recovery.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>After `App\Casts\DateOnly` made date-only storage genuinely `Y-m-d`, `LiveSession::firstOrCreate()` in `startSession()` and `bringBookingToChamber()` began missing existing rows and tripping the unique index on `(tenant_id, schedule_session_id, session_date)`.</symptom>
  <root_cause>Those calls passed a Carbon instance as the `session_date` attribute. `firstOrCreate()` uses that array as the WHERE clause as well as the insert payload, and a Carbon binds as `'Y-m-d H:i:s'` — which had matched only because the column previously stored a `00:00:00` time component too. Latent all along; the cast exposed it.</root_cause>
  <prevention_rule>Pass date-only columns as `->toDateString()`, never a Carbon, anywhere the value becomes a query binding (`where`, `firstOrCreate`, `updateOrCreate`). A model cast controls what is written, not what is bound in a WHERE.</prevention_rule>
</bug>

## 2026-08-08T01:46:24+0600

<bug>
  <category>Code</category>
  <symptom>The application could not be installed on MySQL at all. `php artisan migrate` failed on the very first migration with "SQLSTATE[HY000] 1824 Failed to open the referenced table 'tenants'".</symptom>
  <root_cause>`0001_01_01_000000_create_users_table` declares a foreign key to `tenants`, but the tenants table was published by stancl/tenancy as `2019_09_15_000010` — which sorts *after* it. SQLite does not resolve foreign-key targets at CREATE time, so the whole schema appeared healthy for months; MySQL/InnoDB rejects an FK to a table that does not exist yet.</root_cause>
  <prevention_rule>`tenants` is the root of the schema — nineteen tables reference it — so it is now `0000_01_01_000000_create_tenants_table`, ahead of Laravel's own `0001_…` migrations, with a comment saying why it must not be renumbered. More generally: migration order is a real constraint that SQLite cannot check for you, which is what the new `phpunit-mysql` CI job exists to catch.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>On MySQL, `create_booking_lab_test_table` failed with "6125 Failed to add the foreign key constraint. Missing unique key for constraint … in the referenced table 'bookings'".</symptom>
  <root_cause>`booking_lab_test` forms a composite FK against `bookings (tenant_id, id)`, but that unique key was added by `2026_07_25_175000_add_indexes_to_bookings_and_slot_blocks` — which runs an hour later in migration order. MySQL requires the referenced unique key to exist when the FK is created; SQLite never checks, so the gap was invisible.</root_cause>
  <prevention_rule>A unique key that exists so other tables can form a composite foreign key belongs in the referenced table's own create migration, not a later index pass. Moved into `create_bookings_table` with a comment; the later migration keeps only the genuine performance indexes.</prevention_rule>
</bug>

<bug>
  <category>Business_Logic</category>
  <symptom>On MySQL, `add_cancellation_tracking_to_bookings_table` failed with "1830 Column 'tenant_id' cannot be NOT NULL: needed in a foreign key constraint SET NULL".</symptom>
  <root_cause>The migration declared a composite FK `(tenant_id, slot_block_id)` → `slot_blocks (tenant_id, id)` with `nullOnDelete()`. `ON DELETE SET NULL` applies to *every* column in the key, so deleting a slot block would have nulled the booking's own `tenant_id` and severed it from its practice. MySQL refuses because `tenant_id` is NOT NULL — it was protecting the data. SQLite accepted the definition silently, so a genuinely wrong constraint sat in the schema unnoticed.</root_cause>
  <prevention_rule>`nullOnDelete()` on a composite foreign key nulls the whole key, so it is only ever correct when every column in it is expendable. A tenancy key never is. Reduced to a single-column FK on `slot_block_id` → `slot_blocks(id)`, which is what the feature actually meant ("forget which block cancelled this booking"); cross-tenant references are already prevented by the global scope and a globally unique PK.</prevention_rule>
</bug>

## 2026-08-08T09:16:59+0600

<bug>
  <category>Code</category>
  <symptom>"Mark Late" and "Cancel Session" on Live Queue Control threw `UniqueConstraintViolationException` on `live_sessions.tenant_id, schedule_session_id, session_date` — a 500 on the queue runner's screen mid-session — whenever a live session row for today already existed.</symptom>
  <root_cause>Self-inflicted, by the `App\Casts\DateOnly` change made earlier the same day. Both actions resolve today's session with `LiveSession::firstOrCreate()` keyed on `'session_date' => Carbon::today()`. That key array is the WHERE clause as well as the insert payload, and a Carbon binds as `'Y-m-d H:i:s'`. Once `DateOnly` made SQLite store a genuine `'Y-m-d'`, the lookup stopped matching, so `firstOrCreate` fell through to an INSERT that collided with the row it had failed to find. The two equivalent call sites in `LiveSessionService` were fixed when the cast landed; these two in `LiveQueueControl` were missed. It crashes on SQLite (dev + CI) but **not** MySQL, which coerces the datetime to a DATE and matches — the reverse of the usual SQLite-hides-it pattern, and the reason the MySQL validation pass did not surface it.</root_cause>
  <prevention_rule>When a cast changes how a column is *stored*, every query binding against that column has to change too — grep the whole codebase for the column name, not just the service that owns it. For date-only columns specifically: never bind a Carbon, always `->toDateString()`. Covered by `LiveQueueControlPageTest::test_session_lifecycle_actions_reuse_todays_live_session`, which drives both Livewire actions against an existing session row and asserts no second row is created; it fails on both cases if the `->toDateString()` is removed.</prevention_rule>
</bug>

## 2026-08-08T09:53:43+0600

<bug>
  <category>Business_Logic</category>
  <symptom>Cancellation SMS billed the gateway for 3 sends while the clinic's prepaid wallet was debited 1 credit. A clinic that bought 500 credits could actually send about 165, with the balance reading roughly 3x higher than the truth right up until it hit zero mid-clinic.</symptom>
  <root_cause>Two days after the identical bug was fixed on `confirmationBody()`, the per-doctor notify feature added a path that sends caller-supplied text: `LiveQueueControl::notifyCancelledAction()` builds a WhatsApp message containing a typographic dash and the notify list forwards that same string to `NotifySmsController` as an SMS body. One non-GSM character drops the whole message from 160 characters per segment to 70, so a 138-character notice became 3 segments. `debitOneCredit()` takes exactly one regardless. The earlier prevention rule — "SMS body templates must use only ASCII separators" — constrained templates, and this text is not a template; nothing checked the body actually sent. A second, related hole: `NotifySmsController` accepts a free-text `message` with no length cap, so a 647-character staff message was 5 segments on 1 credit.</root_cause>
  <prevention_rule>Do not rely on message authors following an encoding rule; enforce it where every message converges. `SmsService::send()` now runs each body through `App\Support\GsmText::toSingleSegment()` — transliterating to the GSM alphabet (which also renders a Bangla patient name readably rather than deleting it) and truncating prose, never a link, to fit one segment. `GsmText::segments()` is encoding-aware (160/153 for GSM, 70/67 for UCS-2), and the wallet debits that count via `debitCredits()`, so the balance cannot overstate again even in the one case a segment is impossible. Authors may keep real typography — the shared WhatsApp copy still shows an em dash.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>Fitting every SMS to one segment reduced the prescription notice to a bare URL: the patient received an unexplained link from an unknown number, which reads exactly like a phishing text.</symptom>
  <root_cause>A signed prescription share link is ~181 characters — longer than a whole 160-character segment before any words — so the "always fit one segment" rule had no room for context and the first implementation returned the URL alone. Caught by the existing `NotifyChannelsTest`, which asserts the body contains "view:".</root_cause>
  <prevention_rule>When a size limit cannot be met, degrade by charging honestly, never by stripping meaning. `GsmText::toSingleSegment()` keeps the surrounding words when a link alone exceeds a segment and lets the message run to two, which `SmsService` then bills. Restoring true one-credit prescription SMS needs a short redirect link, not a shorter sentence.</prevention_rule>
</bug>

## 2026-08-08T10:36:55+0600

<bug>
  <category>UI/UX</category>
  <symptom>"Mark Late" texted every waiting patient one at a time inside the staff member's own request, and spent one SMS credit per patient without mentioning either. Thirty people waiting meant up to ten seconds each on the gateway — minutes of a frozen Live Queue Control screen at the exact moment staff need it — and thirty credits gone silently.</symptom>
  <root_cause>`LiveSessionService::markDelay()` looped `SmsService::sendDoctorLateNotices()` synchronously. The action also had no confirmation step, unlike End Session, which already names the patients it is about to cancel.</root_cause>
  <prevention_rule>Anything that calls an external service once per patient belongs off the request: `SendDoctorLateNotices` is dispatched with `->afterResponse()`, not queued, because this app runs no worker and a queued job would silently never send. Any action that spends prepaid credits must state the count and the cost before it is confirmed, and say so when the wallet cannot cover everyone. Both halves are pinned by `LiveQueueControlPageTest`, and the cost warning is asserted off the mounted action rather than the helper method, so unwiring it from the modal fails the build.</prevention_rule>
</bug>

## 2026-08-08T13:47:38+0600

<bug>
  <category>UI/UX</category>
  <symptom>In the visit-notes / Write prescription modal, pressing "Add medicine" left every medicine already filled in fully expanded. By the third or fourth drug the doctor was scrolling past finished rows — mid-consultation, with the patient in the chair — to reach the empty one.</symptom>
  <root_cause>The repeater was configured `->collapsed(fn ($item) => filled($item->getRawState()['medicine_name']))`, which reads like "collapse a row once it has a medicine" but is not that. It only seeds an item's *initial* Alpine `isCollapsed` value when that item's DOM node is first rendered. Livewire's morph preserves the client-side state of nodes that already exist, so a row the doctor had just filled in kept `isCollapsed: false` forever. Filament's own add action even calls `$component->collapsed(false, …)` for the duration of that request, so the closure is not consulted for existing rows at all.</root_cause>
  <prevention_rule>Anything that must change the state of an *already-mounted* Alpine component has to be pushed to the client as an event; a server-side "initial state" closure cannot reach it. Use the event Filament itself uses (`repeater-collapse`, the one behind "Collapse all"). Two spellings of the wiring fail silently and both look right in review, so the attributes are asserted rather than the intent: `extraAttributes(['x-on:click' => …])` is **dropped**, because `Action::toButtonHtml()` seeds `x-on:click` into the bag and `ComponentAttributeBag::merge()` lets the bag win over merged defaults; and `alpineClickHandler()` **replaces** the action's `wire:click`, giving a button that collapses the list but never adds a row. The working form is `extraAttributes(['x-on:click.capture' => …])`. `CompleteVisitCallNextSplitTest::test_add_medicine_collapses_finished_rows_and_still_adds_one` rebuilds the merged bag exactly as `toButtonHtml()` does and fails on all three wrong variants.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>Follow-on from the collapse fix above, reported by the owner the same session: with finished rows now folding away, pressing "Add medicine" left the doctor looking at empty space — the new row was off-screen and they had to scroll back up to type into it.</symptom>
  <root_cause>Collapsing the rows above removes their height from the document. The scroll offset does not move with it, so the shortened list now sits entirely above the viewport while the freshly appended row is off the top of the screen. The fix for the first bug created the second: nothing repositioned the view after the layout shrank.</root_cause>
  <prevention_rule>When an interaction collapses or removes content above the thing the user is about to type into, it must also bring that thing back into view — the scroll position is not self-correcting. The add action's `after()` hook dispatches `VisitNotesFormSchema::MEDICINE_ADDED_EVENT` once the row actually exists (a click-time handler is too early — the row is added by a Livewire round trip), and the repeater listens on `.window` and scrolls its last item into view. `CompleteVisitCallNextSplitTest::test_adding_a_medicine_announces_itself_so_the_new_row_is_scrolled_to` drives the real action and asserts both halves; it fails if either the dispatch or the listener is removed. Note when testing repeater actions: the browser passes a schema *key* (`mountedActionSchema0.prescription_items`), not the state path — the wrong value makes the call succeed while adding nothing, which reads as a passing test.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>On phones the Consult Screen showed **two** "Complete visit" buttons — one in the page header, one in the sticky bottom bar. On desktop the "Write prescription" button wrapped onto two lines.</symptom>
  <root_cause>Two independent layout slips. The sticky bar was added to put the queue actions within thumb reach on phones and is hidden from 768px up, but nothing ever hid the page's own header actions below that width, so both copies rendered together on mobile — the same three actions, under the same conditions. Separately, `.cs-primary-btn` drops to `width: auto` at 768px and then sits as a flex item beside a summary block that can grow; with the default `flex-shrink: 1` it got squeezed below its label's width, so the longer "Write prescription" broke across two lines while the shorter "Edit prescription" usually did not — which is why it looked intermittent.</root_cause>
  <prevention_rule>When one control is deliberately rendered twice for different breakpoints, the two visibility rules must be complements, not independent guesses — hiding one at `min-width: 768px` obliges the other to be hidden at `max-width: 767px`, and that pairing is now commented in `consult-screen.blade.php` and recorded in `architecture.md`. A button whose label must stay on one line needs `white-space: nowrap` **and** `flex-shrink: 0`; nowrap alone only moves the failure from wrapping to overflowing. Verified in the browser on the real page: one visible Complete visit at 375px (the sticky one) and at 1280px (the header one), and the button measured 44px — a single line — with the longer "Write prescription" label at 768px, the tightest desktop width.</prevention_rule>
</bug>

## 2026-08-08T15:54:34+0600

<bug>
  <category>Business_Logic</category>
  <symptom>A mistyped blood pressure — most easily, filling systolic and tabbing past diastolic — would have thrown a validation error that stopped the whole visit from completing. The booking stayed open, the queue never advanced and the next patient was never called, over an optional note field. The doctor would also not have seen why: the message was keyed to `bp_systolic`, but inside a Filament action Livewire scopes the error bag to `mountedActions.0.data.bp_systolic`, so nothing would have appeared next to the box. Caught in review on the same branch it was written; never ran in a chamber.</symptom>
  <root_cause>Vitals validation was put in `VisitNotesFormSchema::normalizeVitals()`, called from `normalizeSubmission()` — which `VisitRecordService::submissionHasContent()` also calls. That method is a question ("is there anything here worth saving?") asked by `CompleteBookingWithVisitNotes::finish()` *before* `LiveSessionService::completeBooking()`. Turning a normaliser into a validator quietly gave a predicate the power to abort its caller, and the caller's next statement was the queue. The 2026-08-06 rule that notes are never compulsory was enforced for *blank* forms and forgotten for *wrong* ones.</root_cause>
  <prevention_rule>Nothing on the path between "staff taps Complete" and "queue advances" may throw. `submissionHasContent()` and everything it calls must always answer, including on nonsense input; its docblock now says so, and `normalizeVitals()` sanitises — dropping an unusable BP as a pair rather than refusing the submission. Field validation belongs on the form, where the doctor can see it: `vitalsSection()` carries `requiredWith` both ways, min/max and a systolic-over-diastolic rule, with `validationMessages()` so the text is the clinical sentence, not Laravel's. Both sides read one definition, `VisitNotesFormSchema::isUsableBloodPressure()`, so screen and database cannot drift apart. `VisitVitalsTest::test_bad_vitals_do_not_block_the_queue()` is the regression guard.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>On the Consult Screen's "Notes so far" chip, a visit with both weight and blood pressure recorded showed only the blood pressure. The weight was in the database and appeared correctly in the two other places vitals are drawn.</symptom>
  <root_cause>The same three-branch "weight · BP" line was hand-written in three Blade files. One of the three used `@elseif` instead of a second `@if`, so the two readings became mutually exclusive in that copy alone.</root_cause>
  <prevention_rule>A formatted line that appears in more than one view is built once on the model, not per-template: `VisitRecord::vitalsSummary()` now composes it and all three views print it. Duplicating display logic across Blade files is how one copy silently drifts from the others.</prevention_rule>
</bug>

## 2026-08-08T22:47:24+0600

<bug>
  <category>Code</category>
  <symptom>After booking on the live domain, the browser jumped to a localhost ticket URL (and SMS/share links could also say localhost).</symptom>
  <root_cause>`BookingController` returned an absolute `ticket_url` from `tenant_web_route()`. Behind nginx/caddy, PHP sees `127.0.0.1` and Laravel had no `trustProxies`, so absolute URLs baked in localhost. SMS links separately use `APP_URL`, which was often still the local default.</root_cause>
  <prevention_rule>Return a relative path for post-booking `ticket_url` (`absolute: false`); trust reverse proxies in `bootstrap/app.php`; keep production `APP_URL=https://…` and real `CENTRAL_DOMAINS` (enforced by `app:production-check`).</prevention_rule>
</bug>

## 2026-08-08T23:17:34+0600

<bug>
 <category>Code</category>
 <symptom>Beyond the post-booking redirect, other patient and TV links could still open or poll localhost in production: prescription SMS/WhatsApp, portal "View Digital Ticket", ticket live-queue poll, outdoor screen API/audio, and the waiting-room Copy link.</symptom>
 <root_cause>Those call sites used absolute `tenant_web_route()` / `route()` / `asset()` / `url()->current()`, which bake whatever host Laravel sees (often 127.0.0.1 behind a proxy) or inherit `http` from a leftover localhost `APP_URL` onto a real clinic Domain.</root_cause>
 <prevention_rule>Links that leave the browser (SMS, WhatsApp, TV bookmark, ticket Copy) must use `TenancyUrl::publicAbsolute()` (Domain or APP_URL). Same-origin navigation and JS fetches must use `tenant_web_route(..., absolute: false)` or `public_asset()` — never absolute `asset()` / `route()` for those surfaces.</prevention_rule>
</bug>

## 2026-08-08T23:21:36+0600

<bug>
 <category>UI/UX</category>
 <symptom>Ticket "Copy link" showed or copied the ticket URL stuck to the Google Maps URL with no space (`…/bookings/uuidhttps://www.google.com/maps?…`).</symptom>
 <root_cause>Copy payload put a newline between ticket and map, then stuffed that string into `<input type="text" value="…">`. Text inputs cannot hold newlines, so the browser dropped `\n` and glued the two URLs.</root_cause>
 <prevention_rule>Never put a multi-line copy payload in an `<input type="text">` value. Show the ticket URL alone in the field; keep ticket+map (with a real newline) in a JS string for `navigator.clipboard.writeText`.</prevention_rule>
</bug>

## 2026-08-09T15:27:52+0600

<bug>
 <category>Business_Logic</category>
 <symptom>Prescription medicine dropdowns were completely empty in production — no brands, no categories, nothing to pick.</symptom>
 <root_cause>The `medicines` table is filled by `php artisan medicines:load` from `data/medicine-list-draft.csv`, not by migrations or `db:seed`. Production had run `migrate` but never the catalogue import, so the table existed but had zero rows. The UI fails silently — an empty grouped select looks like "no medicines configured" rather than throwing an error.</root_cause>
 <prevention_rule>After every fresh `migrate` on a new server, run `php artisan catalogues:load` (or include it in the deploy script). `app:production-check` now blocks production when `medicines` is empty; `composer setup` runs the load step automatically.</prevention_rule>
</bug>

## 2026-08-09T22:18:50+0600

<bug>
 <category>Code</category>
 <symptom>Live Queue Control "Open screen" did nothing (or opened a dead tab) when the practice was browsed at `127.0.0.1:8000/{tenant}/admin`.</symptom>
 <root_cause>The TV link used `TenancyUrl::publicAbsolute()`, which prefers the tenant's first Domain row (`nusraturmi.localhost`) without the dev server's port. That URL targets port 80, not `:8000`, while path tenancy lives under `127.0.0.1:8000/{tenant}/…`.</root_cause>
 <prevention_rule>Waiting-room Open/Copy links on path tenancy must use `TenancyUrl::screenBookmarkUrl()` (current request host + `/{tenant}/screen/{id}`), not `publicAbsolute()`. Reserve `publicAbsolute()` for SMS/WhatsApp and custom-domain production bookmarks.</prevention_rule>
</bug>

## 2026-08-11T16:03:15+0600

<bug>
 <category>CRO</category>
 <symptom>When the next sitting for a schedule was full (or every sitting in the coming weeks), the booking wizard showed grey **Full** cards and patients could not book a later open date — even when seats existed weeks ahead.</symptom>
 <root_cause>The schedule step only called `GET /api/bookings/availability` for the **next** occurrence of each weekly sitting. A full next Saturday disabled the card before the patient ever reached the date picker on the identity step.</root_cause>
 <prevention_rule>The public wizard must offer open dates across the full booking window via `openDatesFor()` / `GET /api/bookings/open-dates`, not gate booking on a single next-weekday snapshot per schedule card.</prevention_rule>
</bug>

## 2026-08-11T16:24:47+0600

<bug>
 <category>CRO</category>
 <symptom>On the new "When can you come?" step, tapping a date card did nothing useful — patients could not move on, and only the first card looked selectable.</symptom>
 <root_cause>`selectOpenDate` called `rebuildFlow()` which dropped `step-when` once `bookableId` and `prefilledDate` were set, then `nextStep()` could not advance because the current index was already past the shortened flow. The earliest card also used `.selected`, which made other dates look disabled.</root_cause>
 <prevention_rule>Never rebuild the wizard flow in a way that removes the step the patient is currently on before `nextStep()` runs — keep `step-when` in the flow like the type step, and reserve `.selected` for the card the patient actually tapped.</prevention_rule>
</bug>

## 2026-08-11T23:15:08+0600

<bug>
 <category>Code</category>
 <symptom>A chamber admin restoring a crafted backup ZIP could overwrite the central Super Admin account and any other chamber's rows — reassigning them into their own chamber with an attacker-chosen email and a reset password. Verified by running it: the Super Admin row came back with `tenant_id` set to the attacking chamber, `role` demoted, and the email replaced.</symptom>
 <root_cause>`DataImportService::importTableRows()` upserted with `[$primaryKey]` as the match key on a shared database, then `normalizeImportedRow()` force-wrote `tenant_id` onto whichever row that id hit. `users.id` is auto-increment, so no id had to be guessed. Being Query Builder writes, they bypassed `BelongsToTenant`'s "a record must never change hands" guard entirely.</root_cause>
 <prevention_rule>Never upsert on a bare global primary key in this shared database. Any import path must assert that every incoming primary key is unowned or owned by the target tenant **before** writing, and refuse the whole restore otherwise — pinned by `DataBackupTest::test_restore_refuses_a_zip_that_reuses_another_accounts_user_id`.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>A "replace" restore that failed partway left the chamber completely empty with no rollback — verified: row counts went from 1/1/1 (patients/bookings/chambers) to 0/0/0. Separately, a replace restore on any chamber with a live queue failed outright on a foreign key, in both the wipe and the import direction.</symptom>
 <root_cause>`wipeScope()` ran in its own `DB::transaction` and committed; the import loop then ran outside any transaction. And `TENANT_TABLES` listed `live_sessions` before `bookings` even though `live_sessions.current_booking_id` references `bookings.id` with no ON DELETE rule — the same list is read forwards to import (parents first) and reversed to delete (children first), so one wrong order was wrong both ways.</root_cause>
 <prevention_rule>Destructive wipe and the import that replaces it must share one transaction. `BackupTableMap::TENANT_TABLES` is ordered parent-before-child and must stay that way — a table's position is load-bearing in both directions. Pinned by `test_a_failed_replace_restore_rolls_back_the_wipe` and `test_replace_restore_works_while_a_queue_is_live`.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Merging two duplicate patient records detached the surviving patient from their clinical history. The doctor's Consult Screen then reported "no history" for someone whose visit notes and prescriptions were still in the database, and no screen could re-link them.</symptom>
 <root_cause>`PatientService::mergePatients()` moved only `bookings.patient_id` before deleting the duplicate. `visit_records.patient_id` and `prescriptions.patient_id` are `nullOnDelete` foreign keys, so the delete NULLed both. `moveBookingToPatient()` had the mirror-image gap — the booking moved while its visit record stayed filed under the old patient.</root_cause>
 <prevention_rule>Anything that deletes or re-parents a `patients` row must repoint every table that references it, in the same transaction, via `PatientService::repointPatientOwnedRows()`. A new patient-owned table must be added there. Pinned by `PatientMergeHistoryTest`.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Medicine and condition "learning" never recorded anything on a real consult. The ranked picker that is supposed to surface a doctor's frequent prescriptions stayed empty for the entire life of the feature; `medicine_usages` had zero rows after a genuine Complete-visit with a prescribed medicine.</symptom>
 <root_cause>`VisitRecordService` gated usage recording on `$booking->status === 'completed'`, but both completion helpers save the notes while the booking is still `in_chamber` and flip the status immediately afterwards. The existing test passed because it called the service directly and set `status = 'completed'` by hand first, so it exercised an order production never uses.</root_cause>
 <prevention_rule>A test for behaviour that hangs off a status transition must drive the real entry point (`CompleteBookingWithVisitNotes`), not the service with the status pre-set. Pinned by `test_completing_a_visit_records_medicine_usage_through_the_real_path`, with `test_writing_a_prescription_mid_consult_does_not_record_usage` guarding the behaviour the gate was protecting.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>"Cancel Session (Doctor Absent)" marked the patient who was mid-consult as `cancelled`, discarding a visit that had actually happened along with any notes written during it — and told none of the patients it turned away, unlike "End session" which hands staff a WhatsApp link per person.</symptom>
 <root_cause>`markAbsent()` cancelled everything not already completed/cancelled/no-show, without `endSession()`'s `in_chamber` carve-out, and returned `void` so the caller had nothing to notify from. Its confirmation modal also named no count and no patients.</root_cause>
 <prevention_rule>Every path that cancels a session's bookings must complete `in_chamber` first and return the bookings it cancelled, so the caller can offer the notify hand-off. Cancelling a patient's appointment without telling them is the failure that return value exists to prevent. Pinned by `test_mark_absent_completes_the_mid_consult_patient_and_returns_who_it_cancelled`.</prevention_rule>
</bug>

## 2026-08-12T01:47:03+0600

<bug>
 <category>Business_Logic</category>
 <symptom>A doctor's saved default dose/frequency/duration was ignored for exactly the brands most worth saving. Editing NAPA on **My medicines** appeared to work — the row was written — but prescribing NAPA still prefilled the catalogue's values, so the doctor re-typed the same correction every visit and concluded the feature did nothing.</symptom>
 <root_cause>`MedicineService::search()` concatenated catalogue and usage rows, sorted by rank, and kept the first row per brand. The doctor's own entry carries a +15 bonus, but a curated or pinned catalogue SKU carries a tier boost of up to 32, so for any well-known brand the catalogue row won the dedupe and the usage row was silently discarded. The two numbers were introduced for different purposes (search ordering vs curation preference) and were never meant to be compared against each other.</root_cause>
 <prevention_rule>Deduplication must never be a side effect of ranking. When two rows describe the same thing, choose by provenance explicitly — the doctor's own row wins — and use rank only for ordering what survives.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>The CI step that asserts the production-readiness gate can pass could never pass. It ran `app:production-check --strict` against a freshly migrated MySQL database, and the gate treats an empty medicine catalogue as a blocker.</symptom>
 <root_cause>The gate check and the workflow were written at different times. A real deploy runs `catalogues:load` right after migrating (`composer setup` does exactly that), but the workflow went straight from `migrate` to the gate, so it was asserting a state no deployment ever ships in. Adding the empty-`conditions` blocker would have made a green-looking step fail for a second reason without the first ever being noticed.</root_cause>
 <prevention_rule>A CI step that simulates a deploy must run the same steps a deploy runs, in the same order. If a gate blocks on state that a setup command produces, that command belongs in the job before the gate.</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>Nineteen patient-facing strings in the booking wizard — including "Pick a date", the WhatsApp number help text and the "no seats available" message — had no Bangla translation, so a Bangla-reading patient hit English mid-booking. `PatientFacingBanglaTest` was failing.</symptom>
 <root_cause>The strings were added to `resources/views/tenant/partials/booking-wizard.blade.php` without the matching `lang/bn.json` entries. The test that exists precisely to catch this was red rather than blocking, so the gap survived.</root_cause>
 <prevention_rule>A red test is a broken feature until proven otherwise. Run the full suite before declaring work done, and never add a `__()` string to a patient-reachable view without its `bn.json` entry in the same change.</prevention_rule>
</bug>

## 2026-08-12T01:55:19+0600

<bug>
 <category>UI/UX</category>
 <symptom>Tapping a chief-complaint chip a second time after attaching a duration (Fever → × 3 days → Fever again) appended a duplicate: `Fever × 3 days, Fever`.</symptom>
 <root_cause>`appendComplaint` compared whole comma-separated segments. After a duration chip ran, the segment was `Fever × 3 days`, which did not equal `Fever`, so the duplicate guard treated it as new.</root_cause>
 <prevention_rule>When a chip can later gain a qualifier on the same segment, strip the qualifier before the duplicate check — compare the complaint itself, not the decorated string.</prevention_rule>
</bug>

## 2026-08-12T12:50:00+0600

<bug>
 <category>Business_Logic</category>
 <symptom>Doctors typing Latin timing shorthand `ac` or `pc` on the Rx desk got the opposite meal instruction — a PPI written `ac` saved as after food instead of before food.</symptom>
 <root_cause>`PrescriptionTiming::shorthandMap()` and the Alpine `timingShorthand` object in `rx-desk.blade.php` mapped `ac` → `after_food` and `pc` → `before_food`. Latin ante cibum / post cibum are the reverse of those mappings; only the English `af`/`bf` tokens were correct.</root_cause>
 <prevention_rule>Clinical abbreviations must be checked against their Latin meaning, not assumed to mirror English shorthand. `PrescriptionTimingTest` asserts `ac` → `before_food` and `pc` → `after_food`.</prevention_rule>
</bug>

## 2026-08-12T15:38:21+0600

<bug>
 <category>Business_Logic</category>
 <symptom>Every medicine on the desktop Rx desk offered the same five dose chips — 500 mg, 10 mg, 20 mg, 40 mg, 5 mg — regardless of the drug. The list simultaneously offered strengths that do not exist (a 5 mg NAPA nobody manufactures) and hid ones that do (NAPA's 120 mg/5 ml paediatric syrup, 125/250/500 mg suppositories, 80 mg/ml drops), so the doctor typed the dose by hand on every line and the chips were noise at best.</symptom>
 <root_cause>`doseChipsFor()` in `rx-desk.blade.php` was a hardcoded JavaScript array, written before the desk had a way to reach the catalogue. The correct per-brand lookup already existed and was already used by the phone form (`VisitNotesFormSchema::doseOptionsForBrand()`), so the desk was a second, dumber implementation of a rule that was solved elsewhere — the same shape of failure as the two copies of the Rx safety rules and the two `ac`/`pc` maps.</root_cause>
 <prevention_rule>Dose strengths come from `MedicineService::doseOptionsForBrand()` and nowhere else — both pickers read it (the desk via `GET /api/medicines/doses`), and a brand with no catalogue row shows no chips rather than a plausible-looking guess. `MedicinePickerTest::test_both_dose_pickers_read_the_same_catalogue_lookup` and `DesktopRxPadTest::test_the_desk_never_ships_a_hardcoded_dose_list` fail if a literal list comes back.</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>A doctor working at the desktop Rx desk could not record **why** a medicine was given. The field existed end to end — `prescription_items.indication` is written by the service, saved by the desk payload, and printed under the brand — but the desk rendered it as read-only text, so the only way to fill it was to abandon the desk and open the phone modal.</symptom>
 <root_cause>The desk was built as a new surface against the same schema, and the brand cell showed `generic_name · indication` as a display line. A field that renders when populated looks finished, which is why nobody noticed that no input on the page could populate it.</root_cause>
 <prevention_rule>A column the print template renders must have an input on every surface that writes prescriptions, not only on the first one built. `DesktopRxPadTest::test_the_desk_can_write_a_reason_for_each_medicine` saves through the real Livewire path and asserts the value lands on the item.</prevention_rule>
</bug>

## 2026-08-12T15:47:16+0600

<bug>
 <category>UI/UX</category>
 <symptom>The desktop Rx desk showed **two Complete visit buttons** — one in the desk's own sticky bar, one in the Filament page header — both green, both doing the same thing.</symptom>
 <root_cause>The desk was added as a new full-width surface on Consult Screen and hid the old layout (`.cs-layout.is-desk-active`) and the phone strip (`.cs-sticky-actions.is-desk-active`), but not the page header's action container. A rule for exactly this already existed for phones (`@media (max-width: 767px) { .fi-header-actions-ctn { display: none } }`); the desk breakpoint was simply never given the same treatment.</root_cause>
 <prevention_rule>Any surface that renders its own copy of a page header action must hide `.fi-header-actions-ctn` at the widths where it is visible. `DesktopRxPadTest::test_the_desk_leaves_only_one_complete_visit_button_on_screen` fails if that rule is dropped.</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>Preview on the Rx desk opened the prescription in a **new browser tab**, dropping the doctor onto a bare print page mid-consult with the browser's Back button as the only way back to the patient.</symptom>
 <root_cause>Preview and Save & print shared one code path — `saveRxDesk()` returns the print URL and the Alpine `save()` called `window.open()` for either. Preview is a "check it before I commit" action and print is a "send it to paper" action; treating them as the same because they resolve the same URL is what put the doctor on another page.</root_cause>
 <prevention_rule>Preview mounts `previewPrescriptionAction()` and frames the print route in a modal; only Save & print leaves the page. The framed URL is resolved on the server from the record, never accepted from the client. Pinned by `DesktopRxPadTest::test_preview_opens_a_modal_over_the_desk_rather_than_a_new_page`.</prevention_rule>
</bug>

## 2026-08-12T16:01:00+0600

<bug>
 <category>Business_Logic</category>
 <symptom>Typing `napa` into the Rx desk's typing box and pressing Enter added a row with the brand and nothing else — no dose, no frequency, no duration, no timing, and no generic under the brand — even though the same doctor typing `napa` into the Brand cell got all of it prefilled. No suggestions appeared while typing there either, so the "fast" way of writing a prescription was the one that filled in the least.</symptom>
 <root_cause>`commitShorthand()` only ever split the text on spaces and pattern-matched tokens. It never asked the catalogue anything, so everything the search endpoint knows — the doctor's own saved default, the catalogue default, the generic name — was reachable from the Brand cell and unreachable from the box directly under it. The box was written as a keyboard shortcut for the token grammar rather than as a second door into the same lookup.</root_cause>
 <prevention_rule>Every way of naming a medicine on the pad resolves through the same `/api/medicines/search` lookup and the shared `applyPrefill()` / `fillOnlyStrength()` helpers. A typed name only prefills on an **exact** brand match (`catalogueMatch()`); anything less exact must be chosen from the suggestion list, because `nap` silently becoming NAPA EXTRA is a different drug on a signed document. Pinned by `DesktopRxPadTest::test_the_typing_box_searches_the_catalogue_instead_of_only_parsing_tokens`.</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>The Rx pad opened as bare column headings with no row under them, so the doctor's first action had to be finding "+ Add medicine" or the typing box. An empty table also left a permanent grey scrollbar slab directly under the headings, which read as a broken element, and the typing box drew a red-looking focus ring on click that read as a validation error on an empty, perfectly valid field.</symptom>
 <root_cause>The desk seeded `items` straight from the saved record, so a new visit started with an empty array and rendered a table with no `<tr>`. The desk's inputs are plain HTML rather than Filament fields and had no `:focus` style of their own, so they fell through to the browser's default ring.</root_cause>
 <prevention_rule>The pad always shows one waiting row (`init()` pushes an empty item) and the blank row is dropped on save — both client-side in `payload()` and server-side in `VisitRecordService::syncPrescription()`, so an untouched row can never reach a prescription. Desk inputs carry their own primary-coloured focus style. Pinned by `DesktopRxPadTest::test_the_pad_opens_with_a_row_waiting_and_drops_it_if_left_empty`.</prevention_rule>
</bug>

## 2026-08-12T16:08:33+0600

<bug>
 <category>UI/UX</category>
 <symptom>Typing a medicine name in the Brand cell (or `na` / `Nap` in the typing box) produced no visible suggestion list. The doctor concluded search was broken and typed full brand names by hand — which then also left Dose / Frequency empty, because prefill only runs when a catalogue row is chosen.</symptom>
 <root_cause>Two layered faults. (1) `.cs-rx-desk__table-wrap { overflow-x: auto }` makes the browser compute `overflow-y: auto` as well, which clipped the suggestion `<ul>` inside the brand cell — the API answered, Alpine filled `medicineResults`, and the list was painted into a clipped box. (2) Absolute `url('/api/medicines/…')` against `APP_URL=localhost` can hit the central host instead of the tenant domain and return empty/403.</root_cause>
 <prevention_rule>The table wrap stays `overflow: visible`; brand suggestions are absolutely positioned in `.cs-rx-desk__brand-cell`. Medicine/condition API URLs on the desk are relative paths (`/api/medicines/search`). `DesktopRxPadTest::test_brand_suggestions_are_not_clipped_inside_the_table` fails if the overflow-x rule returns.</prevention_rule>
</bug>

## 2026-08-12T16:17:49+0600

<bug>
 <category>Code</category>
 <symptom>Typing na on the Rx desk produced console 404s for http://127.0.0.1:8000/api/medicines/search?q=na and no suggestion list. The catalogue was fine; the browser was calling the wrong URL.</symptom>
 <root_cause>The desk shipped a bare /api/medicines/search path. On local php artisan serve (path tenancy at /{slug}/admin) that hits the central app, which has no such route — the real endpoint is /{slug}/api/medicines/search. Custom-domain tenants were unaffected, which is why domain-based tests stayed green.</root_cause>
 <prevention_rule>Desk (and any Alpine fetch) API URLs go through tenant_web_url(), same as the voice recorder. DesktopRxPadTest::test_medicine_search_url_includes_the_tenant_slug_on_the_central_host asserts both the helper output and a live GET on the prefixed path.</prevention_rule>
</bug>

## 2026-08-12T16:46:44+0600

<bug>
 <category>Business_Logic</category>
 <symptom>After picking NAPA, Dose could show 80 mg/ml (paediatric drops) while Frequency, Duration and Timing stayed blank — so the doctor still had to tap each cell even though the catalogue knows the adult tablet line (1+1+1, 3 days, after food).</symptom>
 <root_cause>Several catalogue SKUs share one brand. Search collapse and My-medicines fallback could land on a drops/injection row that has a strength but no frequency/duration/timing. Dose chips then only wrote the strength, never backfilled the rest from the brand line that does have defaults.</root_cause>
 <prevention_rule>Collapse to the SKU with the most complete dosing defaults (brandDosingDefaults / searchRowCompleteness). The doses API returns those brand defaults; the desk applies them on pick and on dose-chip via applyBrandDefaults, without overwriting cells the doctor already filled. Timing gets the same on-focus chips as frequency/duration.</prevention_rule>
</bug>

## 2026-08-12T17:23:06+0600

<bug>
 <category>UI/UX</category>
 <symptom>Timing never autofilled on the Rx desk even when frequency and duration did. Prefill wrote after_food into the row, but the Timing column stayed on "—".</symptom>
 <root_cause>Timing used a &lt;select&gt; whose &lt;option&gt;s were built with Alpine x-for. When prefill set item.timing, the options were not yet a stable matching set; the browser fell back to the empty "—" option and x-model wrote "" back over the prefill. Text inputs (dose / frequency / duration) never had that round-trip.</root_cause>
 <prevention_rule>Timing &lt;option&gt;s are Blade-rendered (@foreach), not Alpine x-for inside the select. Chips may still use x-for. DesktopRxPadTest::test_timing_select_options_are_blade_rendered_so_prefill_is_not_wiped fails if x-for options return.</prevention_rule>
</bug>

## 2026-08-13T00:43:22+0600

<bug>
 <category>Business_Logic</category>
 <symptom>The nightly follow-up reminder run had no error isolation: one chamber's bad row aborted the whole job, so every chamber after it in the cursor silently got no reminders that morning — patients were never told to come back for their recheck, and nobody noticed because the command runs unattended at 07:00.</symptom>
 <root_cause>`SendFollowUpRemindersCommand::handle()` looped `Tenant::cursor()` calling `processTenant()` with no try/catch, and `FollowUpReminderService::processTenant()` had none around its per-visit loop either. Any throw — an SMS gateway timeout, an unresolvable doctor, a malformed row — escaped both loops. `tenancy()->end()` was also skipped on that path, leaving the failed chamber's context bound as the loop moved on.</root_cause>
 <prevention_rule>Every unattended cross-tenant batch isolates each tenant AND each record, logs what it skipped, and ends tenancy in a `finally`. It must also exit non-zero when anything was skipped, so a partly-failed scheduled run is visible instead of reported as a clean night. `SendDoctorLateNotices` already did per-patient isolation — new batch jobs must inherit that shape. Pinned by `FollowUpReminderTest::test_one_failing_chamber_does_not_stop_the_rest` and `::test_one_bad_patient_does_not_stop_the_clinics_other_reminders`.</prevention_rule>
</bug>

## 2026-08-13T10:46:29+0600

<bug>
 <category>Code</category>
 <symptom>Opening the Web Pages editor (or any panel page that compiles a real-time facade) crashed with `tempnam(): file created in the system's temporary directory` in `AliasLoader.php`.</symptom>
 <root_cause>Laravel 12 writes facade cache via `tempnam()` into `storage/framework/cache`. PHP 8.4+ warns when that folder is missing or not writable and the file lands in `/tmp` instead; debug mode turns the warning into an unhandled exception. Livewire's upload folder `storage/app/private/livewire-tmp` was also missing, so FileUpload had nowhere to stage files.</root_cause>
 <prevention_rule>`RuntimeDirectories::ensure()` must run from `AppServiceProvider::register()` so cache, session, view, Livewire tmp, and public website-media folders exist and are writable before the first request. Do not rely on a human remembering `chmod`.</prevention_rule>
</bug>

## 2026-08-13T11:00:26+0600

<bug>
 <category>Code</category>
 <symptom>Hero / educational-video image upload in Web Pages did not show on the site (or vanished after picking a file).</symptom>
 <root_cause>Three stacked faults: (1) `public/storage` still pointed at the old SolDoc checkout, so `/storage/…` URLs never hit ChamberQ files; (2) stancl suffixed the `public` disk into `storage/tenant{id}/app/public`, which is not web-visible; (3) FileUpload `dehydrateStateUsing` rewrote Livewire's temp file into a `/storage/…` path before the file was stored, wiping the upload.</root_cause>
 <prevention_rule>Website FileUpload stays on the unsuffixed `public` disk; Livewire stages on a dedicated `livewire-tmp` disk; `RuntimeDirectories` must recreate `public/storage` when it does not point at this app's `storage/app/public`; convert disk paths to `/storage/…` only on `WebPage` save, never while Livewire still holds a temp file.</prevention_rule>
</bug>

## 2026-08-13T12:48:03+0600

<bug>
 <category>Code</category>
 <symptom>Opening a chamber admin page (My medicines, and any other tenant page that touches cache) crashed with `BadMethodCallException: This cache store does not support tagging`.</symptom>
 <root_cause>Stancl's `CacheTenancyBootstrapper` wraps every `Cache::` call in tags. Redis/array can tag; the default `CACHE_STORE=database` (and `file`) cannot. Tests used `array`, so the crash never showed in PHPUnit.</root_cause>
 <prevention_rule>Never enable Stancl's tagged cache bootstrapper against a store that cannot tag. Use `App\Tenancy\CacheTenancyBootstrapper`: tags when the store supports them, `setPrefix` on the live store otherwise. Pin with a test that puts/gets cache on the `database` driver inside an initialized tenant.</prevention_rule>
</bug>

## 2026-08-13T18:09:24+0600

<bug>
 <category>UI/UX</category>
 <symptom>The printed / shared prescription showed raw HTML on every medicine line — `<span class="pad-l-bn">খাবারের পর</span>` — instead of the Bangla timing the patient needs to read.</symptom>
 <root_cause>`Bilingual::html()` correctly returns an `HtmlString` so Blade will not escape it. `PrescriptionItem::timingBilingualLabel()` was typed `?string`, which stringifies that markup. `{{ }}` then escaped the string, so the tags became visible text.</root_cause>
 <prevention_rule>A method that returns `Bilingual::html()` must keep the `HtmlString` type. `?string` is a silent stringify. Pin with a print-page assertion that the timing markup is real HTML (`<span class="pad-l-bn">`) and that `&lt;span` does not appear.</prevention_rule>
</bug>

## 2026-08-13T18:25:48+0600

<bug>
 <category>UI/UX</category>
 <symptom>Doctors could not find the premade advice. The Advice box was empty, and the "Add advice" chip sat under Diagnosis — then vanished after the first save.</symptom>
 <root_cause>Starter advice was only stored in Alpine after `pickDiagnosis()`. The pad remounts on every save (`wire:key` includes `updated_at`), which reset `diagnosisAdvice` to empty. The chip also lived under Diagnosis rather than in the Advice card where doctors look.</root_cause>
  <prevention_rule>Anything offered because of the current coded diagnosis must be passed from the server on every pad render (`$written->condition->adviceForLocale()`), not only on the pick click. Put the chip in the Advice card. Pin with a Consult Screen test that a saved coded diagnosis still prints the starter sentence in the desk HTML.</prevention_rule>
</bug>

## 2026-08-13T21:07:28+0600

<bug>
  <category>UI/UX</category>
  <symptom>On the desktop Consult Screen pad, the patient strip (name, Preview, My paper, Save & print) covered Filament's top bar — the menu, chamber name, and Complete visit.</symptom>
  <root_cause>`.cs-rx-desk__bar` was `position: sticky; top: 0; z-index: 30`, the same seat as `.fi-topbar-ctn` (`sticky top-0 z-30`, `min-h-16`). Later in the DOM, the strip painted on top.</root_cause>
  <prevention_rule>A sticky element inside page content must not use `top: 0` / z-index 30 when Filament's topbar already occupies that seat. Offset by the topbar height (`4rem` / `min-h-16`) and keep z-index below 30. `DesktopRxPadTest::test_the_patient_strip_sticks_below_the_filament_topbar` fails if the strip returns to `top: 0`.</prevention_rule>
</bug>

## 2026-08-13T23:35:10+0600

<bug>
  <category>Business_Logic</category>
  <symptom>A doctor could write a whole prescription on the desktop Rx pad, tap the green **Complete visit**, and end the consult with an empty or stale prescription — told the visit had completed, with no warning that the pad was never saved. The patient walked out with nothing.</symptom>
  <root_cause>The pad held everything in Alpine memory and only posted from Preview or Save & print. Complete visit is a Filament page header action whose form is filled from `stateFromRecord($this->currentVisitRecord)` — the *stored* record — so it never saw the typed state. A second path to the same loss: `wire:key` carried the visit record's `updated_at`, so any write to that row (a staff paper entry, a follow-up stamp) changed the key on the next 3s poll and remounted the component, discarding whatever was typed.</root_cause>
  <prevention_rule>A writing surface that holds state client-side must keep the server in step on its own, and must say on screen whether it has. The pad autosaves 1.5s after any change and immediately on any pointerdown outside itself, and carries an Unsaved / Saving… / Saved badge. On failure the badge must be set to Unsaved **before** the offline outbox is attempted — doing it after left it stuck on "Saving…" whenever `enqueue` threw, which is precisely when the doctor is relying on it. Pinned by `DesktopRxPadTest::test_the_pad_saves_itself_so_complete_visit_cannot_close_on_an_unwritten_script`, `test_a_draft_save_is_silent_while_an_explicit_save_still_speaks`, `test_a_draft_save_refuses_everything_an_explicit_save_refuses` and `test_the_pad_is_not_keyed_on_the_record_timestamp`.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>After the first save on the Rx pad, every `x-show` on it stopped responding — the complaint "+ Add" picker would not open, brand suggestions never appeared, the timing and follow-up reveals froze. The pad still accepted typing and still saved, so it looked alive.</symptom>
  <root_cause>Introduced while fixing the remount data loss above. Removing `updated_at` from `wire:key` stopped the destructive remount, but that timestamp was also what made the post-save remount *clean*: a changed key makes Livewire replace the element, so Alpine re-initialises the whole subtree consistently. With a stable key Livewire morphs instead, and `x-data="rxDesk({...})"` is rendered from the record — so its attribute string changes after every save and re-runs the component's init against nodes whose effects have already been torn down.</root_cause>
  <prevention_rule>A subtree Alpine owns must be `wire:ignore`, so Livewire can never morph it, with the identity that should force a fresh mount (here the booking) carried in `wire:key` — a changed key still replaces the element even under `wire:ignore`. The methods that write from it are `#[Renderless]`. Note for testing: this class of bug is invisible to the suite, which asserts rendered markup and never executes Alpine — it was found by driving the real page in a browser and toggling state, and `test_the_pad_is_never_morphed_out_from_under_alpine` only guards the two structural markers that stand in for it.</prevention_rule>
</bug>

## 2026-08-14T01:12:58+0600

<bug>
  <category>Code</category>
  <symptom>Caught by a new test during the URL-field → uploader change, before release. Once model `saving` hooks called `PublicStoredImage::toPublicPath()` ahead of the `SafeUrl` scrub, a hostile `javascript:alert(1)` typed into an image field was stored as `/storage/javascript:alert(1)` instead of being blanked: the helper prefixed `/storage/` onto anything that was not http(s) or already absolute, and `SafeUrl` then waved the result through as a same-origin path. Not exploitable (it renders as a broken `<img>`), but the scrub had been silently defeated.</symptom>
  <root_cause>`toPublicPath()` was written for one caller — a Filament FileUpload, which only ever hands back a clean disk path — so "not http(s), not absolute" was treated as proof of a disk path. Widening it to guard every image field meant it began receiving arbitrary typed input, and the assumption stopped holding. Ordering made it worse: promotion has to run *before* the scrub (a disk path has no scheme and would be blanked), so the weakened value was the one the allowlist saw.</root_cause>
  <prevention_rule>A helper that turns user input into a same-origin path must reject anything carrying a URL scheme (`^[a-zA-Z][a-zA-Z0-9+.-]*:`) rather than assume a disk path by elimination — and when a sanitiser runs *after* such a promotion, the test suite must include a hostile-scheme case for each promoted key, not only the happy path.</prevention_rule>
</bug>

## 2026-08-14T10:07:51+0600

<bug>
 <category>Business_Logic</category>
 <symptom>A doctor at one chamber could be shown a *different person's* diagnoses and prescriptions while prescribing — a relative who shared the patient's household phone and had the same common name.</symptom>
 <root_cause>`CrossTenantClinicalHistoryService::findMatchingPatients()` treated normalized phone + normalized name as identity. Household phones are the norm here (the booking wizard has a household picker precisely because several `patients` rows share one number), so relatives matched each other.</root_cause>
 <prevention_rule>Identity across chambers needs a discriminator that separates household members. Phone + name is not identity: require age agreement (or NID) and fail closed when it is unknown. Any future matching rule must be able to tell a father from a son on one phone. Pinned by `CrossTenantClinicalShareTest::test_two_relatives_on_one_phone_with_the_same_name_do_not_link`.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Every patient who existed before the consent checkbox shipped was sharing their clinical history with other chambers without ever having been asked.</symptom>
 <root_cause>The column was added with `->default(true)`, which backfills existing rows. The checkbox that collects the real answer only affects bookings made afterwards.</root_cause>
 <prevention_rule>A column that records consent must never be added with a permissive default — add it nullable or false and let the answer arrive from the person. If a default is unavoidable, ship the corrective backfill in the same task.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Visit records loaded from another chamber are handed to Consult Screen with their media paths blanked, merged into the same collection as the chamber's own records. Saving one would have written those blanks back and destroyed the real voice-note and photo paths in the chamber that owns them.</symptom>
 <root_cause>`fetchSharedVisits()` mutates live `VisitRecord` models for display instead of returning a read-only projection, and nothing marked them as foreign.</root_cause>
 <prevention_rule>Never hand out a mutated Eloquent model as a read-only view. Mark it (`markAsForeignChamberRecord()`) so the model itself refuses to save, rather than trusting every future caller to notice which half of a merged collection it is holding.</prevention_rule>
</bug>

## 2026-08-14T22:39:27+0600

<bug>
 <category>Business_Logic</category>
 <symptom>Changing a doctor’s modules or offer ticks before they paid left the partner’s pending commission on the old package — e.g. Maestro ৳15,000 still showing after the sale was cut to website-only ৳3,000.</symptom>
 <root_cause>`Commission::firstOrCreate` created the pending setup row once and never updated `base_amount` / `commission_amount` on later re-prices. Monthly pending rows had the same freeze.</root_cause>
 <prevention_rule>When Super Admin re-prices a tenant, recalculate every **pending** commission row from the new due amounts. Never rewrite **owed** or **paid** rows.</prevention_rule>
</bug>

## 2026-08-14T22:39:16+0600

<bug>
 <category>Business_Logic</category>
 <symptom>Changing a doctor's modules or a launch offer before they paid left the partner's pending commission on the old amount — Super Admin would later confirm payment against a quote that no longer matched the ledger.</symptom>
 <root_cause>`Commission::firstOrCreate` wrote the pending setup row once and never updated `base_amount` / `commission_amount` when `applyPricingToTenant` re-snapshotted due amounts. Monthly pending rows had the same gap.</root_cause>
 <prevention_rule>When due amounts change, recalculate every **pending** setup/monthly commission for that tenant. Never rewrite **owed** or **paid** rows.</prevention_rule>
</bug>

## 2026-08-14T23:45:02+0600

<bug>
 <category>UI/UX</category>
 <symptom>On a phone, Super Admin Restore and Delete sat beside Confirm paid as equally loud red buttons — a mis-tap could open a wipe instead of recording a payment.</symptom>
 <root_cause>Header actions were a flat list; Restore used Filament danger colour with no extra grouping, matching Delete.</root_cause>
 <prevention_rule>Put Restore and Delete in a labelled **Dangerous** overflow. Keep Confirm paid / Top up / Download as standalone header actions.</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>Platform restore submitted as a live replace unless Super Admin remembered to tick dry-run; download/restore could be double-clicked with no loading state.</symptom>
 <root_cause>`DataBackup::mount()` defaulted `dry_run` to false and `mode` to replace. Buttons had no `wire:loading` target.</root_cause>
 <prevention_rule>Super Admin restore defaults to dry-run (missing value = true). Show REPLACE only for a live replace. Disable download/restore while Livewire is working.</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>Dashboard said SOLO in all-caps, overdue/SMS stats had no colour, and Client Health clinic names were dead text with no phone.</symptom>
 <root_cause>Recent tenants used `strtoupper($tenant->plan_tier)` instead of `Tenant::planTierLabel()`. Panel colours never registered `amber`/`sky`. Seller overview rendered names without `tenantEditUrl()` or `contact_phone`.</root_cause>
 <prevention_rule>Use `Tenant::planTierLabel()` everywhere Super Admin shows a plan. Register Filament colour keys before using them on stats. Client Health names must link to tenant edit and show phone when set.</prevention_rule>
</bug>

## 2026-08-15T00:23:45+0600

<bug>
 <category>UI/UX</category>
 <symptom>Both cards on Platform data backup rendered edge-to-edge: the upload field, restore mode select and submit button sat flush against the card border with no inset.</symptom>
 <root_cause>A UX pass replaced the `.backup-card-body { padding: 1.25rem; … }` rule with new `.backup-btn-row` / `.backup-restore-submit` rules, but both `<div class="backup-card-body">` wrappers stayed in the markup — so the class resolved to `padding: 0px`.</root_cause>
 <prevention_rule>Never delete a CSS rule from a page-scoped `<style>` block without grepping the same file for the class. `SuperAdminPanelUxTest::test_backup_card_body_keeps_its_padding_rule` fails if the rule goes missing while the class is still used.</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>Unchecking dry-run on the platform restore flipped the button text to “Upload and restore platform data” but the button stayed primary blue, so the single most destructive control in the product looked identical to the safe one. Measured background stayed `oklch(0.546 … 262.881)` (primary) while its own `--color-600` had already resolved to danger red; forcing a style recalc snapped it to red.</symptom>
 <root_cause>The colour was expressed only as a Filament colour class swapped on re-render. Livewire morphs the existing button in place, and the browser did not re-resolve the custom-property-driven background against the new class — the class was right, the paint was stale.</root_cause>
 <prevention_rule>Do not let a destructive-state cue depend on a class swapped by a Livewire morph. Give the element a `wire:key` that changes with the state so Livewire replaces it, and back the colour with a cue that cannot go stale — here a freshly rendered red callout plus a `wire:confirm` that names what will be wiped. Covered by `SuperAdminPanelUxTest::test_turning_dry_run_off_arms_the_destructive_restore_with_a_confirmation`.</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>On the Super Admin Tenants list the Edit and Download chamber backup actions sat off the right edge — at 1280 the table was 1488px inside an 896px container (Edit at x=1555), and at 375 it was 862px inside 343px. The operator's main screen needed a horizontal drag to open any tenant.</symptom>
 <root_cause>Eleven columns plus two fully labelled row buttons (a ~290px actions column), and the practice name set a ~290px min-content width. An earlier fix used `visibleFrom`, which is viewport-based — but the sidebar takes ~380px, so at a 1280 viewport every `lg`/`xl` column still rendered into a 896px container.</root_cause>
 <prevention_rule>Size a Filament table against the content container, not the viewport: `visibleFrom` cannot see the sidebar. Keep row actions in an `ActionGroup`, start secondary/finance columns `toggleable(isToggledHiddenByDefault: true)`, and `wrap()` any free-text column that would otherwise set the table's min-content width.</prevention_rule>
</bug>

## 2026-08-15T13:06:00+0600

<bug>
 <category>Business_Logic</category>
 <symptom>Pressing Start on a delayed sitting cleared the yellow “delayed” banner but Estimated Time still pretended the line began at the announced delay (e.g. 5:30), even when the doctor actually started at 5:20.</symptom>
 <root_cause>`scheduleSessionStart()` always added `delay_minutes` to the sitting start. Start set `status` → `active` (yellow off) but left `delay_minutes` at 30, so the ETA engine kept using sitting + delay instead of max(sitting, started_at).</root_cause>
 <prevention_rule>Queue clock lives in `LiveSession::effectiveStartTime()` and `scheduleSessionStart()` must delegate to it: delayed + not started → sitting + delay; started → max(sitting, started_at) + pause; never zero `delay_minutes` on Start.</prevention_rule>
</bug>

## 2026-08-15T13:40:39+0600

<bug>
 <category>Code</category>
 <symptom>Mark Late on a delayed sitting could be shortened (30 down to 15) if anything called `markDelay()` besides the Filament form, which would unsay the time patients were already told.</symptom>
 <root_cause>The “only a larger total” rule lived on the form options and a validation closure. `LiveSessionService::markDelay()` wrote whatever minutes it was given.</root_cause>
 <prevention_rule>`markDelay()` must refuse a new total that is not larger than the current `delay_minutes` when the sitting is already `delayed`. Form options are a convenience; the service is the lock.</prevention_rule>
</bug>

## 2026-08-15T13:55:38+0600

<bug>
 <category>Business_Logic</category>
 <symptom>Booking confirmation SMS gave serial, date, and ticket link but no come-around time, so patients still turned up at sitting start because nothing in their pocket said when to arrive.</symptom>
 <root_cause>`estimatedTimeForBooking()` returned null without a live session row, and `SmsService::confirmationBody()` never called a published guess.</root_cause>
 <prevention_rule>Live-queue booking SMS and the wizard confirm flash must include `PublishedComeAround` before Start; overflow stools say “After serial N”, not a clock. One segment enforced via `GsmText`.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>When all published seats were taken, staff could not add a walk-in at the desk even though the chamber would seat them on a stool after the list.</symptom>
 <root_cause>`createBookingForBookable()` always capped at `slot_cap` with no staff-only overflow path.</root_cause>
 <prevention_rule>Online and `availabilityFor()` use published cap only; staff walk-ins pass `allowOverflow: true` up to `walk_in_overflow_cap`, freezing `is_overflow` on the booking row.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>While the session was paused (doctor stepped out), Call next still advanced the queue.</symptom>
 <root_cause>Pause only updated status and slid ETA; `callNextPatient()` and `callSpecificPatient()` did not check `paused`.</root_cause>
 <prevention_rule>Queue advance entry points must refuse while `live_sessions.status === 'paused'`; UI copy must say tickets wait and Call next is blocked.</prevention_rule>
</bug>

## 2026-08-15T14:26:41+0600

<bug>
 <category>Code</category>
 <symptom>Clinic Departments and Blog posts are supposed to sit under the Filament **Website** sidebar group, but the shared trait redeclared Filament's inherited `$navigationGroup` with a different default (`'Website'` vs `null`).</symptom>
 <root_cause>`ClinicWebsiteResource` set `protected static $navigationGroup = 'Website'` instead of overriding `getNavigationGroup()`.</root_cause>
 <prevention_rule>Shared Filament resource traits must set navigation group/icon/sort with methods (`getNavigationGroup()`), never by redeclaring the parent's typed static properties.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>The ChamberQ sales homepage at `/` assembled prices, WhatsApp links, and partner referral codes inside Blade, reading `config()` and `session()` in every partial.</symptom>
 <root_cause>The central route was a closure `return view('marketing.home')` with no sanitised payload.</root_cause>
 <prevention_rule>Public marketing pages receive only a controller-prepared payload. WhatsApp numbers are digits-only; image paths must stay under `images/marketing/`; referral/discount suffixes are appended only when they match `[a-z0-9-]{1,50}`.</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>Unauthorized or missing URLs showed Laravel's grey Forbidden / Not Found stamp — no ChamberQ name and no way home.</symptom>
 <root_cause>`resources/views/errors/` did not exist, so abort(403)/abort(404) used vendor `errors::minimal`.</root_cause>
 <prevention_rule>Ship branded HTML error pages for 403, 404, 419, 429, 500, and 503. JSON clients must still receive JSON, not the HTML page.</prevention_rule>
</bug>

## 2026-08-15T14:46:39+0600 — production audit

<bug>
 <category>Code</category>
 <symptom>A signed-in ChamberQ patient whose stored phone did not parse as a BD mobile saw every booking of every chamber on the platform at `/me` — name, serial, date, doctor and ticket URL — and every diagnosis and medicine list at `/me/history`.</symptom>
 <root_cause>`PlatformPatientHistoryService::bookingsForAccount()` built its filter inside `->where(function ($q) { … })` and added constraints only when the phone list or patient-id list was non-empty. Laravel's `addNestedWhereQuery()` discards a nested closure that added no wheres, so with both lists empty the compiled query was `select * from bookings` — and every query in that service runs `withoutGlobalScopes()`, so the tenant scope was not there to catch it either.</root_cause>
 <prevention_rule>A query that has opted out of the tenant scope must state its own scope before it runs: compute the identifiers first and return empty when there are none. Never let "no filters to add" fall through to an unfiltered query — conditional `where` closures fail open by design.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Anyone could make the server issue an HTTP POST to an address of their choosing — cloud metadata (`169.254.169.254`), loopback services, private ranges — by booking a serial and registering that URL as their ticket's push endpoint.</symptom>
 <root_cause>`POST /api/queue/{booking}/push` is unauthenticated by design (the ticket UUID is the gate) and validated `endpoint` with Laravel's `url` rule, which accepts any scheme and any host. `MinishlinkWebPushSender` then POSTs to the stored value whenever the queue advances. `POST /api/staff/push` had the same rule.</root_cause>
 <prevention_rule>Any user-supplied URL the server will later fetch goes through `App\Support\PushEndpoint` (https, no userinfo, port 443, no private/reserved IP literal, no `localhost`/`.local`/`.internal`/single-label host) before it is stored. `url` validation is a format check, not a destination check.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Every request on the `web` group ran a `tenants` lookup, so a database fault turned a plain 404 into a 500 — and a caller-set `Referer` header could choose which practice any central page was rendered as.</symptom>
 <root_cause>`InitializeTenancyForTenantHosts` fell back to the referring page's first path segment on *every* route, not just Livewire's tenant-less `/livewire/update`, did not require the referrer to be on this host, and let the lookup throw.</root_cause>
 <prevention_rule>Header-derived tenancy is scoped to the one endpoint that has no other source (`livewire/*`) and to same-host referrers. Middleware on the global `web` group must not be able to escalate an error page into a server error — wrap the lookup and continue.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Department, blog and public-doctor HTML rendered unsanitised on the public clinic site after a disaster-recovery restore.</symptom>
 <root_cause>`body` / `bio` were cleaned by a model `saving` hook, but `DataImportService` restores with `DB::table()->upsert()`, which fires no model events. The three detail blades echoed the columns with a raw `{!! !!}`.</root_cause>
 <prevention_rule>Sanitise at the render boundary as well as the write boundary. Any `{!! !!}` of tenant-authored HTML calls `HtmlSanitizer::clean()` inline, the way `tenant/sections/rich_text.blade.php` already did — a guard that only exists on one write path is not a guarantee.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>`patient_otp_codes` grew by one row — a phone number and a bcrypt hash — for every login attempt ever made, and nothing deleted them.</symptom>
 <root_cause>`PatientOtpService::send()` marked previous codes consumed but never removed them, and no scheduled prune existed.</root_cause>
 <prevention_rule>A table written on every attempt of an unauthenticated flow needs its delete written at the same time as its insert. Prune the acting key's own spent rows (index-covered), not the whole table on a timer nobody wires up.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Signing out of the patient locker on a shared phone left the arriving session id valid and reusable.</symptom>
 <root_cause>`PatientAuthController::logout()` called `session()->forget()` + `regenerateToken()`, which rotates the CSRF token but not the session id.</root_cause>
 <prevention_rule>Logout calls `session()->invalidate()` then `regenerateToken()`. Forgetting keys is not ending a session.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>The `/me` locker, OTP login, and cross-chamber patient matching each ran a full table scan (plus a filesort on login) of tables that grow with the platform rather than with one chamber.</symptom>
 <root_cause>Every existing index was tenant-first (`patients (tenant_id, phone)`), but these queries run `withoutGlobalScopes()` and never supply `tenant_id`, so `tenant_id` being leftmost made the index unusable.</root_cause>
 <prevention_rule>When a column is queried both with and without the tenant, index it phone-first (`(patient_phone, tenant_id)`) so one key serves both. Adding a cross-tenant query means checking that an index exists whose leftmost column it actually supplies.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Debug blocks left in `bootstrap/app.php`, `MarketingController@home`, `LocaleController@switch`, `ClinicWebsiteResource`, and `marketing/home.blade.php` wrote JSON to the hardcoded path `/Users/chowdhuryjoy/ChamberQ/.cursor/debug-3ddb17.log` on every homepage render, every language switch, every Filament navigation build, and every thrown exception.</symptom>
 <root_cause>Agent diagnostic scaffolding was committed rather than removed, and none of it was gated by an environment flag.</root_cause>
 <prevention_rule>No absolute developer path may appear under `app/`, `bootstrap/`, `config/`, `routes/`, `database/` or `resources/` — `grep -rn "/Users/" ` over those directories must return nothing before a release. Diagnostics belong behind a config flag and in `Log`, never `file_put_contents` to a literal path.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>The test suite failed with "no such table: tenants" in one run and passed in the next, blaming whichever class happened to run first.</symptom>
 <root_cause>`InitializeTenancyForTenantHosts` on the global `web` group made previously DB-free HTTP tests touch the database, and `MarketingLandingPageTest` had no `RefreshDatabase`, so it depended on an earlier class having migrated.</root_cause>
 <prevention_rule>Any test class that issues a real HTTP request declares `RefreshDatabase`. Adding middleware to the global `web` group means re-checking which test classes now reach the database.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>`POST /api/offline/sync` answered differently for a `live_session_id` belonging to another chamber than for one that did not exist at all.</symptom>
 <root_cause>`exists:live_sessions,id` ignores the tenant global scope — the same trap `BookingService` already documents for `lab_tests`.</root_cause>
 <prevention_rule>Never use `exists:` / `unique:` on a tenant-scoped table. Validate the shape only, and resolve the id through the model so the scope decides.</prevention_rule>
</bug>

## 2026-08-15T21:12:17+0600

<bug>
 <category>Business_Logic</category>
 <symptom>After tapping Confirm, the patient's screen sat spinning on a booking that had already succeeded — up to ten seconds, and on every booking of a slow evening. Some patients tapped Confirm again.</symptom>
 <root_cause>`BookingService::createBookingForBookable()` called `SmsService::sendBookingConfirmation()` inline, after commit but still inside the patient's request, and `HttpSmsGateway` waits `config('sms.http.timeout')` (10s) for the aggregator.</root_cause>
 <prevention_rule>No outbound network call belongs in a patient-facing request once the thing the patient asked for is committed. Hand it to a job dispatched with `->afterResponse()` — never `dispatch()` alone, because this application runs no queue worker and a queued patient notice is a notice nobody sends.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>`AuthDebugProvider` and `SessionProbe` — kept deliberately by decisions.md 2026-08-05 to capture the cause of the owner's repeated sign-outs — could never be switched on in production, the only place the symptom occurs.</symptom>
 <root_cause>Both gated on `env('AUTH_DEBUG', false)` at the call site. A deployment runs `php artisan config:cache`, after which Laravel skips loading `.env` entirely and `env()` returns null outside config files.</root_cause>
 <prevention_rule>`env()` is read in `config/` and nowhere else; call sites use `config()`. Enforced by `SourceHygieneTest::test_env_is_only_read_from_config_files`, which skips Blade and CSS because `env(safe-area-inset-bottom)` is an unrelated CSS function these views legitimately use.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>A failing SMS gateway's raw reply was copied verbatim into `sms_messages.error` and the log — up to 500 characters, unread by anyone.</symptom>
 <root_cause>`HttpSmsGateway` interpolated `$response->body()` straight into the exception message. BD aggregators commonly echo the request back on an auth failure ("invalid api_key: …"), so the reply can carry the key that authenticates every message the clinic sends.</root_cause>
 <prevention_rule>Redact known secrets out of any third-party response before it is stored or logged, by matching the configured value rather than guessing field names. Keep the rest — a gateway failure is undiagnosable without the message it returned.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>`FiveQueueHonestyTest` passed all morning and failed with "the clinic is closed on the date you chose" every evening — 3 errors and 1 failure on any run after 20:00 local, on unmodified code.</symptom>
 <root_cause>The class books a 17:00–20:00 sitting *today* and never froze the clock, so `BookingService::sessionAlreadyEndedToday()` correctly refused the booking once the real time passed 20:00. The product was right; the test was time-dependent.</root_cause>
  <prevention_rule>Any test that books, calls or completes against a sitting with a wall-clock window freezes time in `setUp()` (`Carbon::setTestNow()`), and clears it in `tearDown()`. A suite that goes red every evening trains everyone to ignore it, which is when a real regression lands.</prevention_rule>
</bug>

## 2026-08-15T22:37:54+0600

<bug>
 <category>UI/UX</category>
 <symptom>Staff tapped **Switch to Bangla** in the desk user menu and the panel stayed English. Chamber language set to Bangla in Branding also never reached the desk. A click with no Referer dumped them onto the public homepage.</symptom>
 <root_cause>Filament prepends `SetUpPanel` (which runs `bootUsing`) before `StartSession` and tenancy init, so the locale callback there never saw a session or a tenant. Neither tenant admin panel ran `Localization`. `LocaleController` with no same-host Referer always sent people to the public home.</root_cause>
 <prevention_rule>Apply `Localization` on both tenant Filament panels after session and tenancy, not in `bootUsing`. Signed-in chamber staff hitting `/lang/{locale}` without a same-host Referer return to `/admin`; guests stay on the public site. Off-site Referer is still ignored. HTTP tests must hit the real switcher — `Livewire::test` plus `app()->setLocale('bn')` does not prove the desk follows chamber language.</prevention_rule>
</bug>

## 2026-08-15T23:46:24+0600

<bug>
 <category>UI/UX</category>
 <symptom>After Switch to Bangla started applying, most of the desk was still English — sidebar items, Live Queue stats (Waiting / Seen), Daily Roster columns, dashboard widgets.</symptom>
 <root_cause>Filament prints `$navigationLabel` and `$title` as-is, so locale never reached those signs. `lang/bn.json` had Operations / Website / Settings and a few queue buttons, not the daily chrome.</root_cause>
 <prevention_rule>Staff chrome labels must go through `TranslatesStaffChrome` / `TranslatesResourceChrome` (or `__()` at the call site). `StaffDeskBanglaTest` must stay red if a Live Queue / roster / dashboard / sidebar string has no `lang/bn.json` entry.</prevention_rule>
</bug>

## 2026-08-16T00:01:07+0600

<bug>
 <category>UI/UX</category>
 <symptom>After the 23:46 pass, sidebar names, page titles, and buttons (Call next, Finish / End Session) were Bangla. Staff already know those English control names and found the translated knobs harder, not easier.</symptom>
 <root_cause>That pass treated painted signs (nav, titles, buttons, Filament Save/Search) as the same as reading copy. Staff want the recipe card in Bangla and the stove knobs in English.</root_cause>
 <prevention_rule>Do not wrap sidebar labels, page titles, or action buttons in `__()`. Reading copy (stats, badges, empty states, sitting notes, column headers, field labels, notifications) still goes through `__()`. `EnglishFilamentLoader` remaps `filament*` namespaces to English when locale is `bn`. `BanglaStaffPanelTest` must see **Finish / End Session** and must not see `সেশন শেষ করুন` on the Bangla desk. The 23:46 traits are deleted — do not restore them.</prevention_rule>
</bug>

## 2026-08-16T13:08:30+0600

<bug>
 <category>Code</category>
 <symptom>Opening Daily Roster **New Walk-In** and typing a phone crashed with `Target class [App\Filament\TenantAdmin\Pages\PatientService] does not exist`.</symptom>
 <root_cause>`DailyRoster` lives in `App\Filament\TenantAdmin\Pages` and called `app(PatientService::class)` without `use App\Services\PatientService`, so PHP looked for a Filament page of that name.</root_cause>
 <prevention_rule>Any Filament page under `Pages\` that calls a `*Service` class must import `App\Services\…`. A missing import is a 500, not a “class not used” warning. Pinned by `DailyRosterWalkInPickerTest`.</prevention_rule>
</bug>

## 2026-08-16T22:00:09+0600

<bug>
  <category>Business_Logic</category>
  <symptom>A referring doctor could be paid twice for the same commissions. Two staff on the Referral ledger — or one double-click on **Mark selected as paid** — each posted a full `referral_payout` expense to the cashbook and each marked the rows paid, so the money went out twice and the second write overwrote `payout_cash_entry_id`, leaving the first expense orphaned and unreconcilable. A failure partway through the loop was the mirror image: the expense was posted for the full total while some rows stayed `pending`, to be paid again on the next payout.</symptom>
  <root_cause>`ReferralCommissionService::markPaid()` filtered the passed collection in memory with `isPending()` and then updated each row unconditionally, with no transaction and no lock. That collection is a Filament bulk selection — a snapshot of what the table showed when it rendered, held separately per request — so both requests saw "pending" even after one had paid.</root_cause>
  <prevention_rule>A money-moving bulk action never trusts the selection it is handed. Re-read the rows inside the transaction under `lockForUpdate()`, filter on the status column there, and keep the status condition on the write itself.</prevention_rule>
</bug>

<bug>
  <category>Business_Logic</category>
  <symptom>A salary could be recorded in the cashbook twice. On a double-submit the second request slipped past the "already recorded?" read, wrote a second `salary` expense — which succeeded — and only then hit the `(tenant_id, employee_id, pay_period)` unique index on the payroll row. The payroll ledger stayed correct with one row while the cashbook kept an expense nothing explained, and the desk saw a raw database error and retried, which could add more.</symptom>
  <root_cause>`HrPayrollService::recordSalaryPayment()` wrote the `ChamberCashEntry` before the `PayrollPayment` and outside any transaction, so the guarded write was the second one and the unguarded money write had already committed. The pre-read was treated as the guard, but two submissions can both pass it.</root_cause>
  <prevention_rule>When a unique index is the real guard, the write it guards and every money row written alongside it belong in the same transaction — and the constraint violation is translated into the same message the pre-check gives, so staff do not retry into more orphans.</prevention_rule>
</bug>

## 2026-08-16T23:47:15+0600

<bug>
 <category>UI/UX</category>
 <symptom>Clinic nav hover slid the label away and left a blank; extra pages in the bar 404’d on path-tenant URLs like /mups/.</symptom>
 <root_cause>`.fx-btn-track` was one line tall with overflow:hidden, so the duplicate hover label was clipped inside the track before the slide. Extra WebPage hrefs used the raw slug `/centres` instead of `tenant_safe_href()`, which does not add `/{tenant}`.</root_cause>
 <prevention_rule>Dual-label hover clips on the outer button only — never on the sliding track. Clinic nav (and any page-builder path CTA) must use `tenant_safe_href()` / `clinic_nav_items()`, not a bare `/slug`.</prevention_rule>
</bug>

## 2026-08-17T11:32:16+0600

<bug>
 <category>UI/UX</category>
 <symptom>On the sitting hours form, the live minutes-each helper showed a heading **Minutes each hint** and the sentence “At this window that is about 10 minutes each.” Staff could not tell what the number meant.</symptom>
 <root_cause>`Placeholder::make('minutes_each_hint')->label('')` — an empty string is falsy, so Filament falls back to a humanised field name. The English string was also broken.</root_cause>
 <prevention_rule>Give instructional Placeholders a real `->label()`, or `->hiddenLabel()`. Never `->label('')`. Assert the rendered HTML does not contain the raw field name.</prevention_rule>
</bug>

## 2026-08-17T15:30:27+0600

<bug>
 <category>Code</category>
 <symptom>`startSession()` threw `ModelNotFoundException` when two staff pressed Start together: the unique index caught the second insert, but SQLite had already aborted the outer transaction, so the follow-up `firstOrFail()` found nothing.</symptom>
 <root_cause>The unique-constraint catch lived inside one SQLite transaction. A failed INSERT poisons that transaction; a later SELECT in the same txn cannot see the winner's row.</root_cause>
 <prevention_rule>On SQLite, put the INSERT that may trip a unique index in a nested savepoint (`DB::transaction` inside the outer txn), then re-read. Do not `firstOrFail()` after a unique miss in the same aborted transaction.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Offline replay of Patient arrived or Skip recorded `ok: true` even when the booking was in the wrong status, so the desk thought the tap landed.</symptom>
 <root_cause>`patientArrived()` / `skipPatient()` no-op'd wrong statuses in the service, but `OfflineSyncService::applyQueueEvent()` still marked the replay successful.</root_cause>
 <prevention_rule>An offline queue replay that cannot apply must throw `OfflineQueueConflictException`. A silent service no-op is not an acknowledgement.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>`sendVisitToIntervention()` could leave an orphan procedure booking with no `related_booking_id` if the follow-up `update()` failed after create.</symptom>
 <root_cause>Create and the link/`procedure_status` writes were separate, not one transaction.</root_cause>
 <prevention_rule>A handoff that creates a booking and then stamps the link on it belongs in one `DB::transaction` — including **Send to counseling**.</prevention_rule>
</bug>

<bug>
 <category>CRO</category>
 <symptom>A patient could self-book onto the intervention (OT) list from the public website by posting that sitting's `bookable_id`.</symptom>
 <root_cause>`BookingController::create()` loaded every `ScheduleSession`; `store()` trusted `bookable_id` with no kind check.</root_cause>
 <prevention_rule>Public wizard lists and `POST /api/bookings` must both require `ScheduleSession::isPubliclyBookable()`. A view filter is not a control.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>Two bookings on the same day could receive the same voucher number. The desk then opened the wrong patient's row once that number was shown on the ticket.</symptom>
 <root_cause>`nextNumberForDate()` committed (releasing `lockForUpdate`) before the caller wrote `voucher_number`. The index was not unique.</root_cause>
 <prevention_rule>Hold the lock across read and write (`assignIfNeeded()`), and enforce uniqueness with `bookings_voucher_unique`. Never return a number that is not yet reserved.</prevention_rule>
</bug>

<bug>
 <category>UI/UX</category>
 <symptom>On the existing single-room waiting-room TV, the calling tile uses `background: var(--color-primary); color: #fff`. A pale brand colour is white-on-white; a dark navy hides the serial.</symptom>
 <root_cause>`theme_color` is an unconstrained ColorPicker (`BrandingSettings`), yet it was asked to carry calling-state contrast.</root_cause>
 <prevention_rule>Calling-state colour on a waiting-room TV must be a fixed high-contrast pair (white numerals; inverted off-white tile). Confine `theme_color` to chrome such as a header rule. Do not use brand colour for digits that must be read at distance.</prevention_rule>
</bug>

## 2026-08-17T15:48:27+0600

<bug>
 <category>Code</category>
 <symptom>Every public clinic page (MUPS homepage, departments, extra WebPages) returned a 500: `Call to undefined function clinic_nav_items()`.</symptom>
 <root_cause>The header/footer were switched to `clinic_nav_items()` and `architecture.md` described the helper, but it was never added to `app/helpers.php`.</root_cause>
 <prevention_rule>A helper named in a Blade layout must exist in `app/helpers.php` (autoloaded) before the layout ships. A documented function that is not in the repo is a production crash, not a TODO.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Daily Roster **Collect fee** with **Cash + online** stored the whole fee as one method (or rejected with no split fields). Cashbook already collected ৳300 cash + ৳500 bKash correctly; the roster path did not.</symptom>
 <root_cause>`ChamberCashService::recordPatientIncome()` already accepted `cashTaka` / `onlineTaka` / `onlineMethod`, but the Daily Roster form never showed those fields and never passed them in.</root_cause>
 <prevention_rule>When two screens write the same money service, the desk form on both must collect the same split. A service that accepts mixed payment is not mixed until the Filament action passes the split.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>A clinic homepage heading containing `&lt;img onerror=…&gt;` could execute as HTML after the word-split animation ran. Blade had escaped the heading; JS put it back as markup.</symptom>
 <root_cause>`public/js/clinic-clireo.js` `splitWords()` read `textContent` then assigned `innerHTML` with the raw words, unescaped.</root_cause>
 <prevention_rule>If you must write `innerHTML`, escape every untrusted fragment first. Prefer `textContent` / `createElement`. Escaping in Blade does not survive a later `innerHTML` rebuild.</prevention_rule>
</bug>

## 2026-08-17T18:41:08+0600

<bug>
 <category>Code</category>
 <symptom>Anyone who could guess `/api/screen/chamber/3` received the live patient's booking UUID, which opens the full unauthenticated ticket (name, serial, voucher, labs).</symptom>
 <root_cause>The waiting-room JSON included `current_booking_id` so the TV could de-dupe voice announce. The UUID is the ticket's only secret.</root_cause>
 <prevention_rule>Public screen APIs must never return a booking id. De-dupe on `announce_key` (`session|serial|called_at`) instead.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>A hostile backup ZIP with `../../.env` entries could write outside `storage/app/backup-import/` on restore.</symptom>
 <root_cause>`ZipArchive::extractTo()` was called with no entry-name check.</root_cause>
 <prevention_rule>Before extract, reject any ZIP name that is absolute or contains `..`.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Staff tapped **Move intervention** on a called patient; Call next kept returning her and the TV still showed her as now serving.</symptom>
 <root_cause>Move kept `called`/`in_chamber` and left `live_sessions.current_booking_id` pointing at the moved row. It also skipped the staff overflow cap.</root_cause>
 <prevention_rule>Moving a procedure always resets status to waiting, clears any live-session pointer at that booking, and refuses when the target sitting is at `staffCap`.</prevention_rule>
</bug>

<bug>
 <category>CRO</category>
 <symptom>Super Admin set the booking window to 30 or 90 days; Confirm still used a hardcoded 60. Horizon 30 still accepted day 45; horizon 90 showed day 75 then 422.</symptom>
 <root_cause>`BookingController::store` and availability used `now()->addDays(60)` while open-dates used `PlatformSetting`.</root_cause>
 <prevention_rule>Every public booking date gate — open-dates, availability, and POST — must use `PlatformSetting::onlineBookingMaxDate()`.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>A doctor could save another chamber's `visit-audio/…` path on a visit record, then a later save would delete that file.</symptom>
 <root_cause>Report photos checked `isOwnedReportPhotoPath()`; voice and prescription photos did not. `deleteIfExists()` deleted any path.</root_cause>
 <prevention_rule>Every media path written or deleted must pass `isOwnedVoicePath` / `isOwnedPhotoPath` / `isOwnedReportPhotoPath`.</prevention_rule>
</bug>

## 2026-08-17T19:15:41+0600

<bug>
  <category>Code</category>
  <symptom>**Send to counseling** appeared on every finished procedure, including on days the doctor has no counseling sitting. Staff tapped it, confirmed the modal, and only then got a red "No counseling sitting is scheduled for this doctor on that date" — a dead end reached after the confirmation step.</symptom>
  <root_cause>`canSendToCounseling()` checked module, status, sitting kind, procedure-done and duplicate-counseling, but never that a counseling sitting actually existed; `resolveCounselingSession()` was only reached inside `sendToCounseling()`, where it throws. The intervention actions hide the same gap because their modal renders a placeholder instead of the picker — this action is a plain confirm, so there was nowhere to say why.</root_cause>
  <prevention_rule>An action's `visible()` must assert every precondition its handler will throw on. When a handler resolves a dependency that may be absent, expose that lookup as a nullable finder the visibility check can call, rather than discovering it by catching.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>A voucher-assignment failure took the whole booking down with it: the patient had a serial committed, but saw a 500 and never got the confirmation SMS, because the throw landed between the commit and the notify block. Rebooking then hit the duplicate guard.</symptom>
  <root_cause>`VoucherService::assignIfNeeded()` throws a `RuntimeException` after three contended attempts, and `BookingService` called it unguarded after the booking transaction had already committed. A desk convenience was allowed to fail an operation that had already succeeded.</root_cause>
  <prevention_rule>Work that runs after a transaction commits may not throw into the caller. Anything post-commit that is not part of the booking itself gets caught and reported, so a committed serial cannot be reported to the patient as a failure.</prevention_rule>
</bug>

<bug>
  <category>Code</category>
  <symptom>`/api/bookings/availability` and `/api/bookings/open-dates` — both unauthenticated, both accepting up to 50 caller-supplied sitting ids — reported remaining capacity and open dates for **intervention** sittings, which patients may not book. The wizard and the POST were gated; these two were not.</symptom>
  <root_cause>The rule lived as an instance method (`isPubliclyBookable()`) applied at two of four entry points. The endpoints that resolve sittings by id had nothing to call it on before querying.</root_cause>
  <prevention_rule>A rule enforced at more than one entry point belongs in the query layer where those points converge — here `scopePubliclyBookable()` over a shared `PUBLICLY_BOOKABLE_KINDS` constant, so a new endpoint cannot silently opt out.</prevention_rule>
</bug>

<bug>
  <category>UI/UX</category>
  <symptom>Desk and TV strings shipped untranslated under translated headings: three procedure statuses and the Counseling room label on the staff desk, and **Next** / **Running late** on the new combined chamber TV — all with the Bangla tests passing.</symptom>
  <root_cause>Both Bangla tests scan hard-coded file lists. Labels defined in models and services (`Booking::procedureStatusOptions()`, `ScheduleSession::kindOptions()`, `StationsHandoffService` error toasts) were never scanned, and the patient-facing list named `screen.blade.php` but not the new `screen-chamber.blade.php`.</root_cause>
  <prevention_rule>When a feature adds a view or a class that emits `__()` strings, add it to the matching Bangla scan list in the same task. A green translation test only proves the files it was told about.</prevention_rule>
</bug>

## 2026-08-18T14:26:23+0600

<bug>
  <category>Business_Logic</category>
  <symptom>Branch picks on Staff & Roles never saved — every desk login stayed “all branches” after create/edit.</symptom>
  <root_cause>`chamber_ids` used `->dehydrated(false)` but Create/Edit read it from `mutateFormDataBeforeCreate/Save`, which only receives dehydrated fields — so `$chamberIds` was always `[]` and `syncChambers` detached the pivot.</root_cause>
  <prevention_rule>Non-column form fields that sync elsewhere must be read from `$this->form->getState()` in `beforeCreate` / `beforeSave`, not from mutate hooks — same pattern as Super Admin Create Tenant bootstrap emails.</prevention_rule>
</bug>

<bug>
  <category>Business_Logic</category>
  <symptom>Branch-locked lead desk could grant “all branches” or manage hospital-wide staff by leaving branches empty or intersecting to zero.</symptom>
  <root_cause>Empty pivot means all-clinic; `constrainChamberIdsForLeadHire` returned `[]` when the lead picked only out-of-scope branches; `leadMayManageStaff` treated unscoped staff as manageable.</root_cause>
  <prevention_rule>Lead hire must validate branch picks (`assertLeadHireChamberIds`), reject out-of-scope intersections, and never list or edit staff without overlapping chamber rows when the lead is branch-locked.</prevention_rule>
</bug>

## 2026-08-18T17:36:15+0600

<bug>
 <category>UI/UX</category>
 <symptom>The MUPS homepage hero showed white text on a blank white band — the surgery photo was in the HTML but never painted.</symptom>
 <root_cause>A QA form fill wrote “QA sweep” into `theme_color`. CSS `--brand` became that string, so `color-mix(..., var(--ink-deep))` inside `background-image` was invalid and the browser dropped the whole stack, including `url(/images/mups/mups-hero-surgery.jpg)`.</root_cause>
 <prevention_rule>Never emit `theme_color` into CSS unless it is `#rgb`/`#rrggbb` (`Tenant::cssThemeColor()`). Keep the hero photo on its own `background-image` (`--hero-photo`); put the overlay on `::after`. ColorPickers must regex-reject non-hex.</prevention_rule>
</bug>

## 2026-08-18T17:43:15+0600

<bug>
 <category>UI/UX</category>
 <symptom>The clinic homepage hero let a Dhaka patient book a Chittagong serial — one mixed session list, no city question, and picking a sitting skipped “Pick location” in the wizard.</symptom>
 <root_cause>The hero asked doctor/date/session only. Session labels were `Visit · Saturday…` with no chamber. Prefill locked the sitting’s chamber. Intervention sittings were also listed because the homepage loaded every schedule row.</root_cause>
 <prevention_rule>A multi-branch clinic hero must collect the centre first, list only `publiclyBookable()` sittings for that chamber, and drop a posted sitting that does not belong to the chosen chamber.</prevention_rule>
</bug>

## 2026-08-18T17:50:11+0600

<bug>
 <category>UI/UX</category>
 <symptom>The hero Date field looked like 18/08/2026 was already chosen, so patients skipped it or thought the clinic had picked today for them.</symptom>
 <root_cause>Browser `<input type="date">` paints today’s date in a muted colour when the value is blank, especially with `min` set to today.</root_cause>
 <prevention_rule>Patient-facing date fields that start empty must show an explicit empty prompt (e.g. Select date), never a native date input whose blank state looks filled.</prevention_rule>
</bug>

## 2026-08-18T18:24:32+0600

<bug>
 <category>Business_Logic</category>
 <symptom>Storing a patient's age as a whole-year number (42) made the file wrong every birthday — the software either left 42 forever or quietly added a year on the anniversary of the booking, never on the real birthday.</symptom>
 <root_cause>The public wizard asked for age in years and `PatientService` stored `age` + `age_recorded_at`, with `displayAge()` adding elapsed years. That number is not identity; a birth year is.</root_cause>
 <prevention_rule>Ask for year of birth (জন্মসাল) on booking and walk-in. Store `patients.year_of_birth`. Compute display age as this calendar year minus that year. Never store a ticking age as the source of truth. A leftover `age` POST from an old client may be converted once.</prevention_rule>
</bug>

## 2026-08-19T19:22:45+0600

<bug>
 <category>CRO</category>
 <symptom>Submitting from a clinic homepage showed MethodNotAllowedHttpException: method not supported for route `/` (GET, HEAD only).</symptom>
 <root_cause>The homepage catch-all is GET-only. The Book card correctly POSTs to `/book`, but a request that POSTs `/` (browser posting the current URL) never reached `prefill()`.</root_cause>
 <prevention_rule>Clinic `POST /` must share `BookingController::prefill()` with `POST /book`. Pin the hero `action` to `tenant_web_url('/book')`. Do not treat ChamberQ marketing `POST /` as a booking.</prevention_rule>
</bug>

## 2026-08-19T19:33:18+0600

<bug>
 <category>Code</category>
 <symptom>Opening Live Queue Control at `/{tenant}/admin/live-queue-control` showed MethodNotAllowedHttpException on route `/` (GET, HEAD, POST).</symptom>
 <root_cause>The patient service worker is scoped to `/{tenant}/`, which includes the Filament desk. Combined with `APP_URL=http://localhost` vs a tab on `127.0.0.1:8000`, Livewire/desk traffic could hit `/` with a method the homepage does not allow. The earlier `POST /` booking safety net made the error list POST as allowed without fixing the desk.</root_cause>
 <prevention_rule>Patient `sw.js` must not intercept `/admin` or `/livewire/`. Generated URLs on a request must use that request’s host and port (`ForceRequestRootUrl`). `prefill()` must ignore `X-Livewire`. Pin Live Queue HTML `data-update-uri` to `/livewire/update`.</prevention_rule>
</bug>

## 2026-08-19T20:05:04+0600

<bug>
 <category>Code</category>
 <symptom>After the Live Queue `/` Method Not Allowed fix, the red Ignition overlay still appeared on Live Queue in every browser that had opened a clinic homepage. Desk login worked; the queue board loaded underneath the overlay.</symptom>
 <root_cause>Skipping `/admin` in a *new* `sw.js` does not remove an already-installed worker. Path-tenant PWA scope is `/{tenant}/`, which includes the Filament desk. Several tenants (`mups`, `demo`, …) each left a worker on `127.0.0.1:8000`.</root_cause>
 <prevention_rule>Filament pages must unregister service workers on this origin (`drop-patient-service-workers`). `sw.js` must `registration.unregister()` on `/admin` or `/livewire/`, not only `return` from fetch. Pin both in `LiveQueueLivewireUriTest`.</prevention_rule>
</bug>

## 2026-08-19T21:41:39+0600

<bug>
 <category>Business_Logic</category>
 <symptom>Submit on Live Queue **New Walk-In** showed Method Not Allowed on route `/` after the sitting's published end time (e.g. Uttara Intervention 17:00–18:00, walk-in at 21:36).</symptom>
 <root_cause>`sessionAlreadyEndedToday()` treats a past `end_time` as "clinic closed". Desk walk-in did not pass `allowEndedToday`. The exception's `render()` called `back()` on the Livewire POST, which Laravel then rejected as the wrong method for `/`.</root_cause>
 <prevention_rule>Live Queue and Daily Roster walk-in must pass `allowEndedToday: true` (same as Book intervention). `BookingUnavailableException::render()` must not `back()` when `X-Livewire` is set — 422 JSON, and the action must catch and notify. Pin with a walk-in after `end_time` and a Livewire render test.</prevention_rule>
</bug>

## 2026-08-19T21:45:32+0600

<bug>
 <category>UI/UX</category>
 <symptom>Phone admin sidebar clipped the practice name through the middle of the letters and cut off later Operations items such as Follow-up reminders.</symptom>
 <root_cause>Filament’s logo row is a fixed 4rem with `overflow-x-clip` (which also clips vertically) and `leading-5` on a flex logo that will not wrap. The nav is a flex child without `min-height: 0`, so it cannot scroll inside `h-dvh`. Safe-area insets were unused because the viewport meta lacked `viewport-fit=cover`.</root_cause>
 <prevention_rule>Tenant admin sidebar header must wrap the brand name at `height: auto`; `.fi-sidebar-nav` must set `min-height: 0` and bottom safe-area padding; Filament head must include `viewport-fit=cover`. Pin in `TenantAdminShellTest`.</prevention_rule>
</bug>

## 2026-08-19T22:05:02+0600

<bug>
 <category>UI/UX</category>
 <symptom>On Live Queue on a phone, opening the menu left Session Actions and New Walk-In sitting on top of the drawer. The page title “LIVE QUEUE CONTROL” stacked over the hamburger and the clinic name.</symptom>
 <root_cause>The sticky content header is z-index 40 so it stays above the Rx patient strip. Filament’s sidebar/overlay are z-index 30. On a phone the drawer is a full-screen overlay, so the lower z-index lost. The no-topbar hamburger also stayed in document flow, sharing the same band.</root_cause>
 <prevention_rule>Below `lg`, the sidebar must be z-index 50 and the close overlay 45 — never below the content header’s 40. Do not lower the content header to fix the drawer. Pin in `TenantAdminShellTest`.</prevention_rule>
</bug>

## 2026-08-19T22:08:01+0600

<bug>
 <category>UI/UX</category>
 <symptom>On a phone, Live Queue’s title “LIVE QUEUE CONTROL” stacked on three lines under the hamburger while Session Actions and New Walk-In stayed on the same row and overlapped the title.</symptom>
 <root_cause>The content header is `flex-row` at every width. Two wide buttons left almost no room for an uppercase mono title, and `padding-inline` after a mobile `padding-left` cancelled the hamburger gutter.</root_cause>
 <prevention_rule>Below `lg`, `.fi-header.fi-content-shell-header` must be `flex-direction: column` with actions on a full-width second row, and that rule must come after the desktop `flex-row` block. Pin in `TenantAdminShellTest`.</prevention_rule>
</bug>

## 2026-08-19T22:12:45+0600

<bug>
 <category>UI/UX</category>
 <symptom>On Live Queue in dark mode, “Buzz this phone when a sitting needs you” sat on a pale card with near-white type — unreadable.</symptom>
 <root_cause>The card used an inline `background:rgb(250 250 250)` that always won, while the heading inherited Filament’s dark-mode light text. `text-muted` did not set a dark-mode colour either.</root_cause>
 <prevention_rule>Never pin Filament desk cards to a light fill with inline `background`. Paint `.staff-buzz-card` in `tenantAdmin/theme.css` with an explicit `color` and an `html.dark` surface. Pin in `LiveQueueControlPageTest`.</prevention_rule>
</bug>

## 2026-08-20T00:58:50+0600

<bug>
 <category>UI/UX</category>
 <symptom>Chamber desks on a phone got the stacked header and drawer-above-buttons fix; central ChamberQ Super Admin at `/admin` still used Filament’s default topbar and theme, so the same overlap would return there.</symptom>
 <root_cause>`SuperAdminPanelProvider` had the hamburger icon mapping but no `viteTheme`, `topbar(false)`, or `viewport-fit=cover`. Architecture claimed a `superAdmin/theme.css` that does not exist.</root_cause>
 <prevention_rule>Super Admin must register the same `tenantAdmin/theme.css`, `topbar(false)`, collapsible sidebar, and `viewport-fit=cover` as the chamber desk. Pin in `SuperAdminPanelUxTest`.</prevention_rule>
</bug>

## 2026-08-19T19:30:00+0000

<bug>
 <category>Code</category>
 <symptom>Branch-scoped doctors could stream visit voice/photos and print prescriptions for patients at another chamber in the same clinic; staff could tap cancellation/prescription SMS for out-of-scope bookings.</symptom>
 <root_cause>`VisitMediaController`, `PrescriptionController`, and `NotifySmsController` checked role + tenant but not `StaffDeskScope` on the related booking.</root_cause>
 <prevention_rule>Every HTTP path that reads clinical media, prints a prescription, or sends staff SMS must call `StaffDeskScope::assertCanAccessBooking()` after loading the booking — same rule as offline sync.</prevention_rule>
</bug>

<bug>
 <category>Code</category>
 <symptom>A forged Staff & Roles form could set `role=super_admin` even though the Filament select only offered owner/doctor/staff.</symptom>
 <root_cause>`role` and `tenant_id` were in `User::$fillable`; CreateRecord passed POST data straight into `User::create()`.</root_cause>
 <prevention_rule>Keep privileged columns off `$fillable`; whitelist tenant-panel roles in `TenantPanelUserRoles` and assign role via `forceFill` after create/edit.</prevention_rule>
</bug>

 <root_cause>Portal lookup used GET; prescription portal route trusted query-string phone; screen JSON endpoints had no shared secret beyond the numeric session/chamber id.</root_cause>
 <prevention_rule>Portal lookup must POST into session (`PortalSession`); prescription portal reads session phone only; outdoor-screen polls require `ScreenPollToken` from the rendered TV page.</prevention_rule>
</bug>

<bug>
 <category>Business_Logic</category>
 <symptom>Anyone who knew a patient's mobile could set or brute-force the optional portal prescription password without proving they held the SIM.</symptom>
 <root_cause>`PortalPrescriptionLock` set/unlock accepted password forms keyed only on a phone number the caller typed or pasted from the URL.</root_cause>
 <prevention_rule>Require a consumed SMS OTP (`PortalOtpService`) in session before any portal prescription password set or unlock.</prevention_rule>
</bug>

## 2026-08-20T13:20:10+0600

<bug>
 <category>Code</category>
 <symptom>Opening Book Serial (or any tenant admin page) showed Class "App\Filament\TenantAdmin\Pages\StaffDeskJobs" not found from Cashbook.php line 61.</symptom>
 <root_cause>Cashbook::canAccess() calls StaffDeskJobs::canCollectFee() without `use App\Support\StaffDeskJobs`. PHP looked in the Pages namespace. Filament still evaluates Cashbook access while building other pages, so one missing import crashed Book Serial.</root_cause>
 <prevention_rule>Any Filament page that calls StaffDeskJobs must import App\Support\StaffDeskJobs. Pin with Cashbook::canAccess() in ChamberCashTest — that call fatals if the import is missing.</prevention_rule>
</bug>

## 2026-08-21T00:13:30+0600

<bug>
 <category>UI/UX</category>
 <symptom>Medicine voucher rows (Calcimax, Flexactive Extra) had huge empty space above and below each name, like two lines filling half the A4 page.</symptom>
 <root_cause>`table.lines` was `flex: 1` inside a full-height A4 column. Browsers stretched every table row to share leftover page height.</root_cause>
 <prevention_rule>Never put `flex: 1` on a print table. Grow a spacer under the table. Pin `flex: 0 0 auto` and `lines-spacer` on the medicine voucher.</prevention_rule>
</bug>

## 2026-08-21T09:47:47+0600

<bug>
 <category>UI/UX</category>
 <symptom>After booking a serial, the confirmation box and WhatsApp said serial and date only. Patients still asked “what time?” even though SMS and the ticket already had come-around.</symptom>
 <root_cause>Book serial and ConfirmSerialNotifyAction used a fixed “Hello … serial … date” WhatsApp line. The modal never called PublishedComeAround.</root_cause>
 <prevention_rule>Staff-facing booking confirmation and Push WhatsApp must use the same come-around (or hours / after-serial) as SMS and the wizard. One copy helper, not a second short template.</prevention_rule>
</bug>

## 2026-08-21T09:50:13+0600

<bug>
 <category>UI/UX</category>
 <symptom>The one-room waiting-room TV showed a brand-blue serial on a dark card. From the back of the room the number was hard to read; a pale brand colour would also have made the calling state vanish.</symptom>
 <root_cause>The all-rooms TV was restyled with white numerals and an inverted calling tile, but `/screen/{session}` still used `theme_color` on the digits and the calling fill.</root_cause>
 <prevention_rule>Both waiting-room TVs use the same fixed contrast pair. Brand colour is chrome only (header bar). Pin `OutdoorScreenTodayTest::test_single_room_screen_uses_fixed_contrast_not_theme_colour_on_digits`.</prevention_rule>
</bug>

## 2026-08-21T09:59:00+0600

<bug>
 <category>CRO</category>
 <symptom>Booking SMS and WhatsApp carried a long `/bookings/{uuid}` ticket URL, so the message looked like spam and stole space from come-around in a one-credit SMS.</symptom>
 <root_cause>Outbound ticket URLs were built from the booking UUID. Prescriptions already had `/p/{token}`; tickets did not.</root_cause>
 <prevention_rule>Patient outbound ticket links (SMS, WhatsApp, Copy link, wizard confirm) must use `Booking::publicTicketUrl()` (`/t/{token}`), never paste the UUID into a third-party shortener. Pin `TicketShortLinkTest`.</prevention_rule>
</bug>
