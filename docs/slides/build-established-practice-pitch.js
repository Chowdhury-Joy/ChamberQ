const pptxgen = require("pptxgenjs");

const pres = new pptxgen();
pres.layout = "LAYOUT_WIDE"; // 13.333 x 7.5
pres.author = "ChamberQ";
pres.title = "ChamberQ — For Established Practices";

/* ---------------- palette ---------------- */
const NAVY = "0E1954";
const NAVY2 = "1B2978";
const BLUE = "30A9E5";
const MINT = "02C39A";
const CLAY = "B85042";
const BG = "F4F6FA";
const WHITE = "FFFFFF";
const MUTED = "6B7399";
const INK = "1A1F36";
const LINE = "E4E8F0";

// Inter Tight — not in the safe/metric-compatible font list, so every
// container below carries ~10-15% extra height/width slack over what the
// text needs at a safe-font estimate, since local QA cannot confirm fit
// for this exact typeface.
const HEAD = "Inter Tight";
const BODY = "Inter Tight";

const W = 13.333;
const M = 0.7;
const CW = W - M * 2;

/* ---------------- helpers ---------------- */
const soft = () => ({ type: "outer", angle: 90, blur: 14, offset: 3, color: "8892B0", opacity: 0.22 });
const lift = () => ({ type: "outer", angle: 90, blur: 22, offset: 6, color: "0E1954", opacity: 0.28 });

function light(bg) {
  const s = pres.addSlide();
  s.background = { color: bg || BG };
  return s;
}
function dark() {
  const s = pres.addSlide();
  s.background = { color: NAVY };
  return s;
}
function eyebrow(s, t, color, x, y) {
  s.addText(t.toUpperCase(), {
    x: x === undefined ? M : x, y: y === undefined ? 0.52 : y, w: CW, h: 0.3,
    fontFace: BODY, fontSize: 11.5, bold: true, charSpacing: 2.2,
    color: color || BLUE, margin: 0, valign: "middle",
  });
}
function title(s, t, opts) {
  const o = opts || {};
  s.addText(t, {
    x: o.x === undefined ? M : o.x, y: o.y === undefined ? 0.86 : o.y,
    w: o.w === undefined ? CW : o.w, h: o.h === undefined ? 0.9 : o.h,
    fontFace: HEAD, fontSize: o.size || 33, bold: true,
    color: o.color || INK, margin: 0, valign: "middle",
  });
}
function sub(s, t, opts) {
  const o = opts || {};
  s.addText(t, {
    x: o.x === undefined ? M : o.x, y: o.y === undefined ? 1.78 : o.y,
    w: o.w === undefined ? CW - 0.6 : o.w, h: o.h === undefined ? 0.48 : o.h,
    fontFace: BODY, fontSize: o.size || 15.5, color: o.color || MUTED,
    margin: 0, valign: "middle",
  });
}
function card(s, x, y, w, h, fill) {
  s.addShape(pres.ShapeType.roundRect, {
    x, y, w, h, rectRadius: 0.14,
    fill: { color: fill || WHITE }, line: { color: fill ? fill : LINE, width: 1 },
    shadow: soft(),
  });
}
function dot(s, x, y, d, fill, label, labelColor) {
  s.addShape(pres.ShapeType.ellipse, { x, y, w: d, h: d, fill: { color: fill }, line: { color: fill, width: 1 } });
  if (label !== undefined) {
    s.addText(label, {
      x, y, w: d, h: d, fontFace: BODY, fontSize: d > 0.5 ? 14 : 11.5, bold: true,
      color: labelColor || WHITE, align: "center", valign: "middle", margin: 0,
    });
  }
}
function foot(s, t, color) {
  s.addText(t, {
    x: M, y: 6.7, w: CW, h: 0.4, fontFace: BODY, fontSize: 12, italic: true,
    color: color || MUTED, margin: 0, valign: "middle",
  });
}

