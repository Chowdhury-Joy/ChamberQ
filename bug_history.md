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
