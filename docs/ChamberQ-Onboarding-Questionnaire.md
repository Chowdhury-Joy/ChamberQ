# ChamberQ setup questionnaire

Send this **after they say yes**, before Super Admin creates the account.

Think of it like opening a new shop: you cannot print the visiting card until you know the shop name, the address, the opening hours, and who sits at the counter. Same here. Patients cannot book until sittings exist. Staff cannot log in until we have emails.

**Must-have (Part A)** is enough to go live with booking.  
**Website (Part B)** can follow a day later if they are busy.  
**Queue / prescription (Part C)** only if those modules are in the deal.

---

## How to send it (use this, not the long chat list)

**Live Google Form (this Google account):**  
Send this link to the doctor (WhatsApp): https://docs.google.com/forms/d/e/1FAIpQLSdMi6Mt7xZrnBz9JBF6TTjKS4Y5SOSB8TsZhKzO6-cgvl_GDQ/viewform  
Edit: https://docs.google.com/forms/d/1onIMvjngiBHIaYX7q0MR_32code9II16Mv2C82mBcQI/edit  
Question list backup: https://docs.google.com/document/d/1CxBYhwMqjwuQr_OQFbxcLYE6-r1HxNrIU4-EakP_JI8/edit  

Responses go to a Google Sheet from the form’s Responses tab (View in Sheets). Anyone with the link can fill it — staff may fill for the doctor. Do not require Google sign-in.

---

Asking 16 questions in chat is like asking someone to fill a bank account form inside Messenger. They send a voice note, skip the sitting times, and you chase for three days.

Do this instead:

1. Build a **Google Form** once (fields below). Turn on **Collect email addresses** if you want a paper trail. Every question is **English, with Bangla in brackets**.
2. After they say yes, send the **short cover** + the form link. Not the 16-question paste.
3. When the Sheet row arrives, type Part A into Super Admin → Create Tenant, then Chambers + Schedule Sessions.
4. If they ignore the link: “Voice note the sitting times and send one photo — I will type the rest.” Do not re-send the long list.

**Do not** put this form on the public ChamberQ homepage. That page stays WhatsApp-only so random people cannot self-signup. This link is something **you** send to one doctor.

PDF attachments fail on a phone. A Google Form they already know from school and clinic surveys works.

### Short WhatsApp cover (Bangla)

```
সেটআপ শুরু করতে এই ফর্মটা খুলুন (ফোনে ৫–৭ মিনিট)। সিটিং সময় আর একটা ছবি থাকলেই বুকিং চালু করা যায়।

👉 https://docs.google.com/forms/d/e/1FAIpQLSdMi6Mt7xZrnBz9JBF6TTjKS4Y5SOSB8TsZhKzO6-cgvl_GDQ/viewform

আটকে গেলে ভয়েস নোটে সিটিং সময় বলে দিন + ডাক্তারের ছবি পাঠান।
```

### Short WhatsApp cover (English)

```
Open this form to start setup (5–7 minutes on your phone). Sitting times + one photo is enough to turn booking on.

👉 https://docs.google.com/forms/d/e/1FAIpQLSdMi6Mt7xZrnBz9JBF6TTjKS4Y5SOSB8TsZhKzO6-cgvl_GDQ/viewform

Stuck? Voice-note the sitting times and send the doctor’s photo.
```

### Exact wording (paste into Google Form)

Every title is English with Bangla in brackets. Same for answer choices.

Form title: `Chamber setup — ChamberQ (চেম্বার সেটআপ)`

Form description: `Sitting times plus one photo of the doctor is enough to turn online booking on. Website copy can wait. Reception staff can fill this. (সিটিং সময় আর ডাক্তারের একটা ছবি থাকলেই অনলাইন বুকিং চালু করা যায়। ওয়েবসাইটের লেখা পরেও চলবে। রিসেপশন স্টাফ পূরণ করতে পারেন।)`

Settings: Limit to 1 response = **off**. File upload = **on**. Link responses to a Sheet.

**Section: You and the doctor (আপনি ও ডাক্তার)**