/* ============ 1 — TITLE ============ */
{
  const s = dark();
  s.addShape(pres.ShapeType.ellipse, { x: 9.4, y: -2.1, w: 6.4, h: 6.4, fill: { color: NAVY2 }, line: { color: NAVY2, width: 1 } });
  eyebrow(s, "For established practices & clinics", BLUE, M, 1.05);
  s.addText("ChamberQ", {
    x: M, y: 1.48, w: 7.2, h: 1.05, fontFace: HEAD, fontSize: 50, bold: true, color: WHITE, margin: 0, valign: "middle",
  });
  s.addText("You're already busy.\nThis keeps you organized.", {
    x: M, y: 2.66, w: 7.0, h: 1.25, fontFace: HEAD, fontSize: 23, color: BLUE, margin: 0, valign: "top", lineSpacing: 32,
  });
  s.addText("This isn't about filling your room. It's about the patients you already see — one clear record per patient, notes your staff can't read, and a website that looks professional.", {
    x: M, y: 4.02, w: 6.6, h: 0.95, fontFace: BODY, fontSize: 14, color: "C9D0E8", margin: 0, valign: "top", lineSpacing: 19,
  });

  const stats = [
    ["One record", "every visit, in one place"],
    ["Private notes", "only you see a diagnosis"],
    ["Fits your practice", "your staff, your chambers, your pace"],
  ];
  stats.forEach((st, i) => {
    const x = M + i * 4.0;
    s.addText(st[0], { x, y: 5.4, w: 3.75, h: 0.44, fontFace: HEAD, fontSize: 18, bold: true, color: MINT, margin: 0, valign: "middle" });
    s.addText(st[1], { x, y: 5.86, w: 3.75, h: 0.65, fontFace: BODY, fontSize: 12, color: "9AA6CC", margin: 0, valign: "top", lineSpacing: 16 });
  });

  // record panel mock
  const px = 8.85, py = 1.55;
  s.addShape(pres.ShapeType.roundRect, { x: px, y: py, w: 3.8, h: 3.35, rectRadius: 0.16, fill: { color: "081041" }, line: { color: NAVY2, width: 1.5 }, shadow: lift() });
  s.addText("PATIENT RECORD", { x: px, y: py + 0.26, w: 3.8, h: 0.3, fontFace: BODY, fontSize: 11, bold: true, charSpacing: 2.2, color: BLUE, align: "center", margin: 0, valign: "middle" });
  s.addText("Ferdousi Rahman", { x: px + 0.3, y: py + 0.68, w: 3.2, h: 0.42, fontFace: HEAD, fontSize: 19, bold: true, color: WHITE, margin: 0, valign: "middle" });
  s.addText("14th visit · under care since 2019", { x: px + 0.3, y: py + 1.08, w: 3.2, h: 0.3, fontFace: BODY, fontSize: 11, color: "9AA6CC", margin: 0, valign: "middle" });
  s.addShape(pres.ShapeType.rect, { x: px + 0.3, y: py + 1.48, w: 3.2, h: 0.012, fill: { color: NAVY2 }, line: { color: NAVY2, width: 1 } });
  s.addText("Diagnosis   Stable hypertension, on follow-up\nAdvice   Continue current dose; recheck in 6 wks\nOn file   Voice note · reprintable prescription", {
    x: px + 0.3, y: py + 1.66, w: 3.2, h: 1.1, fontFace: BODY, fontSize: 11.5, color: "E4E8F0", margin: 0, valign: "top", lineSpacing: 18,
  });
  s.addText("Only the doctor can see this.", { x: px + 0.3, y: py + 2.85, w: 3.2, h: 0.4, fontFace: BODY, fontSize: 10.5, italic: true, color: MINT, margin: 0, valign: "top" });
  s.addNotes("This version is for doctors whose room is already full — the 'patients wait less' pitch does not land here. Lead with continuity, confidentiality, and professional presence instead.");
}

