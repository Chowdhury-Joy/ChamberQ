const pptxgen = require("pptxgenjs");

const pres = new pptxgen();
pres.layout = "LAYOUT_WIDE"; // 13.333 x 7.5
pres.author = "ChamberQ";
pres.title = "ChamberQ — Client Presentation";

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

const HEAD = "Cambria";
const BODY = "Calibri";

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
    x: x === undefined ? M : x, y: y === undefined ? 0.52 : y, w: CW, h: 0.28,
    fontFace: BODY, fontSize: 11.5, bold: true, charSpacing: 2.2,
    color: color || BLUE, margin: 0, valign: "middle",
  });
}
function title(s, t, opts) {
  const o = opts || {};
  s.addText(t, {
    x: o.x === undefined ? M : o.x, y: o.y === undefined ? 0.85 : o.y,
    w: o.w === undefined ? CW : o.w, h: o.h === undefined ? 0.85 : o.h,
    fontFace: HEAD, fontSize: o.size || 36, bold: true,
    color: o.color || INK, margin: 0, valign: "middle",
  });
}
function sub(s, t, opts) {
  const o = opts || {};
  s.addText(t, {
    x: o.x === undefined ? M : o.x, y: o.y === undefined ? 1.72 : o.y,
    w: o.w === undefined ? CW - 0.6 : o.w, h: o.h === undefined ? 0.42 : o.h,
    fontFace: BODY, fontSize: o.size || 16, color: o.color || MUTED,
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
      x, y, w: d, h: d, fontFace: BODY, fontSize: d > 0.5 ? 15 : 12, bold: true,
      color: labelColor || WHITE, align: "center", valign: "middle", margin: 0,
    });
  }
}
function foot(s, t, color) {
  s.addText(t, {
    x: M, y: 6.72, w: CW, h: 0.34, fontFace: BODY, fontSize: 12.5, italic: true,
    color: color || MUTED, margin: 0, valign: "middle",
  });
}

/* ============ 1 — TITLE ============ */
{
  const s = dark();
  s.addShape(pres.ShapeType.ellipse, { x: 9.4, y: -2.1, w: 6.4, h: 6.4, fill: { color: NAVY2 }, line: { color: NAVY2, width: 1 } });
  eyebrow(s, "For doctors & chambers in Bangladesh", BLUE, M, 1.05);
  s.addText("ChamberQ", {
    x: M, y: 1.45, w: 7.2, h: 1.05, fontFace: HEAD, fontSize: 54, bold: true, color: WHITE, margin: 0, valign: "middle",
  });
  s.addText("Your chamber runs itself.\nYou just see patients.", {
    x: M, y: 2.62, w: 6.6, h: 1.2, fontFace: HEAD, fontSize: 25, color: BLUE, margin: 0, valign: "top", lineSpacing: 34,
  });
  s.addText("Online serial booking · live waiting-room queue · patient records — built for the way a Bangladeshi chamber already works.", {
    x: M, y: 3.95, w: 6.5, h: 0.8, fontFace: BODY, fontSize: 15, color: "C9D0E8", margin: 0, valign: "top",
  });

  const stats = [
    ["2 hrs → 15 min", "typical patient wait"],
    ["Pay at chamber", "no online payment, no change to your cash"],
    ["Set up for you", "you don't touch a computer to go live"],
  ];
  stats.forEach((st, i) => {
    const x = M + i * 4.0;
    s.addText(st[0], { x, y: 5.35, w: 3.7, h: 0.42, fontFace: HEAD, fontSize: 20, bold: true, color: MINT, margin: 0, valign: "middle" });
    s.addText(st[1], { x, y: 5.78, w: 3.7, h: 0.6, fontFace: BODY, fontSize: 12.5, color: "9AA6CC", margin: 0, valign: "top" });
  });

  // now-serving panel mock
  const px = 8.9, py = 1.55;
  s.addShape(pres.ShapeType.roundRect, { x: px, y: py, w: 3.75, h: 3.15, rectRadius: 0.16, fill: { color: "081041" }, line: { color: NAVY2, width: 1.5 }, shadow: lift() });
  s.addText("NOW SERVING", { x: px, y: py + 0.28, w: 3.75, h: 0.3, fontFace: BODY, fontSize: 11.5, bold: true, charSpacing: 2.4, color: BLUE, align: "center", margin: 0, valign: "middle" });
  s.addText("12", { x: px, y: py + 0.6, w: 3.75, h: 1.75, fontFace: HEAD, fontSize: 96, bold: true, color: WHITE, align: "center", margin: 0, valign: "middle" });
  s.addText("Dr. Karim  ·  Evening session", { x: px, y: py + 2.3, w: 3.75, h: 0.3, fontFace: BODY, fontSize: 12, color: "9AA6CC", align: "center", margin: 0, valign: "middle" });
  s.addText("Next  13  ·  14  ·  15", { x: px, y: py + 2.62, w: 3.75, h: 0.32, fontFace: BODY, fontSize: 13, bold: true, color: MINT, align: "center", margin: 0, valign: "middle" });
  s.addText("The screen outside your room — it calls the number out loud.", {
    x: px, y: py + 3.35, w: 3.75, h: 0.6, fontFace: BODY, fontSize: 12, italic: true, color: "9AA6CC", align: "center", margin: 0, valign: "top",
  });
  s.addNotes("Open here. Do not read the slide. Ask: 'How many patients are sitting in your room at 5pm for a 7pm serial?' Let them answer before you continue.");
}

