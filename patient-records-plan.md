# Patient Records — Plan

Status: **All stages built** (2026-08-06). Stages 1–6 complete. See `architecture.md` and `decisions.md`.

---

## Why

SolDoc today handles booking, queue, reports and blocked dates. A competitor can copy all of that.

What it does not do is remember anyone. Every booking is a fresh stranger — a name and a phone number attached to one appointment. If the same person comes back next month, the system has no idea.

Patient records change that. After six months a doctor who has their patients' history in SolDoc cannot easily leave, because the history does not come with them. That is the part of this product that is hard to replace.

---

## Part 1 — Remember people, not phone numbers

### A phone number is a family, not a person

One number often serves a household — a father books for his wife, his children, his mother. So the system stores a record for each **person**, and several people can share one number.

Each person's record holds what stays true across visits: name, phone, age, sex, allergies, ongoing conditions, and medicines they already take.

### Age

Record a date of birth when the patient knows it. Many will not.

So also allow a plain age **together with the date it was recorded**. Storing just "45" rots — three years later the record still says 45. Storing "45, recorded August 2026" stays correct forever.

### Folding in the bookings you already have

Every existing booking gets matched to a person by phone number and name.

One catch: older bookings saved phone numbers inconsistently — some with a country code, some without. These have to be tidied into one format *before* matching, or the same person splits into two records.

After this runs, every patient has a real visit count and no notes at all. That is normal, and the screen has to say so honestly (see Part 2).

### Phone number format

Nobody types a country code. Input, display and storage are all plain `01829293323`, one format everywhere a person sees it.

The country code cannot disappear from the system completely — WhatsApp links require it, and the text-message gateway expects it. But that conversion happens invisibly at the moment a message is sent, and nowhere else. It must never appear in a form or in stored data.

### Booking asks who it is for

Once a known phone number is entered:

> **Who is this appointment for?**
> — Karim Uddin (you) · — Fatima Begum, 34 · — Rashed, 6 · — *Someone new*

A new number shows no list and behaves exactly as booking does today. First-time booking gets no slower.

This appears inline on the step where the phone number is entered, not as a separate step — the wizard shows "Step 3 of 5," and a step count that jumps mid-flow looks broken on the screen where people are already deciding whether to bother.

**Staff creating walk-ins need the same picker.** Walk-ins go through the same booking machinery from the queue screens. If the picker only exists on the public page, walk-ins quietly create duplicate people — the exact problem this part exists to prevent.

### A live bug this fixes

Right now, if a phone number already has a booking with that doctor on that date, the next booking is refused — **the name is never checked**.

So today a father cannot book two of his children for the same day. He gets an error implying the system thinks he is booking twice for himself.

The rule should block the same *person* twice on one day, not the same *phone*. That still catches the real case — someone tapping submit twice — without punishing families.

### Text messages name the patient

A family booking three people would otherwise get three near-identical texts. Each must say who it is for: *"Rashed — serial 12."*

### Someone will get it wrong

Names get typed differently on different days. Someone books for their mother under their own name. So staff need a plain way to **join two records into one** and to **move a visit to the right person**.

This is not optional polish. Anything that works out who someone is from a phone number needs a human able to correct it, and it is far cheaper to build now than after a year of tangled records.

### The patient lookup page

Entering a phone number shows everyone on that number. That is correct for a shared family phone — whoever holds the phone already has access.

---

## Part 2 — The consult screen

### It changes by itself

The system already tracks who has been called into the chamber. The waiting-room screen and the patient's own ticket page already update themselves from it.

So the doctor searches for nothing and taps nothing. The patient is called in, and their history is already on screen.

### Who runs the queue

**One party per practice, not both.** Each client is set to:

- **Staff-run** — the default, and what nearly every practice will use. Staff have the call button. The doctor's consult screen simply follows along as patients are called in.
- **Doctor-run** — for doctors who ask for it. The doctor has the call button; staff see no queue controls.

The account owner can flip this at any time. So when staff are off on a Saturday, the doctor switches to doctor-run for the evening and switches back after. One party at a time, but never a chamber left stuck.

### Roles