/* ============ 2 — THE PROBLEM ============ */
{
  const s = light();
  eyebrow(s, "The problem");
  title(s, "Paper stopped being enough a while ago");
  sub(s, "This isn't about how many patients you see. It's about what happens to what you write down.");

  const items = [
    ["1", "Old notes get lost", "You've treated this patient for ten years. Last visit's notes are on a piece of paper. You hope nobody threw it out."],
    ["2", "New doctors start from zero", "A junior doctor joins your practice. There's nothing to hand them — no shared notes, nothing at all."],
    ["3", "No proper website", "Patients search your name and find nothing. Or a page someone else made for you, without asking."],
  ];
  items.forEach((it, i) => {
    const x = M + i * 4.02, w = 3.72;
    card(s, x, 2.55, w, 3.6);
    dot(s, x + 0.38, 2.95, 0.62, CLAY, it[0]);
    s.addText(it[1], { x: x + 0.38, y: 3.78, w: w - 0.76, h: 0.78, fontFace: HEAD, fontSize: 18, bold: true, color: INK, margin: 0, valign: "top" });
    s.addText(it[2], { x: x + 0.38, y: 4.62, w: w - 0.76, h: 1.42, fontFace: BODY, fontSize: 13, color: MUTED, margin: 0, valign: "top", lineSpacing: 18 });
  });
  foot(s, "Ask which one bothers them the most. Talk about that one for the rest of the meeting.");
  s.addNotes("Let them tell you which one stings. A senior doctor with a stable practice will usually pick #1 or #3.");
}

/* ============ 3 — WHY IT MATTERS AT YOUR SCALE ============ */
{
  const s = light(WHITE);
  eyebrow(s, "Why it matters at your scale");
  title(s, "You can't remember every patient");
  s.addText("That's not a flaw. That's the job.", {
    x: M, y: 1.66, w: CW, h: 0.6, fontFace: HEAD, fontSize: 29, bold: true, color: BLUE, margin: 0, valign: "middle",
  });

  const rows = [
    ["A", "Nobody can remember everyone", "You see hundreds of patients. Nobody can hold all of that in their head. A record does it for you instead."],
    ["B", "One bad prescription hurts your name", "A lost or hard-to-read prescription costs trust you spent years building. A printed one always looks right."],
    ["C", "New doctors need your notes too", "As your practice grows past just you, notes in your own handwriting can't be shared. A record on a screen can."],
    ["D", "A well-known doctor has more to lose", "The more people know your name, the more a leaked diagnosis would cost you. Staff should never be able to see one."],
  ];
  rows.forEach((r, i) => {
    const col = i % 2, row = Math.floor(i / 2);
    const x = M + col * 6.15, y = 2.62 + row * 1.95;
    card(s, x, y, 5.75, 1.7, "F4F6FA");
    dot(s, x + 0.32, y + 0.5, 0.56, NAVY, r[0]);
    s.addText(r[1], { x: x + 1.05, y: y + 0.18, w: 4.45, h: 0.62, fontFace: HEAD, fontSize: 16.5, bold: true, color: INK, margin: 0, valign: "top" });
    s.addText(r[2], { x: x + 1.05, y: y + 0.82, w: 4.45, h: 0.82, fontFace: BODY, fontSize: 12.5, color: MUTED, margin: 0, valign: "top", lineSpacing: 17 });
  });
  foot(s, "If a fuller waiting room is also something they want, booking and the live queue still do that too — it's just not the reason to start.", NAVY2);
  s.addNotes("The money slide for this audience. Do not mention wait times here — pivot straight to reputation and continuity risk.");
}

/* ============ 4 — WHAT'S THE SAME UNDERNEATH ============ */
{
  const s = light();
  eyebrow(s, "One system, two ways to use it");
  title(s, "Booking and the queue are still here");
  sub(s, "You still get everything. You just don't have to use it the same way — it runs quietly in the background.");

  const steps = [
    ["Booking & ticket", "Patients can still book online and get a serial number and a ticket. Useful for staff — you don't have to promote it."],
    ["The waiting room", "The screen and the queue still keep order in the room on busy days. Your staff run it, not you."],
    ["What you actually care about", "The patient record, the prescription, and who can see them. That's next."],
  ];
  steps.forEach((st, i) => {
    const x = M + i * 4.02, w = 3.72;
    card(s, x, 2.55, w, 3.3);
    dot(s, x + 0.38, 2.9, 0.6, i === 2 ? MINT : BLUE, String(i + 1));
    s.addText(st[0], { x: x + 0.38, y: 3.72, w: w - 0.76, h: 0.62, fontFace: HEAD, fontSize: 17, bold: true, color: INK, margin: 0, valign: "top" });
    s.addText(st[1], { x: x + 0.38, y: 4.36, w: w - 0.76, h: 1.35, fontFace: BODY, fontSize: 13, color: MUTED, margin: 0, valign: "top", lineSpacing: 18 });
  });
  foot(s, "None of this forces your staff to change how they already run the room.");
}

