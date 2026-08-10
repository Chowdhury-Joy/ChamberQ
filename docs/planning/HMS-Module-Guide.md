# HMS modules — plain-English guide

Prepared: 2026-08-10T02:46:55+0600 · Reordered by timeline: 2026-08-10  
Audience: owner / domain (not a code walkthrough).  
**Sequence below follows the 25-week build timeline** (not the competitor’s brochure numbering). Team: owner + 1 developer + domain/QA. Calendar weeks overlap on two streams — do not add the Est. delivery column into a longer total.

Market lens: **Bangladeshi clinics and diagnostic centres generally, with Chattogram (Chittagong) in mind** — not one named prospect from a competitor PDF.

---

## Quick scorecard (timeline order)

| When | Module | Real HMS need? | Mistake cost | Clash with logged decisions? | Est. delivery |
|---|---|---|---|---|---|
| Already shipped | SMS | Yes | Medium (cost + trust) | **None — already decided well** | **Done** (extend only as new events need notices) |
| Already shipped | Patient & Doctor Portal | Yes (you have it) | Medium (privacy) | **Watch:** no patient login accounts | **Mostly done** (small ties as HMS modules ship) |
| Weeks **1–2** | Setting & Security | Yes | **Very high** if weak | **Strong tension** with simple 3 roles + no-expiry sessions | ~2 weeks: authority, audit, live infra |
| Weeks **3–7** | OPD | Yes (your core) | High once money is added | Medium — cash vs “no gateway”; roles | ~5 weeks billing completion; queue already ~70% |
| Weeks **3–7** engine · **22–23** UI | Accounts | Yes if you take money | **Extremely high** | Medium — pay-at-chamber for now; not Super Admin commissions | Engine with OPD; screens ~2 weeks once ledger is proven |
| Weeks **8–16** | Diagnostics | Yes for labs/diag centres | **Very high** (money + clinical + commissions) | Medium — Bangla admin, roles, pay-at-chamber shape | ~9 weeks: setup+commissions 8–11, live ops 12–16 |
| Weeks **17–21** | Pharmacy | Yes if you sell drugs | **Very high** (stock + money + patient safety) | Medium — catalogue ≠ stock; Bangla desk | ~5 weeks, includes stock engine |
| Weeks **22–23** | Store | Yes if Pharmacy/IPD | High | Low | ~2 weeks; **release valve** — cut first if late |
| Weeks **24–25** | MIS | Yes for owners | Medium (wrong reports → bad decisions) | Low | ~1–2 weeks on top of live money data |
| Weeks **24–25** | Dashboard & Registration | Yes (every clinic) | Medium–High if IDs duplicate | Low — fits patient spine | ~2 weeks polish; identity spine already exists |
| After 25-week plan | IPD | Only if you have beds | **Extremely high** | Low directly; wrong for many buyers first | **Deferred** (~4–5 weeks after money+stock) |
| After IPD | Emergency | Only with ER | High (inherits IPD/billing) | Low | **Deferred** (~1 week on top of IPD) |
| After 25-week plan | HR | Nice-to-have | Low | Low (scope creep) | **Deferred** (~1–1.5 weeks) |

**Calendar note:** End-to-end for the **9 shipping modules** is still **~25 weeks**, not the sum of the Est. delivery column.

```text
Already done     SMS · Portal
W1–2             Setting & Security (+ queue worker, media backup)
W3–7             OPD cash + Money/Order spines (+ Accounts engine)
W8–16            Diagnostics (+ clinic referral commissions)
W17–21           Pharmacy (+ Stock engine)
W22–23           Store · Accounts UI          ← Store = release valve
W24–25           MIS · Dashboard & Registration
Later            IPD → Emergency · HR
```

---

## Already shipped (before week 1)

### SMS

**Detailed example**  
“Your serial is 14…”, “Doctor late 30 min”, “Report ready”, prescription link. Wrong host in SMS = patient lands on wrong clinic (already fixed that class of bug). Bangla in SMS can explode segment cost (GSM single-segment enforced).

**Need it?** Yes in BD / Chattogram. ChamberQ already has a stronger story (prepaid wallet, segment guard, per-doctor channels).

**Mistake cost:** Medium — money (credits) + trust (phishing-looking links). Clinical risk lower than lab results.

**Clash?** **None** — decisions already match: prepaid wallet, no free SMS, booking succeeds if SMS fails, English SMS bodies, short prescription links.

**Est. delivery:** Done; extend per new HMS events (report ready, due reminder, etc.).

