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