/* ============ 5 — CONTINUITY AT SCALE ============ */
{
  const s = light(WHITE);
  eyebrow(s, "Patient records");
  title(s, "The right history, without searching");
  sub(s, "You don't have time to search for a patient. You shouldn't have to.");

  const cx = M, cy = 2.5;
  s.addShape(pres.ShapeType.roundRect, { x: cx, y: cy, w: 5.9, h: 3.75, rectRadius: 0.16, fill: { color: "F4F6FA" }, line: { color: LINE, width: 1.5 }, shadow: soft() });
  s.addText("Ferdousi Rahman", { x: cx + 0.4, y: cy + 0.28, w: 3.5, h: 0.44, fontFace: HEAD, fontSize: 22, bold: true, color: INK, margin: 0, valign: "middle" });
  s.addText("Female · 58 yrs · 14th visit · last seen 3 wks ago", { x: cx + 0.4, y: cy + 0.72, w: 5.1, h: 0.3, fontFace: BODY, fontSize: 12, color: MUTED, margin: 0, valign: "middle" });
  s.addShape(pres.ShapeType.roundRect, { x: cx + 0.4, y: cy + 1.12, w: 5.1, h: 0.48, rectRadius: 0.08, fill: { color: "FBEAE7" }, line: { color: CLAY, width: 1 } });
  s.addText("Known: Hypertension, on daily medication", { x: cx + 0.62, y: cy + 1.12, w: 4.8, h: 0.48, fontFace: BODY, fontSize: 12, bold: true, color: CLAY, margin: 0, valign: "middle" });
  s.addText("LAST VISIT", { x: cx + 0.4, y: cy + 1.76, w: 5.1, h: 0.28, fontFace: BODY, fontSize: 10, bold: true, charSpacing: 2, color: BLUE, margin: 0, valign: "middle" });
  s.addText("Diagnosis   Stable hypertension, follow-up\nAdvice   Continue current dose, recheck 6 wks\nOn file   Voice note (18s)  ·  Prescription photo", {
    x: cx + 0.4, y: cy + 2.04, w: 5.1, h: 1.05, fontFace: BODY, fontSize: 12.5, color: INK, margin: 0, valign: "top", lineSpacing: 19,
  });
  s.addText("Reprint last prescription  ·  Play voice note", { x: cx + 0.4, y: cy + 3.22, w: 5.1, h: 0.32, fontFace: BODY, fontSize: 11.5, bold: true, color: BLUE, margin: 0, valign: "middle" });

  const rr = [
    ["Shows up the moment they're called", "No file to find, no name to type. The patient in the room and the record on your screen are always the same person."],
    ["Works the same at visit 1 or visit 40", "The more times you've seen someone, the more useful this gets — not harder to keep track of."],
    ["Never required", "Leave it blank, talk for 15 seconds, photograph your paper note, or have your staff key it in from the slip — all count."],
    ["Tells the truth when there's nothing yet", "New patient, no history — it says so plainly. It never makes something up."],
  ];
  rr.forEach((r, i) => {
    const y = 2.55 + i * 1.0;
    s.addText(r[0], { x: 6.98, y, w: 5.65, h: 0.36, fontFace: HEAD, fontSize: 15.5, bold: true, color: INK, margin: 0, valign: "top" });
    s.addText(r[1], { x: 6.98, y: y + 0.38, w: 5.65, h: 0.6, fontFace: BODY, fontSize: 12, color: MUTED, margin: 0, valign: "top", lineSpacing: 16 });
  });
  s.addNotes("The pitch here is scale, not novelty — 'this gets more valuable the more patients you see,' the opposite framing from the general-audience deck.");
}