---

### Patient & Doctor Portal

**Detailed example**  
Patient checks ticket / appointments by phone lookup (no login). Doctor uses admin Consult Screen / records. Competitor “portal” often means patient login + doctor app.

**Need it?** Yes for modern clinics. **ChamberQ largely has its version.**

**Mistake cost:** Medium–high on privacy (phone oracle was real — masked). Clinical notes leaking to staff violates patient-records promises.

**Clash?** Watch:  
- **No patient login accounts** (2026-07-27) — don’t “upgrade” portal into password accounts without a new decision  
- **Homepage lock** — portal/ticket OK; don’t restyle solo homepage while doing HMS  
- Staff must not get full clinical notes (existing privacy model)

**Est. delivery:** Mostly done; small wiring as HMS modules ship.

---

## Weeks 1–2

### Setting & Security

**Detailed example**  
Who may void a ৳5,000 bill? Who sees audit “Staff Rina voided invoice #882 at 4:02pm”? Access profiles for cashier vs lab tech vs admin.

**Need it?** **Yes before money moves.** Real HMS that skips audit regrets it when the first dispute hits.

**Mistake cost:** Very high if missing (no trail). Also high if overbuilt into a permission maze Solo doctors hate.

**Clash?** **Strongest tension in the list:**  

| Decision | Tension |
|---|---|
| Simple 3 roles — avoid hospital permission maze | HMS wants Access Profile + Approval Authority |
| Session never expires | Cashier PC stays open forever — worse once real money exists (owner already accepted; revisit for multi-staff) |
| No per-prescription doctor approval | Don’t invent approval queues for Rx; approval is for **money voids/discounts**, not clinical notes |
| English-only admin | Security / cash screens for Chattogram staff may need Bangla later |
| Patient homepage / CRO locks | Irrelevant if you don’t touch patient site |

**How to resolve without breaking decisions:** keep Solo on 3 simple roles; HMS profiles only behind Clinic/HMS feature flags; approval only on financial actions; don’t touch session expiry without unlock phrase.

**Est. delivery:** Weeks 1–2 (~2 weeks). Same window also closes live-product infra: real queue worker + clinical media off-server.

---

## Weeks 3–7

### OPD (outdoor / chamber)

**Detailed example**  
Patient books serial 14 online. Outdoor TV calls 14. Doctor consults, writes Rx. Today ChamberQ stops at “completed.” Full HMS OPD adds: consultation fee ৳800 → cash counter takes cash/bKash → receipt → day total must match drawer. Policy: “first visit ৳1000, follow-up ৳500 within 14 days.”

**Need it?** Yes for clinics and outdoor chambers. **ChamberQ already owns ~70% of the queue/clinical half.** The missing half is money.

**Mistake cost:** High once billing exists (voids, wrong fee policy, drawer ≠ system). Lower than Diagnostics/IPD because volume and complexity are smaller — good place to prove the ledger.

**Clash?** Medium:  
- **Pay-at-chamber / no pre-payment** — OPD cash counter **supports** that decision; online pay would violate it until unlocked  
- **Roles** — need who can take cash / void (today staff ≠ cashier)  
- **Session never expires** — unattended cash-counter PC stays logged in forever (already accepted trade-off; risk rises with HMS money)

**Est. delivery:** Weeks 3–7 (~5 weeks for billing completion).

---

### Accounts (engine starts here; screens later)

**Detailed example**  
Tuesday: OPD collected ৳42,000, Diagnostics ৳1,18,000, Pharmacy ৳35,000, one ৳2,000 refund. Accounts isn’t “another form” — it’s the **books**: every collect/refund is a balanced debit/credit. Friday owner asks “how much cash should be in the drawer?” and the system answers from postings, not a spreadsheet.

**Need it?** If the centre takes money seriously: **yes**. Many small clinics fake it with Excel until they can’t.

**Mistake cost:** Extremely high. Wrong ledger compounds for months; “Accounts module later” after private money tables = archaeology. Production rule: unrecoverable money outranks ugly UI.

**Clash?** Medium:  
- Must remain **pay-at-chamber** until the owner unlocks payments  
- **Separate** from Super Admin SaaS `BillingPayment` / marketer commissions  
- Needs **audit + approval** before voids — tension with “no hospital-grade permission maze” (extend carefully, don’t dump RBAC on Solo)