Queue management belongs to **doctor or staff**. The account owner role comes off it.

**A trap to avoid:** a solo practice might be set up with only an owner login. If that happens and the owner cannot run a queue, the doctor cannot run their own chamber. So setting up a new client must create a doctor login for the doctor, not just an owner login. Build that as a check at signup rather than discovering it on a Tuesday evening.

### Two people pressing at once

The booking side of the system already protects against two patients grabbing the same slot at the same moment. The queue side has no such protection anywhere.

Without it, two simultaneous presses can both take the same next patient, and one quietly overwrites the other. The patient's number appears on the waiting-room screen, then vanishes, and they are dropped from the queue with nobody knowing — after they saw their number come up.

Making the queue one-party-at-a-time reduces this a lot, but does not remove it: a clinic with two staff on two screens can still collide. The protection still needs adding.

### What is on the screen

**Always showing**

- Name, age, sex
- *"4th visit · last seen 3 weeks ago"* — tells the doctor instantly whether this is a stranger or an ongoing story
- **The last visit's diagnosis and advice** — the most valuable thing here. Without it, the consult opens with *"what did I give you?"* and *"the white tablet."*
- **Warnings** — allergies, ongoing conditions, current medicines. These must stand out. This is the one place where missing something causes harm rather than wasting time.
- Whether a follow-up was asked for, and whether they came back on time

**One tap away**

- The full list of past visits
- Past prescriptions, viewable and printable again
- Voice notes and photos

**Never shown**

- Serial number, chamber, appointment time — the doctor is standing in the room
- Payment — cash at the desk, nothing to do with the consult
- Any block of text long enough that it must be read rather than glanced at

**Adapts to the plan.** A solo doctor's screen hides "which doctor saw them" — it would be the same name on every line forever — and hides lab orders. A clinic shows both. The booking form already works this way.

### The three states

**First time here**

> First visit — no history

Plain and calm. This is normal, not a failure, and must not look like one.

**Been before, nothing written**

> 3 previous visits · no notes recorded

The most important of the three. Telling a doctor that a patient of two years is a stranger would be a lie — and this is what every record looks like on day one, straight after the old bookings are folded in.

It also does quiet work: the doctor sees the gap they left, on a patient sitting in front of them.

**Been before, notes exist**

The history, as above.

---

## Part 3 — Recording the visit

### The design is shaped by the fact that people forget

A doctor seeing forty patients at five minutes each will not type. There is no room. If a note costs thirty seconds it gets skipped around patient three and never picked back up. Then the screen says "no notes recorded" forever, the doctor stops looking, and the whole thing is dead within a month.

So this cannot depend on anyone being disciplined. It has to be almost free.

### Who records what

**Staff record the person's details** — age, sex, allergies, ongoing conditions, current medicines, fixing a misspelled name, photographing a report the patient brought in. Staff are with the patient at the desk before the consult and can simply ask.

**Only the doctor can record the visit** — the diagnosis, the prescription, the advice. Staff were not in the room. No process fixes that.

Staff own the person's record. The doctor owns what happened today. That shrinks the doctor's share to the smallest possible thing.

### Four ways to record a visit

The doctor uses whichever suits the moment. A busy evening might be nothing but taps; a complicated patient gets a voice note. The point is that no visit has to be blank.

**1. Diagnosis — two taps**

A curated list (see below). Fast, and the only part that produces countable data.

**2. Prescription — written in the app, printed for the patient**

Medicines with dose, frequency and duration; advice; when to come back.

Medicine names work like the condition list — the doctor's own commonly-used medicines float to the top. Most chamber doctors reach for the same forty or so, so no national drug database is needed to start.

Printing reuses what the patient ticket already does — the browser's own print dialogue with a layout built for paper. That gives "save as PDF" for free.

On the printed page: doctor's name, qualifications, registration number, chamber address and phone; patient's name, age and date at the top. Leave space for a hand signature — a printed name alone is not usually accepted.

**It reprints.** A patient who loses their prescription gets the same one again from their history. This alone will make doctors like the product.

**3. Voice note**

The doctor speaks for ten or twenty seconds after the patient leaves. It saves, and plays back in the history. No accuracy risk, because it is just their own voice. For a doctor who will not type, this is the fastest capture there is.