/* ============ 6 — CONFIDENTIALITY ============ */
{
  const s = dark();
  eyebrow(s, "Confidentiality", BLUE);
  title(s, "Staff run the room. Not the notes.", { color: WHITE });
  sub(s, "The better known you are, the more this matters. It's not a switch you flip — it's just how the system works.", { color: "9AA6CC" });

  const cols = [
    ["Your staff", BLUE, ["The day's schedule and queue", "Booking and phone numbers", "Website text and photos"], "They can never open a diagnosis, a prescription, or a voice note. You don't set that up — it's already like that."],
    ["Other doctors in your practice", MINT, ["Their own patients' notes", "Handover notes you choose to share", "Their own prescriptions"], "Each doctor sees their own patients. Not everyone sees everyone's, unless you choose to share it."],
    ["Only the treating doctor", "FFFFFF", ["The full diagnosis and advice history", "Every prescription, printable again", "Voice notes and photos"], "This is the one list with everything on it. On purpose."],
  ];
  cols.forEach((c, i) => {
    const x = M + i * 4.02, w = 3.72;
    const featured = i === 2;
    s.addShape(pres.ShapeType.roundRect, {
      x, y: 2.5, w, h: 3.6, rectRadius: 0.14,
      fill: { color: featured ? "081041" : "13205C" },
      line: { color: featured ? MINT : NAVY2, width: featured ? 1.5 : 1 },
      shadow: lift(),
    });
    s.addShape(pres.ShapeType.roundRect, { x: x + 0.36, y: 2.82, w: 2.5, h: 0.42, rectRadius: 0.21, fill: { color: c[1] }, line: { color: c[1], width: 1 } });
    s.addText(c[0], { x: x + 0.36, y: 2.82, w: 2.5, h: 0.42, fontFace: BODY, fontSize: 11.5, bold: true, color: c[1] === "FFFFFF" ? NAVY : WHITE, align: "center", valign: "middle", margin: 0 });
    s.addText(
      c[2].map((b, j) => ({ text: b, options: { bullet: true, breakLine: j !== c[2].length - 1 } })),
      { x: x + 0.36, y: 3.45, w: w - 0.72, h: 1.45, fontFace: BODY, fontSize: 12.5, color: "E4E8F0", margin: 0, valign: "top", paraSpaceAfter: 7 }
    );
    s.addText(c[3], { x: x + 0.36, y: 5.02, w: w - 0.72, h: 0.95, fontFace: BODY, fontSize: 11.5, italic: true, color: "9AA6CC", margin: 0, valign: "top", lineSpacing: 16 });
  });
  s.addNotes("For a prominent doctor, this slide can matter more than pricing. Give it time. Mention it protects them, not just patients.");
}

/* ============ 7 — GROWING PAST JUST YOU ============ */
{
  const s = light();
  eyebrow(s, "Growing the practice");
  title(s, "Room to grow — more doctors, more chambers, a lab", { h: 1.3 });

  const cards = [
    ["Add doctors without adding chaos", "Each new doctor works inside the same system, with their own access — and their own medicine list, matched to their specialty. Not a separate notebook for each person."],
    ["Multiple chambers, one view", "Different days, different places, or a second branch — bookings, the queue, and records all stay in one place."],
    ["Lab tests inside the same booking", "If your practice offers lab tests, patients add one to the same appointment — not a separate errand."],
    ["Upgrade when you're ready, not before", "Use what you need today. Move to Clinic the day you add a second doctor or a second room."],
  ];
  cards.forEach((c, i) => {
    const col = i % 2, row = Math.floor(i / 2);
    const x = M + col * 6.15, y = 2.4 + row * 2.05;
    card(s, x, y, 5.75, 1.8);
    dot(s, x + 0.38, y + 0.4, 0.5, NAVY, String(i + 1));
    s.addText(c[0], { x: x + 1.06, y: y + 0.22, w: 4.4, h: 0.6, fontFace: HEAD, fontSize: 16.5, bold: true, color: INK, margin: 0, valign: "top" });
    s.addText(c[1], { x: x + 1.06, y: y + 0.82, w: 4.4, h: 0.88, fontFace: BODY, fontSize: 12.5, color: MUTED, margin: 0, valign: "top", lineSpacing: 17 });
  });
  foot(s, "Solo already covers one doctor across up to five chambers. You only need Clinic once a second doctor joins.");
}

