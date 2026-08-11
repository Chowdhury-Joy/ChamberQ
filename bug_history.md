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
