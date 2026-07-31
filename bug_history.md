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