/* ============ 8 — YOUR PROFESSIONAL PRESENCE ============ */
{
  const s = light(WHITE);
  eyebrow(s, "Your presence");
  title(s, "A website that looks like the doctor you are");
  sub(s, "Patients already search your name. What they find should look as good as your practice.");

  const bx = M, by = 2.5;
  s.addShape(pres.ShapeType.roundRect, { x: bx, y: by, w: 6.2, h: 3.55, rectRadius: 0.14, fill: { color: "F4F6FA" }, line: { color: LINE, width: 1.5 }, shadow: soft() });
  s.addShape(pres.ShapeType.roundRect, { x: bx + 0.3, y: by + 0.28, w: 5.6, h: 0.4, rectRadius: 0.2, fill: { color: WHITE }, line: { color: LINE, width: 1 } });
  s.addText("drferdousi.com", { x: bx + 0.55, y: by + 0.28, w: 3.0, h: 0.4, fontFace: BODY, fontSize: 12, color: MUTED, margin: 0, valign: "middle" });
  s.addText("Dr. Ferdousi Rahman", { x: bx + 0.5, y: by + 0.92, w: 5.2, h: 0.5, fontFace: HEAD, fontSize: 26, bold: true, color: INK, margin: 0, valign: "middle" });
  s.addText("MBBS, MD (Cardiology)  ·  25 years in practice", { x: bx + 0.5, y: by + 1.42, w: 5.2, h: 0.32, fontFace: BODY, fontSize: 12.5, color: MUTED, margin: 0, valign: "middle" });
  s.addShape(pres.ShapeType.roundRect, { x: bx + 0.5, y: by + 1.92, w: 2.1, h: 0.5, rectRadius: 0.25, fill: { color: NAVY }, line: { color: NAVY, width: 1 } });
  s.addText("Book Appointment", { x: bx + 0.5, y: by + 1.92, w: 2.1, h: 0.5, fontFace: BODY, fontSize: 11.5, bold: true, color: WHITE, align: "center", valign: "middle", margin: 0 });
  s.addText("Chambers  ·  Associates  ·  Conditions treated", { x: bx + 0.5, y: by + 2.62, w: 5.2, h: 0.32, fontFace: BODY, fontSize: 12, color: MUTED, margin: 0, valign: "middle" });
  s.addText("Your own degrees, not a generic template.", { x: bx + 0.5, y: by + 3.0, w: 5.2, h: 0.35, fontFace: BODY, fontSize: 11, italic: true, color: MUTED, margin: 0, valign: "middle" });

  const pts = [
    ["Your degrees, shown clearly", "Your specialty and years in practice, shown where people search for them — the things that took decades to earn."],
    ["Every chamber and doctor listed", "A practice with more than one location or doctor shows all of them on one page — not scattered across Facebook posts."],
    ["Your staff can update it, not you", "A new timing or a new photo, changed by your staff. No developer, no phone call."],
    ["One button on every page: Book", "Every path on the site leads there."],
  ];
  pts.forEach((p, i) => {
    const y = 2.55 + i * 0.98;
    dot(s, 7.35, y + 0.05, 0.3, MINT, "✓");
    s.addText(p[0], { x: 7.83, y, w: 4.8, h: 0.38, fontFace: HEAD, fontSize: 15.5, bold: true, color: INK, margin: 0, valign: "top" });
    s.addText(p[1], { x: 7.83, y: y + 0.4, w: 4.8, h: 0.58, fontFace: BODY, fontSize: 12, color: MUTED, margin: 0, valign: "top", lineSpacing: 16 });
  });
}