/* ============ 2 — THE PROBLEM ============ */
{
  const s = light();
  eyebrow(s, "The problem");
  title(s, "An evening in your chamber, today");
  sub(s, "None of this is a technology problem. It is a queue problem — and it costs you patients.");

  const items = [
    ["1", "The phone never stops", "Your assistant is giving out serials by phone right through your consult hours. Every call is a patient interrupted."],
    ["2", "Everyone arrives at once", "Thirty people sit from 4 PM for a 7 PM serial. Then the arguments start — “I came first”, “he jumped my turn”."],
    ["3", "Nothing is remembered", "A patient returns after four months. What did you diagnose? What did you prescribe? The paper is with them, and they lost it."],
  ];
  items.forEach((it, i) => {
    const x = M + i * 4.02, w = 3.72;
    card(s, x, 2.5, w, 3.6);
    dot(s, x + 0.38, 2.9, 0.62, CLAY, it[0]);
    s.addText(it[1], { x: x + 0.38, y: 3.72, w: w - 0.76, h: 0.72, fontFace: HEAD, fontSize: 20, bold: true, color: INK, margin: 0, valign: "top" });
    s.addText(it[2], { x: x + 0.38, y: 4.5, w: w - 0.76, h: 1.4, fontFace: BODY, fontSize: 14, color: MUTED, margin: 0, valign: "top", lineSpacing: 20 });
  });
  foot(s, "Ask them which of the three annoys them most. Build the rest of the meeting around that one.");
  s.addNotes("Let the doctor tell you which one is worst. Do not argue for all three.");
}

/* ============ 3 — WHY IT COSTS THEM ============ */
{
  const s = light(WHITE);
  eyebrow(s, "Why it matters to you");
  title(s, "Waiting is not your problem —");
  s.addText("until it starts costing you patients.", {
    x: M, y: 1.62, w: CW, h: 0.6, fontFace: HEAD, fontSize: 32, bold: true, color: BLUE, margin: 0, valign: "middle",
  });

  const rows = [
    ["A", "Patients leave before their turn", "A full room at 5 PM means some people walk out. Those consult fees never happened, and you never knew."],
    ["B", "You sit idle between patients", "Serial 14 wandered off to eat. Your assistant hunts for him while you wait. That gap repeats all evening."],
    ["C", "Your assistant is a call centre", "You already pay for that time. It should be spent managing the room, not answering the same question."],
    ["D", "Word of mouth is your only growth", "“At Dr. Karim's chamber you don't have to wait.” No competing doctor on your road can say that yet."],
  ];
  rows.forEach((r, i) => {
    const col = i % 2, row = Math.floor(i / 2);
    const x = M + col * 6.15, y = 2.6 + row * 1.92;
    card(s, x, y, 5.75, 1.68, "F4F6FA");
    dot(s, x + 0.32, y + 0.5, 0.56, NAVY, r[0]);
    s.addText(r[1], { x: x + 1.05, y: y + 0.18, w: 4.45, h: 0.6, fontFace: HEAD, fontSize: 18, bold: true, color: INK, margin: 0, valign: "middle" });
    s.addText(r[2], { x: x + 1.05, y: y + 0.8, w: 4.45, h: 0.8, fontFace: BODY, fontSize: 13.5, color: MUTED, margin: 0, valign: "top", lineSpacing: 18 });
  });
  foot(s, "If you are already fully booked and patients will wait regardless — skip ahead to the records and prescriptions.", NAVY2);
  s.addNotes("This is the money slide. Ask directly: on a busy evening, how many people leave before their turn? Whatever number they say is the monthly fee, several times over.");
}

/* ============ 4 — BEFORE / AFTER ============ */
{
  const s = light();
  eyebrow(s, "The change");
  title(s, "Same chamber. Same fees. Better evening.");

  const mk = (x, tone, tag, value, label, bullets) => {
    card(s, x, 2.35, 5.75, 3.9, tone === "before" ? WHITE : NAVY);
    const fg = tone === "before" ? INK : WHITE;
    const mid = tone === "before" ? MUTED : "C9D0E8";
    const acc = tone === "before" ? CLAY : MINT;
    s.addText(tag.toUpperCase(), { x: x + 0.42, y: 2.68, w: 4.9, h: 0.3, fontFace: BODY, fontSize: 11.5, bold: true, charSpacing: 2.2, color: acc, margin: 0, valign: "middle" });
    s.addText(value, { x: x + 0.42, y: 3.0, w: 4.9, h: 0.85, fontFace: HEAD, fontSize: 46, bold: true, color: fg, margin: 0, valign: "middle" });
    s.addText(label, { x: x + 0.42, y: 3.85, w: 4.9, h: 0.32, fontFace: BODY, fontSize: 13.5, color: mid, margin: 0, valign: "middle" });
    s.addText(
      bullets.map((b, i) => ({ text: b, options: { bullet: true, breakLine: i !== bullets.length - 1 } })),
      { x: x + 0.42, y: 4.35, w: 4.9, h: 1.65, fontFace: BODY, fontSize: 14, color: tone === "before" ? INK : "E4E8F0", margin: 0, valign: "top", paraSpaceAfter: 7 }
    );
  };
  mk(M, "before", "Today", "2 hrs", "average patient wait", [
    "Phone ringing through your consults",
    "Patients idle in the room for hours",
    "Scramble at the chamber door",
    "No record of the last visit",
  ]);
  mk(M + 6.15, "after", "With ChamberQ", "15 min", "average patient wait", [
    "Serials booked on the patient's phone",
    "They come when the ticket says to come",
    "A calm queue the screen controls",
    "Last visit on your screen, automatically",
  ]);
  s.addNotes("These are the numbers already on our marketing site. Do not promise an empty waiting room — say you are smoothing the crowd, not removing it.");
}