**Est. delivery:**  
- **Weeks 3–7** — double-entry **engine** (proved on OPD cash)  
- **Weeks 22–23** — Accounts **UI** (chart of accounts, journal, search, finalize/cancel) over a ledger that has already carried real postings for ~15 weeks

---

## Weeks 8–16

### Diagnostics (Diagnostic Department)

**Detailed example**  
An outside GP in Agrabad sends Rahim for CBC + lipid. Desk: pick Rahim → order both tests → mark “referred by Dr. Karim” → take ৳1,800 cash → print receipt. Lab: collect blood → mark received → enter results → print report / SMS “report ready.” End of month: owner pays Dr. Karim 15% on those tests. Wrong referral or wrong rate = Dr. Karim stops sending patients — in Chattogram lab markets that network *is* the business.

**Need it?** For a **diagnostic centre / clinic with labs: yes — this is usually the product they buy.** For solo chamber with no lab: no.

**Mistake cost:** Very high.  
- Wrong result / wrong patient sample = clinical harm  
- Wrong cash / due / refund = silent money drift  
- Wrong referral commission = lose the referral network (commercial death in BD labs)

**Clash?** Medium, not hard blockers:  
- **English-only admin** (2026-08-08) — Chattogram counter staff often need Bangla on order/cash screens  
- **Simple 3 roles** — needs cashier / lab tech capabilities  
- **Pay-at-chamber** — fine if cash stays at desk; **clashes if** someone adds online lab pre-pay without unlock  
- **Must not** reuse Super Admin marketer `CommissionService` (different product — clinic pays referring doctors)

**Est. delivery:** Weeks 8–16 (~9 weeks: setup + clinic referral commissions 8–11; live pathology/ops 12–16).

---

## Weeks 17–21

### Pharmacy

**Detailed example**  
Doctor prescribed Napa 500mg × 20. Pharmacist sells batch `A-2027-03` (expires Mar 2027) at MRP. Stock drops. Next week supplier GRN adds 50 strips. Month-end: physical count 47, system says 49 → someone stole/mis-sold 2. Without batch/expiry you can sell expired stock and not know.

**Need it?** If the centre **sells medicines**: yes (common next to busy OPD/diag counters in Chattogram). If doctors only prescribe and patient buys outside: **no** (catalogue-only is enough — what ChamberQ has today).

**Mistake cost:** Very high — money, stock theft, **patient safety (expiry)**.

**Clash?** Medium:  
- Medicine catalogue exists for Rx — **don’t confuse with stock**  
- Bangla for pharmacy staff vs English-only admin decision  
- Feature-flag so Solo never sees Pharmacy menus

**Est. delivery:** Weeks 17–21 (~5 weeks, including the stock engine).

---

## Weeks 22–23

### Store (inventory)

**Detailed example**  
Lab needs 10 reagent kits. Store issues them to Diagnostics. Pharmacy is retail to patients; Store is “gloves, syringes, reagents” to departments. Same stock brain, different counter.

**Need it?** Real hospitals/labs with Pharmacy or IPD: yes. Tiny outdoor chamber: often no. Mid-size Chattogram diag centres: **useful once Pharmacy exists**, not day-one.

**Mistake cost:** High (stock disagreement with Pharmacy forever if built twice). Lower urgency than Pharmacy sales for a first sale.

**Clash?** Low. Plan correctly treats it as second face / release valve.

**Est. delivery:** Weeks 22–23 (~2 weeks; **cut first** if Diagnostics or Pharmacy slipped).

---

### Accounts UI (same window as Store)

See **Accounts** under Weeks 3–7 for the full module write-up. In weeks 22–23 the developer builds the Filament screens over the already-running ledger while the owner finishes Store (or drops Store if the schedule is late).

---

## Weeks 24–25

### MIS

**Detailed example**  
Owner wants: “This week Diagnostics revenue, top referring doctors, pharmacy margin, OPD completion rate.” Today Operational Reports answer queue counts, not money.

**Need it?** Yes for retention of monthly SaaS — owners stay if the numbers feel trustworthy. Chattogram owners especially care about **referral doctor league tables** and daily cash.

**Mistake cost:** Medium. Wrong dashboard → bad business decisions; usually not irreversible like a bad ledger. Danger is MIS that lies because Accounts/stock underneath are wrong.

**Clash?** Low. Extends existing ops reports; don’t replace them with vanity charts.

**Est. delivery:** Weeks 24–25 (~1–2 weeks once money data exists).

---

### Dashboard & Registration