**Voice to text** sits on top of it. The hard part is not Bangla and not English — it is that doctors speak both in one sentence: *"patient er gastric problem, omeprazole দিলাম, follow up two weeks."* Speech systems expect one language at a time and handle that switching badly.

So: **the recording is always kept.** The transcript is a convenience on top, always editable, and never the only copy. When it is wrong, the doctor's actual voice is still there.

A transcript never sets the coded diagnosis on its own. That stays a deliberate tap.

**4. Photo of a paper prescription**

For visits written on paper. It is kept as a picture the doctor can look at — nothing more.

**No handwriting recognition.** Doctors' handwriting is bad, recognition of it is unreliable, and a misread drug name or dose is dangerous in a way a misread booking note is not. The system never tries to read the paper.

The real answer to bad handwriting is to stop producing it — which is what the in-app prescription does.

**Also recorded, both plans:** tests advised, and reports seen. Plain text, no price list, no catalogue. This is what makes a solo doctor's history read properly: *asked for a blood test in March, saw the report in April, changed the medicine.*

### Where recording happens

On the **Mark Completed** button, already pressed for every patient.

**Never compulsory.** Force it and you get a screen full of "ok" and "same," and the records become worthless.

### Catching what is missed

Never interrupt during a consult. At the end of the session, one line: *"4 patients today without notes"* — tap to fill them in while the evening is fresh. Two minutes for all of them.

And accept that some will never be written. Four visits with two notes is far better than nothing, as long as the blanks are never dressed up as "no history."

### Who may read notes

- **Doctors on Consult Screen** in the chamber that created the notes, and — when the patient left **Share with other ChamberQ doctors** on — treating doctors at other ChamberQ chambers for the same phone + name.
- **Never the seller / Super Admin patient browser.** Nothing about individual patient records appears in the central panel (research stays counts-only).
- **Never the patient-facing pages** for the full clinical file (ticket stays non-clinical; portal prescription access is a separate, phone-gated exception).
- **Never voice notes or prescription photos** across chambers.

---

## Part 4 — The condition list

### Why free text will not work

Across a few hundred doctors, one condition gets recorded as *gastric*, *gastritis*, *acidity*, *acid peptic disease*, *APD*, the Bangla spelling, and a dozen variants of each. Nothing can be counted. You would have a pile of text needing a human to sort it, growing every day.

So behind the doctor's familiar words, the system stores a **standard code**. The doctor still taps "gastric" — that is what they call it — but underneath it is the same entry every time, and another doctor's "acidity" points at the same one.

**This is the decision that cannot be deferred.** Retrofitting it means paying someone to read years of free text and sort it by hand.

### Shape of the list

- Around **200 to 300 entries** covering what actually walks into a Bangladeshi chamber. The full international classification runs to tens of thousands — unusable at speed and it would bury the doctor.
- Each entry holds one code, one proper name, and the other things people call it — English, Bangla, and common shorthand.
- The doctor's own most recent and most frequent conditions sit at the top. Within two weeks, ten entries cover most of an evening.
- Target: **two taps, under three seconds.** Anything slower gets abandoned.

### Free text stays allowed

Marked as uncoded. It will not appear in research counts, and that is fine.

What matters is that the uncoded pile becomes the roadmap: read it every few months, and the ten things doctors keep typing get added as proper entries. The list grows from real use rather than guesswork.

---

## Part 5 — The seller's overview

Your central panel already covers money — collected, owed, net, recent signups. What is missing is the thing that actually predicts your income: **whether clients are still using it.**

Payment tells you someone left last month. Usage tells you three weeks earlier.

### Who has gone quiet

A list, worst first:

- **Days since they last ran a session.** Ten days is a warning; three weeks is nearly gone.
- **Bookings this week against their own normal.** Relative, not a fixed number — twenty a week is a healthy solo doctor and a dying clinic. What matters is that *they* are down by half.
- **Sessions scheduled but never started.** The doctor set up their timings and then ran the day on paper anyway. The clearest sign the product did not stick.

Something you would work from on a Sunday morning: five names to call, not a chart.