/* ============ 5 — HOW IT WORKS ============ */
{
  const s = light(WHITE);
  eyebrow(s, "How it works");
  title(s, "Four steps. That is the whole system.");
  sub(s, "Everything else in this deck is a detail inside one of these four.");

  const steps = [
    ["1", "Patient books a serial", "On your page, from their phone. No payment online."],
    ["2", "SMS + live ticket", "Their name and serial by SMS, plus a link that shows when to come."],
    ["3", "The screen calls them", "You tap Call. The screen outside flips the number and says it aloud."],
    ["4", "You see the patient", "Their history is already on your screen. Complete — that's it."],
  ];
  steps.forEach((st, i) => {
    const x = M + i * 3.06, w = 2.78;
    card(s, x, 2.6, w, 3.3);
    dot(s, x + 0.34, 2.95, 0.66, i === 3 ? MINT : BLUE, st[0]);
    s.addText(st[1], { x: x + 0.34, y: 3.8, w: w - 0.68, h: 0.8, fontFace: HEAD, fontSize: 18, bold: true, color: INK, margin: 0, valign: "top" });
    s.addText(st[2], { x: x + 0.34, y: 4.62, w: w - 0.68, h: 1.1, fontFace: BODY, fontSize: 13.5, color: MUTED, margin: 0, valign: "top", lineSpacing: 18 });
    if (i < 3) {
      s.addText("›", { x: x + w - 0.06, y: 4.05, w: 0.4, h: 0.4, fontFace: BODY, fontSize: 26, bold: true, color: LINE, align: "center", valign: "middle", margin: 0 });
    }
  });
  foot(s, "Walk-in patients go into the same queue — your staff add them in two taps.");
}

/* ============ 6 — BOOKING ============ */
{
  const s = light();
  eyebrow(s, "Step 1 — the patient");
  title(s, "They take a serial without calling you");

  const bl = [
    ["Four short steps", "Pick the day and session, enter name and phone, done. Every step is labelled so nobody gets lost."],
    ["The whole family, one number", "If the phone is already known, they simply choose who the appointment is for — mother, child, or someone new."],
    ["Nothing to install", "It opens in the browser. The ticket goes to WhatsApp in one tap."],
    ["No online payment", "They pay you at the chamber exactly as they do today."],
  ];
  bl.forEach((b, i) => {
    const y = 2.4 + i * 1.08;
    dot(s, M, y + 0.08, 0.34, BLUE, "✓");
    s.addText(b[0], { x: M + 0.55, y: y, w: 6.6, h: 0.36, fontFace: HEAD, fontSize: 18, bold: true, color: INK, margin: 0, valign: "middle" });
    s.addText(b[1], { x: M + 0.55, y: y + 0.36, w: 6.6, h: 0.62, fontFace: BODY, fontSize: 13.5, color: MUTED, margin: 0, valign: "top", lineSpacing: 18 });
  });

  // phone mock
  const px = 8.85, py = 1.75;
  s.addShape(pres.ShapeType.roundRect, { x: px, y: py, w: 3.4, h: 4.7, rectRadius: 0.3, fill: { color: WHITE }, line: { color: NAVY, width: 2 }, shadow: soft() });
  s.addShape(pres.ShapeType.roundRect, { x: px + 0.25, y: py + 0.35, w: 2.9, h: 0.62, rectRadius: 0.1, fill: { color: NAVY }, line: { color: NAVY, width: 1 } });
  s.addText("Step 2 of 4 — Pick a session", { x: px + 0.25, y: py + 0.35, w: 2.9, h: 0.62, fontFace: BODY, fontSize: 11.5, bold: true, color: WHITE, align: "center", valign: "middle", margin: 0 });
  const opts = [["Friday · 5:00 – 9:00 PM", "8 serials left", true], ["Saturday · 5:00 – 9:00 PM", "14 serials left", false], ["Monday · 10 AM – 1 PM", "20 serials left", false]];
  opts.forEach((o, i) => {
    const y = py + 1.15 + i * 0.95;
    s.addShape(pres.ShapeType.roundRect, { x: px + 0.25, y, w: 2.9, h: 0.78, rectRadius: 0.1, fill: { color: o[2] ? "E8F5FC" : "F4F6FA" }, line: { color: o[2] ? BLUE : LINE, width: o[2] ? 1.75 : 1 } });
    s.addText(o[0], { x: px + 0.42, y: y + 0.09, w: 2.6, h: 0.3, fontFace: BODY, fontSize: 11.5, bold: true, color: INK, margin: 0, valign: "middle" });
    s.addText(o[1], { x: px + 0.42, y: y + 0.39, w: 2.6, h: 0.28, fontFace: BODY, fontSize: 10.5, color: o[2] ? BLUE : MUTED, margin: 0, valign: "middle" });
  });
  s.addShape(pres.ShapeType.roundRect, { x: px + 0.25, y: py + 4.02, w: 2.9, h: 0.5, rectRadius: 0.25, fill: { color: NAVY }, line: { color: NAVY, width: 1 } });
  s.addText("Continue", { x: px + 0.25, y: py + 4.02, w: 2.9, h: 0.5, fontFace: BODY, fontSize: 13, bold: true, color: WHITE, align: "center", valign: "middle", margin: 0 });
  s.addNotes("Book a real serial on the doctor's own phone here. Thirty seconds. Let them hold the phone.");
}