/* ============ 9 — WHAT DOES NOT CHANGE ============ */
{
  const s = light();
  eyebrow(s, "Your worries, answered");
  title(s, "What stays exactly as it is");

  const rows = [
    ["Your money", "Patients pay at the chamber, in cash, exactly as today. No online payment, no commission on your fee."],
    ["Your staff structure", "Your staff keep doing what they already do — reception, queue, records. Nobody gets access they shouldn't."],
    ["Your paper, if you want it", "Nothing clinical is required. Keep writing on paper and photograph it, or let your staff type it in from the slip — your choice, per doctor."],
    ["Your time to start", "We set everything up with you — your site, chambers, doctors, logins. You don't touch a computer to start."],
  ];
  rows.forEach((r, i) => {
    const col = i % 2, row = Math.floor(i / 2);
    const x = M + col * 6.15, y = 2.35 + row * 2.05;
    card(s, x, y, 5.75, 1.8);
    dot(s, x + 0.38, y + 0.4, 0.5, MINT, "✓");
    s.addText(r[0], { x: x + 1.06, y: y + 0.22, w: 4.4, h: 0.6, fontFace: HEAD, fontSize: 17, bold: true, color: INK, margin: 0, valign: "top" });
    s.addText(r[1], { x: x + 1.06, y: y + 0.82, w: 4.4, h: 0.88, fontFace: BODY, fontSize: 12.5, color: MUTED, margin: 0, valign: "top", lineSpacing: 17 });
  });
  foot(s, "One thing to know: this needs internet in the chamber. On a bad day, you fall back to paper — the same as today.", NAVY2);
  s.addNotes("Volunteer the internet caveat before they ask, same as the general deck.");
}

/* ============ 10 — PRICING ============ */
{
  const s = light(WHITE);
  eyebrow(s, "Investment");
  title(s, "Two plans. Simple pricing.");

  const plan = (x, name, tag, setup, monthly, feats, featured) => {
    card(s, x, 2.35, 5.75, 3.4, featured ? NAVY : WHITE);
    const fg = featured ? WHITE : INK;
    const mid = featured ? "C9D0E8" : MUTED;
    s.addText(name, { x: x + 0.45, y: 2.62, w: 3.0, h: 0.45, fontFace: HEAD, fontSize: 24, bold: true, color: fg, margin: 0, valign: "middle" });
    s.addText(tag, { x: x + 0.45, y: 3.06, w: 4.9, h: 0.3, fontFace: BODY, fontSize: 12, color: mid, margin: 0, valign: "middle" });
    s.addText(setup, { x: x + 0.45, y: 3.5, w: 2.5, h: 0.5, fontFace: HEAD, fontSize: 23, bold: true, color: featured ? MINT : NAVY, margin: 0, valign: "middle" });
    s.addText("one-time setup", { x: x + 0.45, y: 3.98, w: 2.5, h: 0.28, fontFace: BODY, fontSize: 11, color: mid, margin: 0, valign: "middle" });
    s.addText(monthly, { x: x + 3.1, y: 3.5, w: 2.4, h: 0.5, fontFace: HEAD, fontSize: 23, bold: true, color: featured ? MINT : NAVY, margin: 0, valign: "middle" });
    s.addText("per month", { x: x + 3.1, y: 3.98, w: 2.4, h: 0.28, fontFace: BODY, fontSize: 11, color: mid, margin: 0, valign: "middle" });
    s.addText(
      feats.map((f, j) => ({ text: f, options: { bullet: true, breakLine: j !== feats.length - 1 } })),
      { x: x + 0.45, y: 4.42, w: 4.9, h: 1.2, fontFace: BODY, fontSize: 12.5, color: featured ? "E4E8F0" : INK, margin: 0, valign: "top", paraSpaceAfter: 5 }
    );
    if (featured) {
      s.addShape(pres.ShapeType.roundRect, { x: x + 3.95, y: 2.62, w: 1.4, h: 0.36, rectRadius: 0.18, fill: { color: MINT }, line: { color: MINT, width: 1 } });
      s.addText("FOR YOUR SCALE", { x: x + 3.95, y: 2.62, w: 1.4, h: 0.36, fontFace: BODY, fontSize: 8.5, bold: true, charSpacing: 0.6, color: NAVY, align: "center", valign: "middle", margin: 0 });
    }
  };
  plan(M, "Solo", "One doctor, up to 5 chambers", "Tk 15,000", "Tk 3,000", [
    "Patient records and prescriptions",
    "Only you see clinical notes",
    "Your own website",
    "We set it up with you",
  ], false);
  plan(M + 6.15, "Clinic", "Multiple doctors, chambers and labs", "Tk 75,000", "Tk 7,500", [
    "Everything in Solo, for a full clinic",
    "Each doctor sees only their own patients",
    "As many chambers as you need",
    "Lab tests inside the same booking",
  ], true);

  card(s, M, 5.98, CW, 0.78, "F4F6FA");
  s.addText("SMS is optional, about Tk 0.50 a text, paid in advance.   Pay by bKash or bank, the same way you do now.", {
    x: M + 0.4, y: 5.98, w: CW - 0.8, h: 0.78, fontFace: BODY, fontSize: 12, color: INK, margin: 0, valign: "middle",
  });
  s.addNotes("Clinic is the natural fit for this audience — feature it, but don't dismiss Solo if they're still a single-chamber practice.");
}