### Did new clients ever go live

For every recent signup, how far they got:

Account made → chambers added → schedule set → website live → **first booking** → **first live session run**

Where they stop tells you what to fix. If everyone stalls at the same step, the gap is in your onboarding and you see it across all of them at once.

The first live session is the real finish line. Everything before it is setup.

### Who is out of text-message credit

You already store a credit balance per client. Nobody watches it.

At zero, booking confirmations quietly stop. The doctor gets no error — patients just stop receiving texts, and eventually someone reports the system as broken. It is not; it is empty.

A support problem and a sale at the same time, since you sell the credit packs.

### Money

Add **who is overdue, and for how long** — a list of names, not a total.

### The line that must not move

Everything here is **counts, never contents.** How many bookings, not who booked. Whether a session ran, not who was in it. Nothing about diagnoses, prescriptions, notes or photos appears in the central panel.

None of these numbers need a patient's name to be useful.

**One thing to watch:** at some point you will want a "log in as this doctor" button for support. Almost every product like this ends up with one, and it is the back door through this whole boundary.

If you add it, make it deliberate: the doctor has to allow it, it expires, and every use is written down. Easier to decide now than to discover the button already exists.

---

## Part 6 — Research data

Disease patterns across hundreds of chambers is real data that almost nobody in Bangladesh has. It is a legitimate asset, with three conditions.

### It only works because of the coded list

Part 4 is what makes this possible. Without codes there is nothing to count.

### Small numbers still identify people

"How many patients have gastritis" across every clinic is safe. But narrow it — one solo doctor, one small town, one uncommon condition, one month — and a count of one **is** a person. Anyone locally who knows that practice could work out who.

Standard fix, and cheap: never show a group smaller than about ten, and do not allow filters to cut thin enough to get there. Build it in at the start and it never needs thinking about again.

### It is not your data, so get it agreed

The doctor collected this inside a clinical relationship and carries a professional duty to those patients. Using it for research is a different purpose from running their bookings — reasonable, but not something to assume.

Say it plainly in the agreement doctors accept at signup: aggregate and anonymous only, never individual records, never anything identifying.

Doctors who understand what they are agreeing to will mostly be fine with it, and some will find it interesting. Doctors who discover it later will not be, and they talk to each other.

---

## Order of work

**Part 1 → Part 2 → Part 3.** Parts 4, 5 and 6 attach where noted.

Each stage is useful on its own:

- **After Part 1** — staff can look someone up by phone and see every visit; returning patients get their name filled in when booking; the family booking bug is gone.
- **After Part 2** — the doctor gets a screen that follows the queue by itself, already showing real visit counts from the old bookings.
- **After Part 3** — visits start being recorded, and the screen gets more useful every week.

Part 4 must be designed **before** Part 3 is built, because the diagnosis field depends on it.

Part 5 is independent and can be built at any time.

Part 6 follows once Part 4 has been running long enough to have data.

---

## Deliberately not building

- **A national drug database.** The doctor's own frequently-used medicines cover the need. Revisit only if it becomes a real limitation.
- **Handwriting recognition.** Unreliable on doctors' writing, and dangerous when wrong.
- **Anything patient-facing built from notes.** Patients see their ticket and their bookings. Not diagnoses, not prescriptions, not notes.
- **Online payment.** Unchanged — pay at the chamber.

---

## Open decision

**Should the "who is this appointment for?" question go in from the start, or should the system match on phone-plus-name first and add the picker later?**

Recommendation: put it in from the start. It adds a step to booking, which affects how many people finish it — but without it, family members get quietly merged into one person until someone notices, and the bug where a father cannot book two children is still shipping. Fixing it later means untangling records by hand.

This is the one item that touches the booking flow, so it is the owner's call.

---

## Appendix A — Prescription rules in Bangladesh

Checked against news reporting and council material rather than written from memory. Sources at the end of this section. **This is research, not legal advice** — have a practising doctor confirm before the first prescription prints.

### The finding that matters

In January 2017 the High Court directed the Directorate General of Health Services and the Bangladesh Medical and Dental Council to circulate instructions that doctors write prescriptions clearly. The accompanying rule asked why doctors should not be required either to write **in capital letters, legibly** — or to **give patients printed prescriptions**.