/* ============ 7 — TICKET ============ */
{
  const s = light(WHITE);
  eyebrow(s, "Step 2 — the ticket");
  title(s, "They know exactly when to come");
  sub(s, "So they stop sitting in your waiting room for three hours.");

  // ticket mock
  const tx = M, ty = 2.35;
  s.addShape(pres.ShapeType.roundRect, { x: tx, y: ty, w: 4.6, h: 3.95, rectRadius: 0.18, fill: { color: WHITE }, line: { color: LINE, width: 1.5 }, shadow: soft() });
  s.addText("Rashed Ahmed  ·  Dr. Karim", { x: tx + 0.4, y: ty + 0.32, w: 3.8, h: 0.3, fontFace: BODY, fontSize: 12.5, color: MUTED, margin: 0, valign: "middle" });
  s.addText("YOUR SERIAL", { x: tx + 0.4, y: ty + 0.68, w: 3.8, h: 0.28, fontFace: BODY, fontSize: 11, bold: true, charSpacing: 2.2, color: BLUE, margin: 0, valign: "middle" });
  s.addText("24", { x: tx + 0.4, y: ty + 0.95, w: 3.8, h: 1.35, fontFace: HEAD, fontSize: 76, bold: true, color: NAVY, margin: 0, valign: "middle" });
  s.addShape(pres.ShapeType.rect, { x: tx + 0.4, y: ty + 2.36, w: 3.8, h: 0.014, fill: { color: LINE }, line: { color: LINE, width: 1 } });
  s.addText("Come around", { x: tx + 0.4, y: ty + 2.5, w: 1.9, h: 0.28, fontFace: BODY, fontSize: 11.5, color: MUTED, margin: 0, valign: "middle" });
  s.addText("7:15 PM", { x: tx + 0.4, y: ty + 2.76, w: 1.9, h: 0.42, fontFace: HEAD, fontSize: 22, bold: true, color: INK, margin: 0, valign: "middle" });
  s.addText("Now calling", { x: tx + 2.4, y: ty + 2.5, w: 1.8, h: 0.28, fontFace: BODY, fontSize: 11.5, color: MUTED, margin: 0, valign: "middle" });
  s.addText("12", { x: tx + 2.4, y: ty + 2.76, w: 1.8, h: 0.42, fontFace: HEAD, fontSize: 22, bold: true, color: MINT, margin: 0, valign: "middle" });
  s.addText("Updates by itself  ·  Share on WhatsApp  ·  Print or save as PDF", { x: tx + 0.4, y: ty + 3.32, w: 3.8, h: 0.4, fontFace: BODY, fontSize: 11, italic: true, color: MUTED, margin: 0, valign: "middle" });

  const pts = [
    ["The number moves while they watch", "The ticket refreshes on its own — they can see the queue from home or from the tea stall downstairs."],
    ["A time that protects you, not just them", "The “come around” time is deliberately padded, and the first few patients are told to come earlier. You are never left sitting in an empty chamber."],
    ["Directions to your chamber", "A Google Maps link to the exact room, on the same page."],
    ["Lost the link? Nothing breaks", "They type their phone number on your site and their booking comes back."],
  ];
  pts.forEach((p, i) => {
    const y = 2.4 + i * 1.06;
    s.addText(p[0], { x: 5.9, y, w: 6.75, h: 0.34, fontFace: HEAD, fontSize: 17, bold: true, color: INK, margin: 0, valign: "middle" });
    s.addText(p[1], { x: 5.9, y: y + 0.34, w: 6.75, h: 0.68, fontFace: BODY, fontSize: 13.5, color: MUTED, margin: 0, valign: "top", lineSpacing: 18 });
  });
  s.addNotes("Say plainly: we are not trying to empty your waiting room. An empty chamber costs you more than a full one. The timing is padded on purpose.");
}

/* ============ 8 — OUTDOOR SCREEN ============ */
{
  const s = dark();
  eyebrow(s, "Step 3 — the room", BLUE);
  title(s, "The screen outside settles every argument", { color: WHITE });
  sub(s, "Nobody argues with the television. That is the point.", { color: "9AA6CC" });

  const tvx = M, tvy = 2.4;
  s.addShape(pres.ShapeType.roundRect, { x: tvx, y: tvy, w: 6.0, h: 3.55, rectRadius: 0.16, fill: { color: "081041" }, line: { color: NAVY2, width: 2 }, shadow: lift() });
  s.addText("NOW SERVING", { x: tvx, y: tvy + 0.32, w: 6.0, h: 0.32, fontFace: BODY, fontSize: 13, bold: true, charSpacing: 3, color: BLUE, align: "center", margin: 0, valign: "middle" });
  s.addText("12", { x: tvx, y: tvy + 0.68, w: 6.0, h: 1.9, fontFace: HEAD, fontSize: 118, bold: true, color: WHITE, align: "center", margin: 0, valign: "middle" });
  s.addText("Chamber 1  ·  Dr. Karim  ·  Evening 5–9 PM", { x: tvx, y: tvy + 2.55, w: 6.0, h: 0.32, fontFace: BODY, fontSize: 13, color: "9AA6CC", align: "center", margin: 0, valign: "middle" });
  s.addText("Next   13   ·   14   ·   15", { x: tvx, y: tvy + 2.92, w: 6.0, h: 0.38, fontFace: HEAD, fontSize: 19, bold: true, color: MINT, align: "center", margin: 0, valign: "middle" });

  const rr = [
    ["It speaks the number out loud", "“Number twelve” — a clear recorded voice, not a robotic one. Patients at the back hear it without anyone shouting."],
    ["Any screen you already own", "A TV, an old monitor, a spare tablet. It just opens a web page."],
    ["Your own chime, if you prefer", "Chime only, voice only, or chime then voice — your choice."],
    ["Serials never get confused", "The session name and timing stay on screen, so a morning serial 12 is never mistaken for the evening's."],
  ];
  rr.forEach((r, i) => {
    const y = 2.42 + i * 1.02;
    dot(s, 7.1, y + 0.06, 0.3, BLUE);
    s.addText(r[0], { x: 7.58, y, w: 5.05, h: 0.34, fontFace: HEAD, fontSize: 16.5, bold: true, color: WHITE, margin: 0, valign: "middle" });
    s.addText(r[1], { x: 7.58, y: y + 0.34, w: 5.05, h: 0.66, fontFace: BODY, fontSize: 13, color: "9AA6CC", margin: 0, valign: "top", lineSpacing: 17 });
  });
  s.addNotes("DEMO MOMENT. Put this on the biggest screen in the room and have someone tap Call so it announces out loud. This sells the product more than any slide.");
}