**Detailed example**  
Fatima arrives at a Chattogram diagnostic centre. Reception asks phone `01712…`. System finds her from last month’s MRI, or creates her once. She gets today’s registration slip. Her husband books for their child on the same phone — the system must not rename Fatima into the child’s name (ChamberQ already fixed that class of bug).

**Need it?** Yes. Every real HMS starts here. Without one patient identity, labs, bills, and commissions invent three different “Fatimas.”

**Mistake cost:** High. Duplicate patients → wrong history, double billing, wrong referring-doctor credit. Fixable but painful after months of data.

**Clash?** Low. Aligns with `PatientService`, masked public picker, staff full names. Solo vs Clinic tiers already gate complexity.

**Est. delivery:** Weeks 24–25 (unify once other modules exist; identity spine already ships). Built last as a **unified desk**, not because registration is unimportant — because the patient spine already works and the HMS faces need to exist before one dashboard can show them.

---

## After the 25-week plan (deferred)

### IPD (wards)

**Detailed example**  
Patient admitted Bed 12 for 3 days. Day 1: bed charge. Day 2: pharmacy issues antibiotics to ward. Day 3: CT billed to the admission. Discharge: one running bill that must match every bed-day, drug, and test. If the bed status says “empty” while the patient is still there, nursing and billing fight.

**Need it?** Only if you run **inpatient beds**. Most pure diagnostic centres and outdoor chambers: **no**. Some private hospitals in Chattogram: yes — but that is a different buyer.

**Mistake cost:** Extremely high. Running bill that won’t reconcile compounds for days; hardest money+stock+clinical mix. That’s why the plan defers it.

**Clash?** Little with logged UX. Clashes with **first-sale focus** for typical diag centres and **risk ranking** in production-phase rules (“unrecoverable outranks inconvenient”).

**Est. delivery:** Deferred (~4–5 weeks after money + stock spines exist).

---

### Emergency

**Detailed example**  
Accident at 11pm: quick register → treat → either send home with bill or admit to IPD. Often skips normal OPD queue.

**Need it?** Only with a real ER. Cosmetic “Emergency” menu without IPD is theatre. Uncommon for diagnostic-centre buyers.

**Mistake cost:** High (speed + billing + possible admit). Defers with IPD.

**Clash?** None material.

**Est. delivery:** Deferred (~1 week on top of IPD).

---

### HR

**Detailed example**  
Nurse attendance, leave, maybe salary sheet. Owner opens it once a month.

**Need it?** A full hospital HMS often includes it. **A diagnostic centre can run for years on Excel / a separate HR tool.** Lowest daily-touch module.

**Mistake cost:** Low for clinical/money. Worst cost is **building it instead of Diagnostics commissions**.

**Clash?** Soft clash with scope: not what most Chattogram diag buyers pay for first. Defer is correct.

**Est. delivery:** Deferred (~1–1.5 weeks when needed).

---

## Decision-clash summary (what actually matters)

**Hard “don’t do this” clashes (need owner unlock / new decision):**
1. Online patient pre-payment / gateways while building Accounts or Diagnostics billing — **until** the owner opens payments (BanglaQR note below)
2. Patient login accounts as “portal upgrade”
3. Shortening session expiry under the guise of HMS security
4. Restyling locked patient homepage for HMS
5. Treating Super Admin marketer commissions as clinic referral commissions

**Soft clashes (must design around, not abandon):**
1. Simple 3 roles → extend with HMS capabilities **flagged**, don’t dump hospital RBAC on Solo
2. English-only admin → Bangla for **desk** screens (cash/pharmacy/order entry), not full panel by default
3. “No queue worker” posture → HMS stock/period close needs a worker (weeks 1–2)
4. Staff prescription entry without approval → don’t add Rx approval; add **void/refund** approval only

**No meaningful clash (already aligned):**  
SMS, pay-at-chamber cash desk (until payments unlock), patient identity, feature flags Solo vs Clinic, production-grade care for money/clinical data.

---

## What a “real” HMS must have vs brochure padding

**Must-have for a typical BD / Chattogram diagnostic centre buyer:**  
Registration, Diagnostics (incl. **referral payouts**), OPD+cash, Accounts/ledger, Settings/Audit, SMS, basic MIS, Pharmacy if they sell drugs.

**Must-have only if you have beds:** IPD + Emergency.

**Often replaced or deferred:** HR, fancy Store (if Pharmacy stock is enough), heavy Doctor Portal apps.

**ChamberQ already largely owns:** SMS, patient portal pattern, OPD queue/clinical, patient identity.