1. What name will patients see for the chamber? (রোগী চেম্বারের কোন নাম দেখবে?) — short, required. Hint: visiting card, e.g. Dr. Karim’s Chamber
2. Doctor’s full name (ডাক্তারের পুরো নাম) — short, required
3. Degrees (ডিগ্রি) — short, required. Hint: `MBBS, FCPS (Medicine)`
4. BM&DC registration number (বিএমডিসি রেজিস্ট্রেশন নম্বর) — short, optional
5. What type of practice? (কোন ধরনের প্র্যাকটিস?) — multiple choice, required: `General (জেনারেল)` / `Gynae (গাইনি)` / `Dental (দাঁত)` / `Child (শিশু)` / `Heart (হার্ট)` / `Skin (চর্ম)` / `Other (অন্য)`
6. Usual visit fee in taka? (সাধারণ ভিজিট ফি কত টাকা?) — short, required. Hint: numbers only, e.g. `800`
7. Any different follow-up or other fees? (ফলো-আপ বা অন্য ফি আলাদা কি?) — paragraph, optional
8. Phone number patients should call (রোগী যে নম্বরে ফোন করবে) — short, required. Hint: `01XXXXXXXXX`
9. WhatsApp number (হোয়াটসঅ্যাপ নম্বর) — short, required
10. Doctor login email (ডাক্তার লগইনের ইমেইল) — short, required
11. Preferred page link? (পেজের লিংক কেমন চান?) — short, optional. Hint: `drkarim` → chamberq.com/drkarim
12. Patient page language? (রোগীর পেজ কোন ভাষায়?) — multiple choice, required: `Bangla (বাংলা)` / `English (ইংরেজি)`
13. Who will run the waiting-room queue? (ওয়েটিং রুমের লাইন কে চালাবে?) — multiple choice, required: `Reception staff (রিসেপশন স্টাফ)` / `The doctor (ডাক্তার নিজে)`
14. Which parts do you want? (কোন কোন অংশ চান?) — checkboxes, required: website+booking / waiting-room TV / prescription (Bangla in brackets on the form)
15. Send SMS after a booking? (বুকিংয়ের পর রোগীকে SMS যাবে?) — multiple choice, required: `Yes, I want SMS (হ্যাঁ, SMS চাই)` / `No, skip SMS (না, লাগবে না)`

**Section: Where is the chamber (চেম্বার কোথায়)**

16. Name of this chamber (এই চেম্বারের নাম) — short, required
17. Full address (পুরো ঠিকানা) — paragraph, required
18. Google Maps link (গুগল ম্যাপ লিংক) — short, required
19. Any other chambers? (আর কোন চেম্বার আছে?) — paragraph, optional. Solo max 5 locations

**Section: Sitting days — most important (কোন দিন বসেন — সবচেয়ে জরুরি)**

20. Write sitting times — one sitting per line (সিটিং সময় লিখুন — এক লাইনে একটা) — paragraph, required. Hint: Saturday morning and Saturday evening = two lines (cinema showtimes). Example: `Saturday | Evening | 5:00 | 8:00 | 20`. Friday off if closed (`শুক্রবার বন্ধ`).

21. After online serials fill, how many extra walk-ins can the desk take? (অনলাইন সিরিয়াল শেষ হলে ডেস্ক আর কয়জন ওয়াক-ইন নিতে পারে?) — short, optional. Hint: `0` if none

**Section: Staff (স্টাফ)**

22. Will anyone at the counter log in? (কাউন্টারের কেউ লগইন করবে?) — paragraph, optional. Hint: name + Gmail

**Section: Photos (ছবি)**

23. Will you send photos on WhatsApp, or here? (ছবি হোয়াটসঅ্যাপে পাঠাবেন, নাকি এখানে?) — optional. Face photo of the doctor is required.

**Section: Website copy (can wait) (ওয়েবসাইটের লেখা — পরেও চলবে)**

24. One-line introduction (এক লাইনে পরিচয়) — short, optional
25. About the doctor — 2 to 4 lines (ডাক্তার সম্পর্কে ২–৪ লাইন) — paragraph, optional
26. Which problems do you see? (কোন কোন সমস্যা দেখেন?) — paragraph, optional
27. Three questions patients often ask (রোগীরা যা জিজ্ঞেস করে — ৩টা) — paragraph, optional

Responses: **link the form to a Google Sheet.** Each doctor is one row you can copy from.

The long numbered paste below is only a fallback if they refuse to open a link (some older doctors). Prefer the form.

---

## WhatsApp paste (Bangla) — send this first