/* ============ 9 — QUEUE CONTROL ============ */
{
  const s = light();
  eyebrow(s, "Step 3 — your side");
  title(s, "One screen runs the whole evening");
  sub(s, "Start the session. Call the next patient. Mark them arrived. Complete. Four buttons, all evening.");

  const acts = [
    ["Start", "Opens today's session. The screen outside wakes up.", NAVY],
    ["Call next", "The number flips and is announced. No shouting.", BLUE],
    ["Arrived", "Confirms the patient is actually at the door — so you never wait for someone who stepped out.", BLUE],
    ["Complete", "Sends them off and moves the queue on.", MINT],
  ];
  acts.forEach((a, i) => {
    const x = M + i * 3.06, w = 2.78;
    card(s, x, 2.5, w, 2.15);
    s.addShape(pres.ShapeType.roundRect, { x: x + 0.34, y: 2.8, w: 1.5, h: 0.44, rectRadius: 0.22, fill: { color: a[2] }, line: { color: a[2], width: 1 } });
    s.addText(a[0], { x: x + 0.34, y: 2.8, w: 1.5, h: 0.44, fontFace: BODY, fontSize: 12.5, bold: true, color: WHITE, align: "center", valign: "middle", margin: 0 });
    s.addText(a[1], { x: x + 0.34, y: 3.4, w: w - 0.68, h: 1.1, fontFace: BODY, fontSize: 13, color: MUTED, margin: 0, valign: "top", lineSpacing: 18 });
  });

  card(s, M, 4.95, CW, 1.5, NAVY);
  s.addText("You decide who drives it", { x: M + 0.45, y: 5.15, w: 5.2, h: 0.4, fontFace: HEAD, fontSize: 19, bold: true, color: WHITE, margin: 0, valign: "middle" });
  s.addText("Staff-run: your assistant calls patients and your screen simply follows along.\nDoctor-run: you control the queue yourself from the chamber. If you have no assistant at all, you are never locked out of your own room.", {
    x: M + 0.45, y: 5.55, w: 11.0, h: 0.75, fontFace: BODY, fontSize: 13.5, color: "C9D0E8", margin: 0, valign: "top", lineSpacing: 18,
  });
  foot(s, "Walk-in patients are added here in two taps — they take the next serial like everyone else.");
}

/* ============ 10 — CONSULT SCREEN ============ */
{
  const s = light(WHITE);
  eyebrow(s, "Step 4 — the chamber");
  title(s, "The right patient appears by himself");
  sub(s, "No searching, no typing a name, no file to pull. You call them in and the screen has already changed.");

  const cx = M, cy = 2.45;
  s.addShape(pres.ShapeType.roundRect, { x: cx, y: cy, w: 5.9, h: 3.85, rectRadius: 0.16, fill: { color: "F4F6FA" }, line: { color: LINE, width: 1.5 }, shadow: soft() });
  s.addText("Rashed Ahmed", { x: cx + 0.4, y: cy + 0.28, w: 3.5, h: 0.44, fontFace: HEAD, fontSize: 24, bold: true, color: INK, margin: 0, valign: "middle" });
  s.addText("Male · 46 yrs · 7th visit · last seen 12 Mar", { x: cx + 0.4, y: cy + 0.72, w: 5.1, h: 0.3, fontFace: BODY, fontSize: 12.5, color: MUTED, margin: 0, valign: "middle" });
  s.addShape(pres.ShapeType.roundRect, { x: cx + 0.4, y: cy + 1.12, w: 5.1, h: 0.5, rectRadius: 0.08, fill: { color: "FBEAE7" }, line: { color: CLAY, width: 1 } });
  s.addText("Allergy: Penicillin   ·   Known: Type 2 diabetes", { x: cx + 0.62, y: cy + 1.12, w: 4.8, h: 0.5, fontFace: BODY, fontSize: 12.5, bold: true, color: CLAY, margin: 0, valign: "middle" });
  s.addText("LAST VISIT", { x: cx + 0.4, y: cy + 1.78, w: 5.1, h: 0.28, fontFace: BODY, fontSize: 10.5, bold: true, charSpacing: 2, color: BLUE, margin: 0, valign: "middle" });
  s.addText("Diagnosis   Uncontrolled type 2 diabetes\nAdvice   Reduce rice at night; walk 30 min daily\nAlso on file   Voice note (14s)  ·  Prescription photo", {
    x: cx + 0.4, y: cy + 2.06, w: 5.1, h: 1.1, fontFace: BODY, fontSize: 13, color: INK, margin: 0, valign: "top", lineSpacing: 21,
  });
  s.addText("Reprint last prescription  ·  Play voice note", { x: cx + 0.4, y: cy + 3.25, w: 5.1, h: 0.32, fontFace: BODY, fontSize: 12, bold: true, color: BLUE, margin: 0, valign: "middle" });

  const rr = [
    ["Writing notes is never compulsory", "You can complete a patient without typing a single word. Blank is allowed, always."],
    ["Or speak instead of typing", "Record a 15-second voice note, or photograph your own paper prescription. Both count as the record."],
    ["Honest about what it doesn't know", "If there are no notes from before, it says so plainly — “5 previous visits · no notes recorded”. It never invents history."],
    ["A gentle nudge, not a lock", "At the end of a session it tells you how many patients have no notes yet — and lets you close anyway."],
  ];
  rr.forEach((r, i) => {
    const y = 2.5 + i * 1.02;
    s.addText(r[0], { x: 6.98, y, w: 5.65, h: 0.34, fontFace: HEAD, fontSize: 16.5, bold: true, color: INK, margin: 0, valign: "middle" });
    s.addText(r[1], { x: 6.98, y: y + 0.34, w: 5.65, h: 0.66, fontFace: BODY, fontSize: 13, color: MUTED, margin: 0, valign: "top", lineSpacing: 17 });
  });
  s.addNotes("Doctors fear data entry more than they fear cost. Say 'never compulsory' out loud, twice.");
}