The council then issued a circular requiring capital letters or legible writing. Legal notices have since been served on doctors who ignored it, with contempt proceedings threatened.

**Printing is one of the two routes the court itself named.** So the in-app prescription is not merely convenient — it puts a doctor on the right side of a directive that is being enforced, and that they are otherwise at risk of breaching every day with a pen.

That is a sales line, not just a feature: *printed prescriptions, compliant by default.*

### What to put on the printed page

**Confirmed by the directive**
- Legible print (this is automatic once it is typed)
- Generic names — the court rule covered mentioning generic names, and the council's 2020 telemedicine guidance puts drug names in capitals with the generic name beneath

**Standard practice, not confirmed as statutory** — treat as required until a doctor says otherwise
- Doctor's name, qualifications, and council registration number
- Chamber name, address, phone
- Patient's name, age, sex, and the date
- Space for a hand signature — a printed name alone is not generally accepted

### How this shapes the build

- **Drug name in capitals, generic name underneath.** Build the medicine entries this way from the start rather than retrofitting.
- **Registration number belongs on the doctor's profile**, not typed each time.
- **Leave signature space in the print layout.** Optionally allow an uploaded signature image, but the paper copy should still be signable by hand.
- **Never print something incomplete.** If the registration number is missing from the profile, warn before printing rather than producing an invalid document.

### Sources

- [HC directs govt to ask physicians for writing prescription clearly — New Age](https://www.newagebd.net/article/6606/index.php)
- [Doctors must write their prescriptions clearly: HC — The Daily Star](https://www.thedailystar.net/frontpage/doctors-must-write-their-prescriptions-clearly-hc-1343149)
- [Instruct doctors to write prescriptions in clear handwriting — bdnews24](https://bdnews24.com/bangladesh/instruct-doctors-to-write-prescriptions-in-clear-handwriting-hc-tells-government)
- [Legal notice served to 2 doctors over prescription writing — The Daily Star](https://www.thedailystar.net/country/news/prescription-writing-violating-directive-of-high-court-legal-notice-served-two-dinajpur-doctors-1633336)
- [Illegible prescriptions continue despite High Court directive — Dhaka Tribune](https://www.dhakatribune.com/bangladesh/nation/179662/illegible-prescriptions-continue-despite-high)
- [BM&DC registration verification](https://verify.bmdc.org.bd/)

---

## Appendix B — Research clause for the signup agreement

Draft wording. **Not legal advice** — have it reviewed before it becomes binding.

> **Anonymous health statistics**
>
> We may produce anonymous statistics from the clinical information recorded in this system — for example, how many patients across all clinics were diagnosed with a particular condition in a given month.
>
> These statistics are counts only. They never include a patient's name, phone number, address, or any other detail that could identify them, and they never include an individual patient's records. We do not publish figures for any group small enough that an individual could be recognised.
>
> We do not sell individual patient records to third parties. Within ChamberQ, when a patient leaves sharing switched on at booking (the default), other ChamberQ doctors who later see that same patient may view their clinical notes, vitals and prescriptions — not voice recordings or prescription photos — so care can continue across chambers. Your practice staff and doctors still hold day-to-day control of records created in your chamber. Our platform staff and marketers cannot browse individual patient records, prescriptions or clinical notes through the system.
>
> You may opt out of contributing to these statistics at any time, without any change to your subscription or service.

### Notes on the wording

**The opt-out is deliberate.** It costs you very little — few will use it — and it converts the clause from something a doctor might resent into something they chose. A doctor who finds out later that they could not refuse is the one who tells other doctors.

**The "platform staff cannot browse records" line is a promise you must keep.** It rules out a casual "log in as this doctor" support button. Cross-chamber clinical share is only for treating ChamberQ doctors when the patient left sharing on — not for marketers or Super Admin browsing. If you ever build a support impersonation button, this wording has to change first.

**Say it at signup, in plain Bangla as well as English.** A clause nobody read is worth little if it is ever challenged, and doctors talk to each other more than they read agreements.