```
ChamberQ সেটআপের জন্য কয়েকটা তথ্য লাগবে। নম্বর ধরে রিপ্লাই করুন। ব্যস্ত থাকলে শুধু ১–৮ পাঠালেই বুকিং চালু করা যায়।

১) চেম্বার/প্র্যাকটিস নাম (রোগী যা দেখবে):
২) ডাক্তারের পুরো নাম + ডিগ্রি (যেমন MBBS, FCPS):
৩) BM&DC রেজিস্ট্রেশন নম্বর (থাকলে):
৪) বিশেষত্ব: জেনারেল / গাইনি / দাঁত / শিশু / হার্ট / চর্ম / অন্য:
৫) কনসালটেশন ফি ৳:   ফলো-আপ আলাদা হলে সেটাও:
৬) রোগীর ফোন + হোয়াটসঅ্যাপ নম্বর:
৭) ডাক্তার লগইন ইমেইল (জিমেইল চলবে):
৮) চেম্বার ঠিকানা + গুগল ম্যাপ লিংক (ম্যাপে শেয়ার চাপুন, লিংক পেস্ট করুন):

৯) সিটিং টেবিল — প্রতি লাইনে একটা:
   দিন | সকাল/বিকাল/সন্ধ্যা | শুরু | শেষ | কয়জন সিরিয়াল
   উদাহরণ: শনি | সন্ধ্যা | ৫:০০ | ৮:০০ | ২০
   (একাধিক চেম্বার থাকলে কোনটা কোন ঠিকানায় লিখুন। সোলোতে সর্বোচ্চ ৫টা লোকেশন।)

১০) অনলাইন সিরিয়াল শেষ হলে ডেস্ক আর কয়জন ওয়াক-ইন নিতে পারে? (না থাকলে ০)
১১) লাইন কে চালাবে: স্টাফ / ডাক্তার নিজে?
১২) রোগী পেজ: ইংরেজি / বাংলা?
১৩) স্টাফ থাকলে: নাম + ইমেইল (কাউন্টারের জন্য)
১৪) চাই: ওয়েবসাইট+বুকিং / লাইভ কিউ টিভি / প্রেসক্রিপশন — কোনগুলো?
১৫) বুকিং কনফার্মেশন এসএমএস চান? (প্রিপেইড ক্রেডিট; খালি থাকলে বুকিং তবু হয়)
১৬) পেজের লিংক কেমন চান? chamberq.com/______  (ছোট ইংরেজি নাম, যেমন drkarim)

ফটো (হোয়াটসঅ্যাপে পাঠালেই হয়):
- ডাক্তারের পোর্ট্রেট
- লোগো (থাকলে)
- প্রেসক্রিপশন প্যাডের ছবি (প্রেসক্রিপশন নিলে)

ওয়েবসাইট টেক্সট পরেও চলবে: ২–৩ লাইন পরিচিতি, যেসব রোগ দেখেন, ৩টা FAQ।
```

---

## WhatsApp paste (English)

```
To set up ChamberQ I need a few facts. Reply under each number. If you are busy, 1–8 is enough to turn booking on.

1) Practice name patients should see:
2) Doctor full name + degrees (e.g. MBBS, FCPS):
3) BM&DC number (if you have it):
4) Type: general / gynae / dental / child / heart / skin / other:
5) Visit fee ৳:    Follow-up different? :
6) Patient phone + WhatsApp:
7) Doctor login email (Gmail is fine):
8) Chamber address + Google Maps link (open Maps → Share → paste):

9) Sitting table — one line each:
   Day | Morning/Afternoon/Evening | Start | End | How many serials
   Example: Saturday | Evening | 5:00pm | 8:00pm | 20
   (More than one room? Write which address. Solo: up to 5 locations.)

10) After online seats fill, how many extra walk-ins can the desk take? (0 if none)
11) Who runs the queue: staff or the doctor?
12) Patient pages: English or Bangla?
13) Staff login? Name + email for the counter
14) Which parts: website+booking / live queue TV / prescription
15) SMS booking confirmation? (prepaid credits; booking still works if the wallet is empty)
16) Preferred link: chamberq.com/______  (short English, e.g. drkarim)

Photos on WhatsApp:
- Doctor portrait
- Logo if you have one
- Photo of your printed pad (if prescription is included)

Website copy can wait: 2–3 lines about you, conditions you treat, 3 FAQs.
```

---

## Part A — must-have (booking + logins)

Fill this yourself from their replies. Blank = cannot go live.

### Practice

| Field | Answer | Why we ask |
|---|---|---|
| Practice / clinic name | | Shows on the site, tickets, and prescription letterhead |
| Preferred URL slug | `chamberq.com/________` | Lowercase letters, numbers, dashes only. Not `admin`, `book`, `find`, `me` |
| Own domain later? | No / Yes: ________ | Day-one default is the platform link. Own domain needs their DNS |
| Patient phone | | Printed on the site |
| WhatsApp (8801…) | | Sales + patient contact |
| Patient language | English / Bangla | Book, ticket, TV, admin. Bangla *homepage articles* are a separate add-on |
| Plan | Maestro (1 doctor) / Clinic (several doctors or labs) | Clinic is the multi-doctor sticker |
| Modules in the deal | Front door (site + book) / Live queue / Prescription | Tick only what they paid for |

### Doctor (repeat a block if Clinic + several doctors)