/* ============ 11 — PRESCRIPTIONS & RECORDS ============ */
{
  const s = light();
  eyebrow(s, "Your records");
  title(s, "Prescriptions with your name on them");

  const cards = [
    ["Printed properly", "Your qualifications and BMDC registration number on every prescription. It warns you if the number is missing."],
    ["Reprint any time", "The patient lost it. You print it again from the last visit in one tap — no rewriting."],
    ["One person, one record", "Family members sharing one phone each keep their own history. Duplicates can be joined; a visit put on the wrong person can be moved."],
    ["Diagnoses that stay tidy", "A searchable list of conditions in English and Bangla that learns what you use most — or just type freely. Both are fine."],
  ];
  cards.forEach((c, i) => {
    const col = i % 2, row = Math.floor(i / 2);
    const x = M + col * 6.15, y = 2.35 + row * 2.05;
    card(s, x, y, 5.75, 1.78);
    dot(s, x + 0.38, y + 0.36, 0.5, NAVY, String(i + 1));
    s.addText(c[0], { x: x + 1.06, y: y + 0.3, w: 4.4, h: 0.4, fontFace: HEAD, fontSize: 18, bold: true, color: INK, margin: 0, valign: "middle" });
    s.addText(c[1], { x: x + 1.06, y: y + 0.72, w: 4.4, h: 0.9, fontFace: BODY, fontSize: 13.5, color: MUTED, margin: 0, valign: "top", lineSpacing: 18 });
  });
  foot(s, "Nothing here is required to run the queue. Use as much or as little of it as you want.");
}

/* ============ 12 — WEBSITE ============ */
{
  const s = light(WHITE);
  eyebrow(s, "Your presence");
  title(s, "A proper website, at your own name");
  sub(s, "Most established doctors in Bangladesh have no website at all — only a Facebook post someone else wrote.");

  const bx = M, by = 2.5;
  s.addShape(pres.ShapeType.roundRect, { x: bx, y: by, w: 6.2, h: 3.6, rectRadius: 0.14, fill: { color: "F4F6FA" }, line: { color: LINE, width: 1.5 }, shadow: soft() });
  s.addShape(pres.ShapeType.roundRect, { x: bx + 0.3, y: by + 0.28, w: 5.6, h: 0.4, rectRadius: 0.2, fill: { color: WHITE }, line: { color: LINE, width: 1 } });
  s.addText("drkarim.com", { x: bx + 0.55, y: by + 0.28, w: 3.0, h: 0.4, fontFace: BODY, fontSize: 12, color: MUTED, margin: 0, valign: "middle" });
  s.addText("Dr. Karim Uddin", { x: bx + 0.5, y: by + 0.95, w: 5.2, h: 0.55, fontFace: HEAD, fontSize: 30, bold: true, color: INK, margin: 0, valign: "middle" });
  s.addText("MBBS, FCPS (Medicine)  ·  Consultant Physician", { x: bx + 0.5, y: by + 1.5, w: 5.2, h: 0.32, fontFace: BODY, fontSize: 13, color: MUTED, margin: 0, valign: "middle" });
  s.addShape(pres.ShapeType.roundRect, { x: bx + 0.5, y: by + 2.0, w: 2.1, h: 0.52, rectRadius: 0.26, fill: { color: NAVY }, line: { color: NAVY, width: 1 } });
  s.addText("Book Appointment", { x: bx + 0.5, y: by + 2.0, w: 2.1, h: 0.52, fontFace: BODY, fontSize: 12, bold: true, color: WHITE, align: "center", valign: "middle", margin: 0 });
  s.addText("Chambers  ·  Timings  ·  Conditions I treat  ·  FAQ", { x: bx + 0.5, y: by + 2.75, w: 5.2, h: 0.32, fontFace: BODY, fontSize: 12.5, color: MUTED, margin: 0, valign: "middle" });

  const pts = [
    ["Your own domain, or ours", "drkarim.com if you want it — otherwise you are live on our address the same day."],
    ["Written for patients, not doctors", "Chamber address with a map, timings, conditions you treat, and the questions patients always ask."],
    ["Your staff can edit the words", "Change a timing or a photo without calling anyone."],
    ["Every page leads to Book", "That is the only job the site has."],
  ];
  pts.forEach((p, i) => {
    const y = 2.55 + i * 0.98;
    dot(s, 7.35, y + 0.05, 0.3, MINT, "✓");
    s.addText(p[0], { x: 7.83, y, w: 4.8, h: 0.34, fontFace: HEAD, fontSize: 16.5, bold: true, color: INK, margin: 0, valign: "middle" });
    s.addText(p[1], { x: 7.83, y: y + 0.34, w: 4.8, h: 0.62, fontFace: BODY, fontSize: 13, color: MUTED, margin: 0, valign: "top", lineSpacing: 17 });
  });
}

/* ============ 13 — WHEN YOU ARE AWAY ============ */
{
  const s = light();
  eyebrow(s, "The awkward days");
  title(s, "When you are away, nobody waits");

  const flow = [
    ["Block the date", "Choose the day. It tells you how many patients are affected before you confirm."],
    ["Serials cancelled", "Everyone with a serial that day is released automatically. Visits you already completed stay."],
    ["One tap each on WhatsApp", "A ready-written message per patient. You just press send."],
  ];
  flow.forEach((f, i) => {
    const x = M + i * 4.02, w = 3.72;
    card(s, x, 2.55, w, 2.7);
    dot(s, x + 0.38, 2.9, 0.6, CLAY, String(i + 1));
    s.addText(f[0], { x: x + 0.38, y: 3.62, w: w - 0.76, h: 0.78, fontFace: HEAD, fontSize: 19, bold: true, color: INK, margin: 0, valign: "middle" });
    s.addText(f[1], { x: x + 0.38, y: 4.44, w: w - 0.76, h: 0.82, fontFace: BODY, fontSize: 13.5, color: MUTED, margin: 0, valign: "top", lineSpacing: 18 });
  });

  card(s, M, 5.5, CW, 0.95, NAVY);
  s.addText("You can block one chamber, one doctor, or the whole day — a conference, a wedding, an emergency. It takes under a minute.", {
    x: M + 0.45, y: 5.5, w: CW - 0.9, h: 0.95, fontFace: BODY, fontSize: 14, color: "E4E8F0", margin: 0, valign: "middle",
  });
}