---

## Explaining the estimated times

These are **calendar weeks on the 25-week plan**, not “one person coding alone in a vacuum.”

| When | Why that size | Plain meaning |
|---|---|---|
| Weeks 1–2 Settings/Audit | Looks “small” | Greenfield (no audit table today), but small surface — and it **unblocks** every money screen |
| Weeks 3–7 OPD billing | Looks long for “just cash” | You are building the **money + order spines**, not polishing queue. OPD is the cheap proving ground |
| Weeks 8–16 Diagnostics | Dominates the calendar | Biggest unknown domain + referral engine + pathology lifecycle + desk-speed UX. Highest overrun risk |
| Weeks 17–21 Pharmacy | Includes stock engine | Store after that is cheap because the engine already exists |
| Weeks 22–23 Accounts UI | Short | Engine already ran for ~15 weeks on real postings — screens are mostly CRUD |
| Weeks 22–23 Store | Short + optional | Second face on stock; **release valve** if Diagnostics overran |
| Weeks 24–25 MIS + Registration | Buffer + polish | Harden, UAT, unify desk once there is something to unify |
| Deferred IPD/HR/Emergency | Outside 25 weeks | Not “forever no” — **not in the 25-week budget** for a diag-centre-first product |

**Uncertainty:** Diagnostics has the widest estimate band (±2–3 weeks). Store is the release valve. SMS/Portal estimates assume extension only, not rebuild.

---

## Chance of mistake in code (where bugs will hide)

Ranked by how likely a careful team still ships a costly bug (not by timeline):

| Rank | Area | Why bugs are likely | Typical failure |
|---|---|---|---|
| 1 | **Money ledger** | Concurrent cashiers, voids, partial pays, day close | Drawer ≠ books; debits ≠ credits after a refund |
| 2 | **Clinic referral commissions** | Rate cards, merges, rearrange, due vs paid, multi-test orders | Under/over-pay referring doctors; trust collapse |
| 3 | **Stock batch/expiry** | GRN, sales, returns, two faces (Pharmacy/Store) | Negative stock; sell expired; Pharmacy ≠ Store qty |
| 4 | **Diagnostics sample→result** | Wrong patient / wrong sample / edit-after-print | Clinical safety + reprint disputes |
| 5 | **Tenant isolation on new tables** | Shared DB; HMS triples FKs into `tenants` | Cross-clinic data leak (catastrophic for SaaS) |
| 6 | **Feature flags** | Solo tenants accidentally see HMS menus | Support load + confused Solo doctors |
| 7 | **Roles / approval** | Over-simple or over-complex authz | Junior staff void large bills; or desk frozen waiting for admin |
| 8 | **IPD running bill** (if built early) | Bed + pharmacy + lab on one admission | Unreconcilable discharge bill |
| 9 | **SMS/portal extensions** | Host/tenant URL mistakes | Lower now — patterns already battle-tested |
| 10 | **HR** | Simple CRUD | Low product risk; easy to overbuild |

**Code-discipline that lowers chance:** one write path per spine (ledger post, stock move), invariant tests (debits=credits; qty=ledger sum), MySQL CI on every migration, `app:production-check` before release, tenant-scope tests on every new write.

---

## How much of this do Chattogram clinics & diagnostic centres actually need?

Speaking about the **general market** (outdoor chambers, polyclinics, stand-alone pathology/imaging centres in Chattogram) — not one PDF prospect. Table follows **build timeline**:

| When | Module | How often needed in that market | Notes |
|---|---|---|---|
| Done | SMS | **Almost always** | Patients expect texts; WhatsApp still huge alongside |
| Done | Portal / ticket | **Strong differentiator** | You already have this; competitors often weaker online |
| W1–2 | Settings / audit | **Required with money** | Multi-staff counters in Chattogram make audit more important than solo chamber |
| W3–7 | OPD + cash | **Almost always** for clinics with doctors | Queue software without fee collection still leaves Excel at the desk |
| W3–7 / W22–23 | Accounts / ledger | **Needed once volume rises** | Many start with notebook; switch when disputes appear |
| W8–16 | Diagnostics | **Core for diag centres**; optional for pure OPD chambers | Referral commissions are culturally central for labs |
| W17–21 | Pharmacy | **Common add-on**, not universal | Busy centres sell; many chambers prescribe-only |
| W22–23 | Store | **Medium** — after Pharmacy/lab reagents | Skip for tiny centres |
| W24–25 | MIS | **High for owners who pay monthly SaaS** | Especially referral rankings + daily cash |
| W24–25 | Registration (unified) | **Almost always** | Phone-first identity is how BD desks work (spine already exists) |
| Later | IPD | **Low for diag centres**; high for hospitals | Don’t lead with this for lab buyers |
| Later | Emergency | **Low** unless true ER | |
| Later | HR | **Low as HMS must-have** | Separate tools / Excel common |