| Field | Answer |
|---|---|
| Full name as on the pad | |
| Login email | |
| Degrees | e.g. MBBS, FCPS (Medicine) |
| BM&DC number | |
| Practice type | General / Gynecologist / Dentist / Pediatrician / Cardiologist / Dermatologist |
| Normal visit fee ৳ | Staff cannot type a different amount for this type |
| Other fees | e.g. Follow-up ৳____ / Dressing ৳____ (leave blank if every visit is the same) |
| Staff may type this doctor’s prescriptions later? | Yes / No (default No) |
| Staff may book repeating serials (physio, dressings)? | Yes / No (default No) |

### Logins

| Who | Name | Email | Role |
|---|---|---|---|
| Doctor | | | Doctor |
| Counter / manager | | | Staff (content + queue) or Admin (full) |

Doctor email is required to create the tenant. Staff can be added the same day or after the first sitting.

### Each chamber / location

Solo: up to **5**. Copy the table per location.

| Field | Answer |
|---|---|
| Room name patients hear | e.g. Dhanmondi chamber / Popular Diagnostic |
| Full address | |
| Google Maps link | Maps → Share → paste. Not a Facebook page link |
| Desk phone (if different) | |

### Each sitting (this is the booking engine)

One row = one day + one window. If Saturday has morning *and* evening, that is **two** rows.

Real-life example: a cinema does not sell “Saturday tickets”. It sells “Saturday 5pm show, 20 seats”. Same idea.

| Day | Sitting name | Chamber | Start | End | Online serials | Extra walk-in seats |
|---|---|---|---|---|---|---|
| e.g. Saturday | Evening | Dhanmondi | 5:00 pm | 8:00 pm | 20 | 5 |
| | | | | | | |
| | | | | | | |
| | | | | | | |

Closed days: write “Friday off” so we do not invent a sitting.

If two sittings the same day at the same room share one daily limit (unusual), note it. Default is **each sitting has its own cap**.

---

## Part B — website (can wait 24 hours)

Homepage look is already designed. We only need **their words and photos**.

| Item | What to send |
|---|---|
| Doctor portrait | Clear face photo, standing or seated. Not a group wedding shot |
| Logo | Optional. PNG/JPG. Without it we use the name |
| Brand colour | Optional hex or “same as my visiting card” |
| Tagline | One line, e.g. “Chest specialist, Dhanmondi — book online, pay at the chamber” |
| Hero credentials | Same degrees as the pad |
| Role & location | e.g. Consultant Physician, Dhanmondi |
| About — 2–4 lines | Study, years, what they are known for |
| Conditions / treatments | 4–8 names patients search (acne, diabetes follow-up, antenatal…) |
| FAQs | 3 is enough: how to book, walk-in?, what to bring |
| Testimonials | Optional. Name + one sentence. Ask permission |
| Facebook / page link | Optional, for “follow us” |

Do **not** collect insurance-card copy or “HIPAA” language. This is pay-at-chamber Bangladesh practice.

---

## Part C — live queue & prescription (only if bought)

| Field | Options | Default if they skip |
|---|---|---|
| Who runs the queue | Staff call next / Doctor calls next | Staff |
| Waiting-room sound | Chime + voice / chime only / voice only | Chime + voice |
| TV language | English recorded “Number twelve” | English clips (clearer than browser Bangla) |
| SMS after booking | On / Off | On if they bought credits, else Off |
| SMS when doctor is late | On / Off | Off (staff can still WhatsApp) |
| Cancel sitting | WhatsApp tap-to-send / SMS | WhatsApp on, SMS off |
| Send prescription link | WhatsApp / SMS | WhatsApp on, SMS off |
| Follow-up reminder | SMS 3 days before / WhatsApp list | SMS on if credits exist |
| Printed pad already has name? | Yes — we skip letterhead on print / No — we print letterhead | Ask; photo of pad helps |

SMS is prepaid credits. Empty wallet = booking still works, no text. Never promise “unlimited SMS”.

---

## Internal checklist (you, not the doctor)

- [ ] Slug free and not a reserved path
- [ ] Doctor login email in Create Tenant
- [ ] Modules and launch-offer ticks match the WhatsApp quote
- [ ] Marketer / discount code attached if any
- [ ] Every sitting row created (day + start + end + cap)
- [ ] Maps link is a real Google Maps URL
- [ ] Staff account created if they sent an email
- [ ] Queue runner matches who actually stands at the door
- [ ] First sitting day: who opens Live Queue, which laptop/TV

Hand off: Super Admin Create Tenant → Chambers → Schedule Sessions → Branding phone/WhatsApp → (optional) Web Pages copy and photos.

---

## What not to ask

- Bank OTP, bKash PIN, or their personal Gmail password
- Online payment gateway details (patients pay at the chamber)
- A full old patient Excel on day one (they can keep the paper diary; we start clean)
- Custom homepage redesign (layout is fixed; we only change their text and photos)