/* ============ 14 — PRIVACY ============ */
{
  const s = light(WHITE);
  eyebrow(s, "Confidentiality");
  title(s, "Who can see what — plainly");
  sub(s, "Ask us this question. We would rather answer it now than have you wonder later.");

  const cols = [
    ["The patient", MINT, ["Their own serial and timing", "Their ticket and chamber directions", "Their own past bookings"], "Never sees any clinical note.", false],
    ["Your staff", BLUE, ["The day's list and the queue", "Booking and contact details", "Your website text and photos"], "Cannot open a diagnosis, a prescription, or a voice note.", false],
    ["Only you, the doctor", NAVY, ["Diagnoses and advice", "Prescriptions and reprints", "Voice notes and photos"], "Clinical records are doctor-only, by design.", true],
  ];
  cols.forEach((c, i) => {
    const x = M + i * 4.02, w = 3.72;
    card(s, x, 2.45, w, 3.55, c[4] ? NAVY : WHITE);
    const fg = c[4] ? WHITE : INK;
    const mid = c[4] ? "C9D0E8" : MUTED;
    s.addShape(pres.ShapeType.roundRect, { x: x + 0.38, y: 2.78, w: 1.9, h: 0.42, rectRadius: 0.21, fill: { color: c[1] }, line: { color: c[1], width: 1 } });
    s.addText(c[0], { x: x + 0.38, y: 2.78, w: 1.9, h: 0.42, fontFace: BODY, fontSize: 12, bold: true, color: WHITE, align: "center", valign: "middle", margin: 0 });
    s.addText(
      c[2].map((b, j) => ({ text: b, options: { bullet: true, breakLine: j !== c[2].length - 1 } })),
      { x: x + 0.38, y: 3.38, w: w - 0.76, h: 1.5, fontFace: BODY, fontSize: 13.5, color: fg, margin: 0, valign: "top", paraSpaceAfter: 7 }
    );
    s.addText(c[3], { x: x + 0.38, y: 5.0, w: w - 0.76, h: 0.8, fontFace: BODY, fontSize: 12.5, italic: true, bold: true, color: c[4] ? MINT : mid, margin: 0, valign: "top", lineSpacing: 17 });
  });
  foot(s, "We keep anonymous disease counts across practices for research — never names, never numbers, never one patient's visit.", NAVY2);
  s.addNotes("Do not hide the research aggregation. Being straight about it builds more trust than dodging. Minimum group size is 10 — nothing can be traced back.");
}

/* ============ 15 — WHAT DOESN'T CHANGE ============ */
{
  const s = light();
  eyebrow(s, "Your worries, answered");
  title(s, "What does not change");

  const rows = [
    ["Your money", "Patients pay you at the chamber, in cash, exactly as today. There is no online payment and no commission on your fee."],
    ["Your walk-ins", "Patients who just turn up are still welcome. Staff put them into the same queue in two taps. Online booking is a bonus, not a requirement."],
    ["Your way of working", "Notes, prescriptions, voice — every one of them optional. You can use this purely as a serial and queue system and nothing will nag you."],
    ["Your effort to start", "We build your site, chambers, timings and logins with you. You do not sit at a computer to go live."],
  ];
  rows.forEach((r, i) => {
    const col = i % 2, row = Math.floor(i / 2);
    const x = M + col * 6.15, y = 2.35 + row * 2.05;
    card(s, x, y, 5.75, 1.78);
    dot(s, x + 0.38, y + 0.36, 0.5, MINT, "✓");
    s.addText(r[0], { x: x + 1.06, y: y + 0.3, w: 4.4, h: 0.4, fontFace: HEAD, fontSize: 19, bold: true, color: INK, margin: 0, valign: "middle" });
    s.addText(r[1], { x: x + 1.06, y: y + 0.72, w: 4.4, h: 0.92, fontFace: BODY, fontSize: 13.5, color: MUTED, margin: 0, valign: "top", lineSpacing: 18 });
  });
  foot(s, "One honest caveat: the live queue needs internet in the chamber. On a bad day you fall back to paper — the same paper you use now.", NAVY2);
  s.addNotes("Volunteer the internet caveat before they ask. Doctors trust a salesperson who names a limitation first.");
}