**Rough “must ship to win a Chattogram diag-centre deal” bundle:**  
Registration + Diagnostics (with referral payouts) + cash collection + audit + SMS/report delivery + owner MIS.  
Pharmacy next if they retail. IPD/HR later or never for that segment.

---

## What else would I add (beyond the 13), and why?

Not more brochure modules — **gaps that BD/Chattogram ops actually hit**:

1. **Tender types designed for future BanglaQR (and bKash) without building the gateway yet**  
   Cash counter records `cash | bkash_manual | card | qr_pending…`. Ledger posts “money in” the same way regardless of tender. When BanglaQR is integrated later, you add a payment provider adapter — you do **not** redesign Accounts.  
   *Why:* Owner signalled BanglaQR may come. Logged decision still says **no online patient payment until explicitly unlocked** — so build the *shape* now, not the integration.  
   *When:* design into weeks 3–7 cash counter.

2. **Patient due / credit balance (উধার)**  
   Labs often let known patients take reports and pay later, or pay partial.  
   *Why:* Pure “paid in full at counter” is cleaner in code but fights real Chattogram desk behaviour; without dues, staff go back to notebooks.  
   *When:* with Diagnostics billing (weeks 12–16).

3. **Referring-doctor statements (printable / WhatsApp)**  
   Monthly “Dr. Karim: 42 patients, ৳X owed, ৳Y paid.”  
   *Why:* The commission engine without a statement staff can show the doctor is half a product.  
   *When:* weeks 8–11 with referral engine; polish in MIS weeks 24–25.

4. **Desk Bangla pack (order entry + cash + pharmacy only)**  
   *Why:* Already in plan; calling it out as a product add-on, not “translate all of Filament.”  
   *When:* decide before week 8; ship with desk screens as they land.

5. **Report-ready notice on existing SMS/WhatsApp channels**  
   *Why:* Tiny extension of what you own; high perceived value for diag centres.  
   *When:* with Diagnostics R2 (weeks 12–16).

6. **Multi-counter / shift close**  
   Morning cashier vs evening cashier; each closes their drawer.  
   *Why:* Busier Chattogram centres aren’t one person all day; day-close bugs are where money fights start.  
   *When:* with OPD cash / Accounts (weeks 3–7, harden later).

7. **Branch / collection-point awareness (later)**  
   Some chains take samples in one place and process in another.  
   *Why:* Not week 1; don’t paint yourself into a single-location stock/lab model forever.  
   *When:* after first diag-centre pilots.

**I would not add early:** full HR suite, patient password accounts, IPD, Emergency menu cosplay, or online gateway before the ledger is proven on cash.

---

## Future payment integration (BanglaQR) — how this fits decisions

**Logged today:** pay-at-chamber only; online pre-payment / gateways are later-stage and must not be built until the owner explicitly asks.

**Owner signal (2026-08-10):** BanglaQR (and similar) may come later.

**What to do in the 25-week build (compatible with both):**
- Keep collecting at the chamber desk (cash / manual bKash reference) as the live path.
- Model payments as **tender + receipt + ledger post**, not “gateway-shaped” tables that assume SSLCommerz/bKash checkout.
- When BanglaQR is unlocked: add provider + webhook/confirm flow that creates the **same** payment event the cashier creates today.
- Do **not** put QR checkout on the public booking wizard until a new `<decision>` replaces or narrows the pre-payment ban.
- Log that decision in `decisions.md` **when chosen**, before code — same as other §6 HMS locks.

Real-life analogy: the cash register drawer should accept “notes, bKash screenshot, or QR ping” as ways money arrived — the shop’s books don’t care which pocket it came from. Build the books first (weeks 3–7); plug BanglaQR into the drawer later.

---

## Project memory

- `decisions.md` — **not updated** here; BanglaQR is a future intent, not a decision yet. Pay-at-chamber still stands.
- `bug_history.md` — not required (no bug fixed).
- `architecture.md` / `architecture_history.md` / `sitemap.md` — not required (planning doc only; no code/routes changed).