/* ============ 11 — CLOSE ============ */
{
  const s = dark();
  s.addShape(pres.ShapeType.ellipse, { x: -1.9, y: 3.6, w: 6.2, h: 6.2, fill: { color: NAVY2 }, line: { color: NAVY2, width: 1 } });
  eyebrow(s, "Next step", BLUE, M, 1.3);
  s.addText("Give me three things today.", {
    x: M, y: 1.68, w: 8.1, h: 0.95, fontFace: HEAD, fontSize: 36, bold: true, color: WHITE, margin: 0, valign: "middle",
  });
  const three = [
    ["1", "Your chambers, and any other doctors"],
    ["2", "How you want your name and degrees shown"],
    ["3", "One photograph of yourself"],
  ];
  three.forEach((t, i) => {
    const y = 2.95 + i * 0.8;
    dot(s, M, y + 0.02, 0.46, BLUE, t[0]);
    s.addText(t[1], { x: M + 0.68, y, w: 7.2, h: 0.55, fontFace: BODY, fontSize: 16, color: WHITE, margin: 0, valign: "middle" });
  });
  s.addText("Your website and records go live under your own name.\nNothing changes for your patients until you say so.", {
    x: M, y: 5.42, w: 8.2, h: 1.15, fontFace: HEAD, fontSize: 19, color: MINT, margin: 0, valign: "top", lineSpacing: 27,
  });

  const bx = 9.0;
  s.addShape(pres.ShapeType.roundRect, { x: bx, y: 2.35, w: 3.6, h: 2.9, rectRadius: 0.16, fill: { color: "081041" }, line: { color: NAVY2, width: 1.5 }, shadow: lift() });
  s.addText("ChamberQ", { x: bx, y: 2.7, w: 3.6, h: 0.45, fontFace: HEAD, fontSize: 20, bold: true, color: WHITE, align: "center", margin: 0, valign: "middle" });
  s.addText("WhatsApp", { x: bx, y: 3.3, w: 3.6, h: 0.3, fontFace: BODY, fontSize: 11, bold: true, charSpacing: 2, color: BLUE, align: "center", margin: 0, valign: "middle" });
  s.addText("01700-000000", { x: bx, y: 3.6, w: 3.6, h: 0.5, fontFace: HEAD, fontSize: 22, bold: true, color: MINT, align: "center", margin: 0, valign: "middle" });
  s.addText("Replace with your number\nbefore the meeting", { x: bx, y: 4.25, w: 3.6, h: 0.7, fontFace: BODY, fontSize: 10.5, italic: true, color: "6B7399", align: "center", margin: 0, valign: "top" });
  s.addNotes("Ask for credentials and associate names, not a signature. For this audience, precision about how their name/credentials appear matters — get it right before building.");
}

pres.writeFile({ fileName: "/tmp/dgdeck2/ChamberQ-Established-Practice-Pitch.pptx" }).then((f) => console.log("written:", f));