/* ============ 16 — PRICING ============ */
{
  const s = light(WHITE);
  eyebrow(s, "Investment");
  title(s, "Two plans. No hidden charges.");

  const plan = (x, name, tag, setup, monthly, feats, featured) => {
    card(s, x, 2.35, 5.75, 3.35, featured ? NAVY : WHITE);
    const fg = featured ? WHITE : INK;
    const mid = featured ? "C9D0E8" : MUTED;
    s.addText(name, { x: x + 0.45, y: 2.62, w: 3.0, h: 0.45, fontFace: HEAD, fontSize: 26, bold: true, color: fg, margin: 0, valign: "middle" });
    s.addText(tag, { x: x + 0.45, y: 3.06, w: 4.9, h: 0.3, fontFace: BODY, fontSize: 12.5, color: mid, margin: 0, valign: "middle" });
    s.addText(setup, { x: x + 0.45, y: 3.48, w: 2.5, h: 0.5, fontFace: HEAD, fontSize: 25, bold: true, color: featured ? MINT : NAVY, margin: 0, valign: "middle" });
    s.addText("one-time setup", { x: x + 0.45, y: 3.96, w: 2.5, h: 0.28, fontFace: BODY, fontSize: 11.5, color: mid, margin: 0, valign: "middle" });
    s.addText(monthly, { x: x + 3.1, y: 3.48, w: 2.4, h: 0.5, fontFace: HEAD, fontSize: 25, bold: true, color: featured ? MINT : NAVY, margin: 0, valign: "middle" });
    s.addText("per month", { x: x + 3.1, y: 3.96, w: 2.4, h: 0.28, fontFace: BODY, fontSize: 11.5, color: mid, margin: 0, valign: "middle" });
    s.addText(
      feats.map((f, j) => ({ text: f, options: { bullet: true, breakLine: j !== feats.length - 1 } })),
      { x: x + 0.45, y: 4.4, w: 4.9, h: 1.15, fontFace: BODY, fontSize: 13, color: featured ? "E4E8F0" : INK, margin: 0, valign: "top", paraSpaceAfter: 5 }
    );
    if (featured) {
      s.addShape(pres.ShapeType.roundRect, { x: x + 4.05, y: 2.62, w: 1.3, h: 0.36, rectRadius: 0.18, fill: { color: MINT }, line: { color: MINT, width: 1 } });
      s.addText("MOST TAKEN", { x: x + 4.05, y: 2.62, w: 1.3, h: 0.36, fontFace: BODY, fontSize: 9, bold: true, charSpacing: 0.8, color: NAVY, align: "center", valign: "middle", margin: 0 });
    }
  };
  plan(M, "Solo", "One doctor, up to 5 chambers", "Tk 15,000", "Tk 3,000", [
    "Your branded site and booking page",
    "Live queue, outdoor screen, patient tickets",
    "Patient records and prescriptions",
    "We set it all up with you",
  ], true);
  plan(M + 6.15, "Clinic", "Multiple doctors, chambers and labs", "Tk 75,000", "Tk 7,500", [
    "Everything in Solo, for a full clinic",
    "Multiple doctors and unlimited chambers",
    "Lab tests inside the booking flow",
    "We set it all up with you",
  ], false);

  card(s, M, 5.92, CW, 0.82, "F4F6FA");
  s.addText("SMS confirmations are prepaid and optional — about Tk 0.50 each (200 for Tk 100, 500 for Tk 225, 2,000 for Tk 800).   Pay by bKash or bank.   WhatsApp ticket sharing is free.", {
    x: M + 0.4, y: 5.92, w: CW - 0.8, h: 0.82, fontFace: BODY, fontSize: 12.5, color: INK, margin: 0, valign: "middle",
  });
  s.addNotes("Reframe, do not defend: Tk 3,000 a month is roughly a few consult fees. Ask how many patients leave on a busy evening. Anchor with Clinic so Solo feels modest.");
}

/* ============ 17 — CLOSE ============ */
{
  const s = dark();
  s.addShape(pres.ShapeType.ellipse, { x: -1.9, y: 3.6, w: 6.2, h: 6.2, fill: { color: NAVY2 }, line: { color: NAVY2, width: 1 } });
  eyebrow(s, "Next step", BLUE, M, 1.3);
  s.addText("Give me three things today.", {
    x: M, y: 1.68, w: 8.1, h: 0.95, fontFace: HEAD, fontSize: 38, bold: true, color: WHITE, margin: 0, valign: "middle",
  });
  const three = [
    ["1", "Your chamber name and address"],
    ["2", "Your sitting days and timings"],
    ["3", "One photograph of yourself"],
  ];
  three.forEach((t, i) => {
    const y = 2.95 + i * 0.78;
    dot(s, M, y + 0.02, 0.46, BLUE, t[0]);
    s.addText(t[1], { x: M + 0.68, y, w: 6.5, h: 0.5, fontFace: BODY, fontSize: 17, color: WHITE, margin: 0, valign: "middle" });
  });
  s.addText("Your site will be live at your name by tomorrow.\nTest it with ten patients before you pay anything.", {
    x: M, y: 5.35, w: 8.1, h: 1.2, fontFace: HEAD, fontSize: 21, color: MINT, margin: 0, valign: "top", lineSpacing: 30,
  });

  const bx = 9.0;
  s.addShape(pres.ShapeType.roundRect, { x: bx, y: 2.35, w: 3.6, h: 2.9, rectRadius: 0.16, fill: { color: "081041" }, line: { color: NAVY2, width: 1.5 }, shadow: lift() });
  s.addText("ChamberQ", { x: bx, y: 2.7, w: 3.6, h: 0.45, fontFace: HEAD, fontSize: 22, bold: true, color: WHITE, align: "center", margin: 0, valign: "middle" });
  s.addText("WhatsApp", { x: bx, y: 3.3, w: 3.6, h: 0.3, fontFace: BODY, fontSize: 11.5, bold: true, charSpacing: 2, color: BLUE, align: "center", margin: 0, valign: "middle" });
  s.addText("01700-000000", { x: bx, y: 3.6, w: 3.6, h: 0.5, fontFace: HEAD, fontSize: 24, bold: true, color: MINT, align: "center", margin: 0, valign: "middle" });
  s.addText("Replace with your number\nbefore the meeting", { x: bx, y: 4.25, w: 3.6, h: 0.7, fontFace: BODY, fontSize: 11, italic: true, color: "6B7399", align: "center", margin: 0, valign: "top" });
  s.addNotes("Do not ask for a signature. Ask for the three items. A doctor who sees their own name on a live site closes themselves.");
}

pres.writeFile({ fileName: "/tmp/dgdeck/ChamberQ-Client-Pitch.pptx" }).then((f) => console.log("written:", f));
