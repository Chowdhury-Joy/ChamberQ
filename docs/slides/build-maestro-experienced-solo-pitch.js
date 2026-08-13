/**
 * ChamberQ Maestro — slide deck for experienced solo doctors
 * Visual language matches docs/proposals/* (teal cover, Bebas Neue + Helvetica Neue).
 * Audience: busy one-doctor chambers (room already full). Plan name: Maestro.
 *
 * Run: node docs/slides/build-maestro-experienced-solo-pitch.js
 */
import pptxgenjs from "pptxgenjs";
import { withPPTXEmbedFonts } from "pptx-embed-fonts/pptxgenjs";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { createRequire } from "module";

const require = createRequire(import.meta.url);
const JSZip = require("jszip");

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pptxgen = withPPTXEmbedFonts(pptxgenjs);

/* —— Proposal palette (from Dr-* ChamberQ proposals) —— */
const COVER = "0C3A3B";
const COVER2 = "0F4F4A";
const INK = "0F3D3E";
const ACCENT = "0F766E";
const BODY = "1A2332";
const MUTED = "6B7280";
const LINE = "E5E7EB";
const CARD = "F4F7F7";
const MINT = "5EEAD4";
const WHITE = "FFFFFF";

// Match proposal HTML: Bebas Neue (uppercase + tight tracking) + Helvetica Neue body.
// Bebas is Regular-only — never faux-bold it.
const HEAD = "Bebas Neue";
const BODY_F = "Helvetica Neue";

/** Proposal CSS: text-transform:uppercase; letter-spacing:-0.02em on Bebas titles. */
function U(t) {
  return String(t).toUpperCase();
}

const W = 13.333;
const M = 0.7;
const CW = W - M * 2;

const pres = new pptxgen();
pres.layout = "LAYOUT_WIDE"; // 13.333 × 7.5
pres.author = "Joy Chowdhury, ChamberQ";
pres.title = "ChamberQ Maestro — For Experienced Solo Doctors";
pres.subject = "Product presentation";
// Prefer our faces when Keynote/PowerPoint substitute theme fonts.
pres.theme = {
  headFontFace: HEAD,
  bodyFontFace: BODY_F,
};
const soft = () => ({
  type: "outer",
  angle: 90,
  blur: 14,
  offset: 3,
  color: "0F3D3E",
  opacity: 0.12,
});
const lift = () => ({
  type: "outer",
  angle: 90,
  blur: 22,
  offset: 6,
  color: "0C3A3B",
  opacity: 0.28,
});

function coverSlide() {
  const s = pres.addSlide();
  s.background = { color: COVER };
  return s;
}
function light(bg) {
  const s = pres.addSlide();
  s.background = { color: bg || WHITE };
  return s;
}
function eyebrow(s, t, color, x, y) {
  s.addText(String(t).toUpperCase(), {
    x: x === undefined ? M : x,
    y: y === undefined ? 0.48 : y,
    w: CW,
    h: 0.28,
    fontFace: BODY_F,
    fontSize: 11,
    bold: true,
    charSpacing: 2.4,
    color: color || ACCENT,
    margin: 0,
    valign: "middle",
  });
}
function title(s, t, opts) {
  const o = opts || {};
  s.addText(U(t), {
    x: o.x === undefined ? M : o.x,
    y: o.y === undefined ? 0.82 : o.y,
    w: o.w === undefined ? CW : o.w,
    h: o.h === undefined ? 1.15 : o.h,
    fontFace: HEAD,
    fontSize: o.size || 40,
    // Bebas Neue — Regular only; match proposal letter-spacing (-0.02em ≈ −0.8 at 40pt)
    charSpacing: o.charSpacing !== undefined ? o.charSpacing : -0.8,
    color: o.color || INK,
    margin: 0,
    valign: o.valign || "middle",
  });
}
/** Secondary Bebas labels (cards, list heads) — always uppercase like proposal h2/h3. */
function head(s, t, opts) {
  const o = opts || {};
  s.addText(U(t), {
    x: o.x,
    y: o.y,
    w: o.w,
    h: o.h,
    fontFace: HEAD,
    fontSize: o.size || 22,
    charSpacing: o.charSpacing !== undefined ? o.charSpacing : -0.5,
    color: o.color || INK,
    align: o.align,
    margin: 0,
    valign: o.valign || "middle",
  });
}
function sub(s, t, opts) {
  const o = opts || {};
  s.addText(t, {
    x: o.x === undefined ? M : o.x,
    y: o.y === undefined ? 1.78 : o.y,
    w: o.w === undefined ? CW - 0.8 : o.w,
    h: o.h === undefined ? 0.55 : o.h,
    fontFace: BODY_F,
    fontSize: o.size || 15,
    color: o.color || MUTED,
    margin: 0,
    valign: "top",
  });
}
function card(s, x, y, w, h, fill) {
  // Section 4 / proposal “shared” card: soft teal-gray, mint-edge border, light shadow.
  // Dark cover panels pass an explicit fill (e.g. investment / WhatsApp close).
  const bg = fill || CARD;
  const isDark = bg === "0A2F30" || bg === COVER || bg === COVER2;
  s.addShape(pres.ShapeType.roundRect, {
    x,
    y,
    w,
    h,
    rectRadius: 0.1,
    fill: { color: bg },
    line: { color: isDark ? bg : "D7E3E2", width: 1 },
    shadow: soft(),
  });
}
function dot(s, x, y, d, fill, label, labelColor) {
  s.addShape(pres.ShapeType.ellipse, {
    x,
    y,
    w: d,
    h: d,
    fill: { color: fill },
    line: { color: fill, width: 1 },
  });
  if (label !== undefined) {
    s.addText(String(label), {
      x,
      y,
      w: d,
      h: d,
      fontFace: BODY_F,
      fontSize: d > 0.5 ? 14 : 11,
      bold: true,
      color: labelColor || WHITE,
      align: "center",
      valign: "middle",
      margin: 0,
    });
  }
}
function foot(s, t, color) {
  s.addText(t, {
    x: M,
    y: 6.95,
    w: CW,
    h: 0.35,
    fontFace: BODY_F,
    fontSize: 12,
    italic: true,
    color: color || MUTED,
    margin: 0,
    valign: "middle",
  });
}
function mintRule(s, x, y, w) {
  s.addShape(pres.ShapeType.rect, {
    x,
    y,
    w: w || 0.55,
    h: 0.035,
    fill: { color: MINT },
    line: { color: MINT, width: 1 },
  });
}

/* ============ 1 — COVER ============ */
{
  const s = coverSlide();
  s.addShape(pres.ShapeType.ellipse, {
    x: 9.2,
    y: -2.4,
    w: 7.2,
    h: 7.2,
    fill: { color: COVER2 },
    line: { color: COVER2, width: 1 },
  });

  head(s, "MAESTRO", {
    x: M,
    y: 1.05,
    w: 7.5,
    h: 0.55,
    size: 32,
    charSpacing: 4,
    color: WHITE,
    valign: "middle",
  });
  s.addText("For the experienced solo doctor", {
    x: M,
    y: 1.6,
    w: 7.5,
    h: 0.4,
    fontFace: BODY_F,
    fontSize: 15,
    color: "C5E8E4",
    margin: 0,
    valign: "middle",
  });

  mintRule(s, M, 2.35, 0.55);

  head(s, "Product\nPresentation", {
    x: M,
    y: 2.55,
    w: 8,
    h: 1.7,
    size: 56,
    color: WHITE,
    charSpacing: -1.1,
    valign: "top",
  });
  s.addText(
    "Your chamber is already full. Maestro keeps serials, the waiting room, and the consult under one calm system — under your name.",
    {
      x: M,
      y: 4.5,
      w: 7.2,
      h: 0.9,
      fontFace: BODY_F,
      fontSize: 15,
      color: "D7EDEC",
      margin: 0,
      valign: "top",
    }
  );

  s.addText("ChamberQ  ·  Joy Chowdhury  ·  WhatsApp 01818-614349  ·  August 2026", {
    x: M,
    y: 6.75,
    w: CW,
    h: 0.35,
    fontFace: BODY_F,
    fontSize: 13,
    color: "A8D4CF",
    margin: 0,
    valign: "middle",
  });
  s.addNotes(
    "Experienced solo audience: do not open with 'fill your room'. Lead with order, reputation, and time back in the consult."
  );
}

/* ============ 2 — THIS IS FOR YOU ============ */
{
  const s = light();
  eyebrow(s, "Who this is for");
  title(s, "You're already busy.\nThis keeps you organized.", { h: 1.7, size: 38 });
  sub(s, "Not a starter kit for empty chairs. A system for a doctor whose sittings already fill — and whose desk still runs on phone serials.", {
    y: 2.7,
    h: 0.7,
  });

  const pills = [
    ["One doctor", "Your practice, your name"],
    ["Up to 5 chambers", "Different days, one system"],
    ["Outdoor TV", "One board per sitting"],
    ["Done with you", "We set it up — not empty software"],
  ];
  pills.forEach((p, i) => {
    const x = M + (i % 4) * 3.05;
    const y = 4.0;
    card(s, x, y, 2.9, 2.15);
    dot(s, x + 0.25, y + 0.35, 0.42, MINT, "✓", INK);
    head(s, p[0], {
      x: x + 0.25,
      y: y + 0.9,
      w: 2.4,
      h: 0.45,
      size: 20,
      color: INK,
    });
    s.addText(p[1], {
      x: x + 0.25,
      y: y + 1.4,
      w: 2.4,
      h: 0.5,
      fontFace: BODY_F,
      fontSize: 13,
      color: MUTED,
      margin: 0,
      valign: "top",
    });
  });
}

/* ============ 3 — THE FRICTION ============ */
{
  const s = light();
  eyebrow(s, "The friction");
  title(s, "The day still costs you energy");
  sub(s, "Your consults are full. The chaos is outside the room — and it leaks into your time.");

  const items = [
    [
      "1",
      "Phone serials never stop",
      "Families still call for numbers while you are with a patient. Every ring is a consult interrupted.",
    ],
    [
      "2",
      "The hall gets restless",
      "Everyone asks “when will it be my turn?” Your desk spends the evening calming the queue instead of helping you.",
    ],
    [
      "3",
      "Two chambers, one head",
      "Morning at one hospital, evening at another — sittings mix, boards mix, and you lose minutes answering “who is next?”",
    ],
  ];
  items.forEach((it, i) => {
    const x = M + i * 4.02;
    const w = 3.72;
    card(s, x, 2.55, w, 3.55);
    dot(s, x + 0.35, 2.9, 0.5, MINT, it[0], INK);
    head(s, it[1], {
      x: x + 0.35,
      y: 3.6,
      w: w - 0.7,
      h: 0.95,
      size: 22,
      color: INK,
      valign: "top",
    });
    s.addText(it[2], {
      x: x + 0.35,
      y: 4.65,
      w: w - 0.7,
      h: 1.15,
      fontFace: BODY_F,
      fontSize: 13.5,
      color: MUTED,
      margin: 0,
      valign: "top",
    });
  });
  foot(s, "Ask which one bothers them most. Build the rest of the meeting around that.");
}

/* ============ 4 — DIGITAL FRONT DESK ============ */
{
  const s = light();
  eyebrow(s, "Section 1");
  title(s, "Your digital front desk");
  sub(s, "We set ChamberQ up as your front door — so someone searching for you lands somewhere that feels like you.");

  const blocks = [
    ["1", "Portfolio website", "Credentials, chambers, FAQ, Book — under your name, not a template hospital."],
    ["2", "Booking on the phone", "Patients take a serial for the sitting they need. No app. No password."],
    ["3", "A ticket", "Save, print, or forward on WhatsApp. Shows roughly when to come."],
    ["4", "Waiting-room screens", "One board per sitting. You or your assistant control the call."],
    ["5", "Consult screen", "Notes, medicines, follow-up — clinical side of your day, private to you."],
  ];
  blocks.forEach((b, i) => {
    const y = 2.45 + i * 0.82;
    head(s, b[0], {
      x: M,
      y,
      w: 0.45,
      h: 0.55,
      size: 22,
      color: ACCENT,
    });
    head(s, b[1], {
      x: M + 0.55,
      y,
      w: 3.6,
      h: 0.55,
      size: 22,
      color: INK,
    });
    s.addText(b[2], {
      x: M + 4.3,
      y,
      w: 7.5,
      h: 0.55,
      fontFace: BODY_F,
      fontSize: 14.5,
      color: MUTED,
      margin: 0,
      valign: "middle",
    });
    if (i < blocks.length - 1) {
      s.addShape(pres.ShapeType.rect, {
        x: M + 0.55,
        y: y + 0.7,
        w: CW - 0.55,
        h: 0.012,
        fill: { color: LINE },
        line: { color: LINE, width: 1 },
      });
    }
  });
}

/* ============ 5 — A DAY WITH PATIENTS ============ */
{
  const s = light();
  eyebrow(s, "Section 2");
  title(s, "A day with your patients");
  sub(s, "One family books. The same calm across every sitting you run.");

  const steps = [
    ["1", "Your page", "They see your specialty and chambers — and recognise the hospital they already know."],
    ["2", "Book a sitting", "Day, session, phone. If someone else already booked on that number, they pick who this visit is for."],
    ["3", "Serial ticket", "No app. Reopen anytime, or type the phone into your portal."],
    ["4", "Waiting room", "When their number is called, the screen updates — and can chime or announce."],
    ["5", "Consult", "You see them. Notes stay with you. Then the next serial when you are free."],
    ["6", "Rx home", "Print or WhatsApp a clean slip — medicines and follow-up, not the full file."],
  ];
  steps.forEach((st, i) => {
    const col = i % 3;
    const row = Math.floor(i / 3);
    const x = M + col * 4.05;
    const y = 2.5 + row * 2.15;
    card(s, x, y, 3.85, 1.95);
    dot(s, x + 0.28, y + 0.32, 0.48, MINT, st[0], INK);
    head(s, st[1], {
      x: x + 0.95,
      y: y + 0.32,
      w: 2.6,
      h: 0.48,
      size: 20,
      color: INK,
      valign: "middle",
    });
    s.addText(st[2], {
      x: x + 0.28,
      y: y + 1.0,
      w: 3.3,
      h: 0.75,
      fontFace: BODY_F,
      fontSize: 12.5,
      color: MUTED,
      margin: 0,
      valign: "top",
    });
  });
}

/* ============ 6 — DESK + CONSULT ============ */
{
  const s = light();
  eyebrow(s, "Section 3");
  title(s, "Your desk · your consult room");
  sub(s, "One Live Queue across your sittings. Inside the room, only your patients and your notes.");

  card(s, M, 2.45, 5.85, 4.15);
  s.addText("ACROSS YOUR PRACTICE", {
    x: M + 0.4,
    y: 2.7,
    w: 5.0,
    h: 0.35,
    fontFace: BODY_F,
    fontSize: 11,
    bold: true,
    charSpacing: 1.8,
    color: ACCENT,
    margin: 0,
    valign: "middle",
  });
  const left = [
    "One Live Queue Control for every sitting",
    "Walk-ins onto the right sitting",
    "Outdoor TVs — one link per sitting; boards never mix",
    "One portfolio website under your name",
    "Closed days & operational reports",
  ];
  left.forEach((t, i) => {
    const y = 3.2 + i * 0.55;
    dot(s, M + 0.4, y + 0.1, 0.28, MINT, "✓", INK);
    s.addText(t, {
      x: M + 0.85,
      y,
      w: 4.7,
      h: 0.5,
      fontFace: BODY_F,
      fontSize: 14,
      color: BODY,
      margin: 0,
      valign: "middle",
    });
  });

  card(s, M + 6.15, 2.45, 5.85, 4.15);
  s.addText("PERSONAL TO YOUR CONSULT", {
    x: M + 6.55,
    y: 2.7,
    w: 5.0,
    h: 0.35,
    fontFace: BODY_F,
    fontSize: 11,
    bold: true,
    charSpacing: 1.8,
    color: ACCENT,
    margin: 0,
    valign: "middle",
  });
  const right = [
    "Your sittings, serials, and TV",
    "Consult Screen — who is with you now",
    "Notes & history stay with you",
    "Prescriptions under your name & registration",
    "Late / day off affects only your list",
  ];
  right.forEach((t, i) => {
    const y = 3.2 + i * 0.55;
    dot(s, M + 6.55, y + 0.1, 0.28, MINT, "✓", INK);
    s.addText(t, {
      x: M + 7.0,
      y,
      w: 4.7,
      h: 0.5,
      fontFace: BODY_F,
      fontSize: 14,
      color: BODY,
      margin: 0,
      valign: "middle",
    });
  });
}

/* ============ 7 — BUSY DAY FLOW ============ */
{
  const s = light();
  eyebrow(s, "On a busy day");
  title(s, "Five taps. Then the next person.");
  sub(s, "Same doctor. Separate sittings. One calm board for each.");

  const flow = [
    ["1", "Start", "Open the sitting"],
    ["2", "Call", "Next serial"],
    ["3", "Arrived", "Patient is in"],
    ["4", "Complete", "Consult done"],
    ["5", "Next", "Or switch sitting"],
  ];
  flow.forEach((f, i) => {
    const x = M + i * 2.45;
    card(s, x, 2.55, 2.25, 2.35);
    dot(s, x + 0.8, 2.85, 0.55, MINT, f[0], INK);
    head(s, f[1], {
      x: x + 0.15,
      y: 3.6,
      w: 1.95,
      h: 0.45,
      size: 20,
      color: INK,
      align: "center",
      valign: "middle",
    });
    s.addText(f[2], {
      x: x + 0.15,
      y: 4.15,
      w: 1.95,
      h: 0.45,
      fontFace: BODY_F,
      fontSize: 13,
      color: MUTED,
      align: "center",
      margin: 0,
      valign: "middle",
    });
    if (i < flow.length - 1) {
      s.addText("›", {
        x: x + 2.05,
        y: 3.4,
        w: 0.4,
        h: 0.5,
        fontFace: BODY_F,
        fontSize: 24,
        color: ACCENT,
        align: "center",
        margin: 0,
      });
    }
  });

  card(s, M, 5.2, CW, 1.35);
  head(s, "Inside the chamber", {
    x: M + 0.4,
    y: 5.4,
    w: 3.2,
    h: 0.35,
    size: 18,
    color: INK,
    valign: "middle",
  });
  s.addText(
    "See the patient and past visits → write notes, medicines, follow-up → complete → print or WhatsApp the Rx → call the next when you are free. Husband and wife on one phone hold two tickets without mixing queues.",
    {
      x: M + 0.4,
      y: 5.8,
      w: CW - 0.8,
      h: 0.55,
      fontFace: BODY_F,
      fontSize: 13.5,
      color: MUTED,
      margin: 0,
      valign: "top",
    }
  );
}

/* ============ 8 — PRESCRIPTION ============ */
{
  const s = light();
  eyebrow(s, "Section 4");
  title(s, "Sending the prescription home");
  sub(s, "After consult, the family needs a clear slip — not your full clinical file.");

  const pts = [
    ["Follow-up you choose", "“1 week”, “as needed”, or a date — on the slip they keep."],
    ["Medicines from your list", "Searchable catalogue, including “same as last visit”."],
    ["Print a clean copy", "Looks like you, not a hospital printout from 2009."],
    ["WhatsApp or SMS link", "Medicines, advice, follow-up, vitals if recorded — diagnosis stays with you."],
  ];
  pts.forEach((p, i) => {
    const col = i % 2;
    const row = Math.floor(i / 2);
    const x = M + col * 6.15;
    const y = 2.55 + row * 2.0;
    card(s, x, y, 5.85, 1.8);
    dot(s, x + 0.35, y + 0.45, 0.5, MINT, "✓", INK);
    head(s, p[0], {
      x: x + 1.1,
      y: y + 0.35,
      w: 4.4,
      h: 0.5,
      size: 20,
      color: INK,
      valign: "middle",
    });
    s.addText(p[1], {
      x: x + 1.1,
      y: y + 0.95,
      w: 4.4,
      h: 0.55,
      fontFace: BODY_F,
      fontSize: 14,
      color: MUTED,
      margin: 0,
      valign: "top",
    });
  });
}

/* ============ 9 — WHAT WE BUILD ============ */
{
  const s = light();
  eyebrow(s, "Section 5");
  title(s, "What we build with you");
  sub(s, "Go-live = your numbers, rooms, sittings. Not a blank dashboard.");

  const items = [
    ["Chambers", "Up to five locations on Maestro — the hospitals and rooms you already sit."],
    ["Sittings", "Days, times, and daily caps you choose — morning, evening, second chamber."],
    ["Desk & TVs", "Live Queue Control; one TV bookmark per sitting so boards never mix."],
    ["Portfolio site", "Chambers, FAQ, Book — in your voice."],
    ["Messages", "SMS confirmations and WhatsApp Rx links — your choice."],
    ["Login", "Your account; optional assistant without full clinical notes."],
  ];
  items.forEach((it, i) => {
    const col = i % 3;
    const row = Math.floor(i / 3);
    const x = M + col * 4.05;
    const y = 2.55 + row * 2.15;
    card(s, x, y, 3.85, 1.95);
    dot(s, x + 0.35, y + 0.4, 0.42, MINT, "✓", INK);
    head(s, it[0], {
      x: x + 0.95,
      y: y + 0.35,
      w: 2.55,
      h: 0.5,
      size: 20,
      color: INK,
      valign: "middle",
    });
    s.addText(it[1], {
      x: x + 0.35,
      y: y + 1.0,
      w: 3.15,
      h: 0.7,
      fontFace: BODY_F,
      fontSize: 13.5,
      color: MUTED,
      margin: 0,
      valign: "top",
    });
  });
}

/* ============ 10 — KEPT SIMPLE ============ */
{
  const s = light();
  eyebrow(s, "Section 6");
  title(s, "Kept simple on purpose");

  const rows = [
    [
      "Full experience without sign-up",
      "Patients book, follow the ticket, check by phone, and receive prescription links — no patient accounts to manage.",
    ],
    [
      "Soft launch beside phone serial",
      "Keep your hotline while online booking grows. No big-bang day required.",
    ],
    [
      "Pay at the chamber",
      "Patients pay you in cash exactly as today. No online payment on their booking.",
    ],
    [
      "New features roll out automatically",
      "You do not reinstall. We improve the product; your chamber stays live.",
    ],
  ];
  rows.forEach((r, i) => {
    const y = 2.35 + i * 1.05;
    card(s, M, y, CW, 0.95);
    dot(s, M + 0.35, y + 0.28, 0.4, MINT, "✓", INK);
    head(s, r[0], {
      x: M + 0.95,
      y: y + 0.12,
      w: CW - 1.4,
      h: 0.32,
      size: 18,
      color: INK,
      valign: "middle",
    });
    s.addText(r[1], {
      x: M + 0.95,
      y: y + 0.48,
      w: CW - 1.4,
      h: 0.35,
      fontFace: BODY_F,
      fontSize: 13.5,
      color: MUTED,
      margin: 0,
      valign: "middle",
    });
  });
}

/* ============ 11 — INVESTMENT ============ */
{
  const s = coverSlide();
  s.addShape(pres.ShapeType.ellipse, {
    x: -2.2,
    y: 3.2,
    w: 6.5,
    h: 6.5,
    fill: { color: COVER2 },
    line: { color: COVER2, width: 1 },
  });

  eyebrow(s, "Investment", MINT, M, 0.7);
  head(s, "Maestro", {
    x: M,
    y: 1.15,
    w: 8,
    h: 0.7,
    size: 44,
    color: WHITE,
    valign: "middle",
  });
  s.addText("One doctor · up to 5 chambers · outdoor TV", {
    x: M,
    y: 1.85,
    w: 8,
    h: 0.4,
    fontFace: BODY_F,
    fontSize: 16,
    color: "A8D4CF",
    margin: 0,
    valign: "middle",
  });

  card(s, M, 2.55, 5.5, 3.5, "0A2F30");
  head(s, "৳15,000", {
    x: M + 0.45,
    y: 2.9,
    w: 4.6,
    h: 0.7,
    size: 44,
    color: MINT,
    valign: "middle",
  });
  s.addText("one-time setup", {
    x: M + 0.45,
    y: 3.55,
    w: 4.6,
    h: 0.35,
    fontFace: BODY_F,
    fontSize: 14,
    color: "A8D4CF",
    margin: 0,
    valign: "middle",
  });
  head(s, "৳3,000", {
    x: M + 0.45,
    y: 4.15,
    w: 4.6,
    h: 0.7,
    size: 44,
    color: MINT,
    valign: "middle",
  });
  s.addText("per month", {
    x: M + 0.45,
    y: 4.8,
    w: 4.6,
    h: 0.35,
    fontFace: BODY_F,
    fontSize: 14,
    color: "A8D4CF",
    margin: 0,
    valign: "middle",
  });
  s.addText("We set everything up with you. No technical team needed on your side.", {
    x: M + 0.45,
    y: 5.3,
    w: 4.6,
    h: 0.5,
    fontFace: BODY_F,
    fontSize: 13,
    color: "D7EDEC",
    margin: 0,
    valign: "top",
  });

  const feats = [
    "Maestro account, chambers, sittings, login",
    "Portfolio website in your voice",
    "Live queue, tickets, outdoor screen, consult",
    "Prepaid SMS when you want it",
    "Walkthrough of a real queue-and-consult day",
  ];
  feats.forEach((f, i) => {
    s.addText("✓  " + f, {
      x: 7.0,
      y: 2.7 + i * 0.55,
      w: 5.5,
      h: 0.5,
      fontFace: BODY_F,
      fontSize: 15,
      color: WHITE,
      margin: 0,
      valign: "middle",
    });
  });
  s.addText("SMS optional · ~৳0.50/confirmation · empty wallet skips SMS, booking still works · bill by bKash / bank", {
    x: M,
    y: 6.7,
    w: CW,
    h: 0.4,
    fontFace: BODY_F,
    fontSize: 12,
    italic: true,
    color: "8BBDB8",
    margin: 0,
    valign: "middle",
  });
  s.addNotes("Feature Maestro only for this audience. Rising Star is a smaller lane — do not upsell down unless they ask.");
}

/* ============ 12 — GO LIVE ============ */
{
  const s = light();
  eyebrow(s, "How we go live");
  title(s, "Five steps. Soft launch.");

  const steps = [
    ["1", "You tell us", "Which chambers and sittings start first"],
    ["2", "We build", "Site, schedules, TV links, login"],
    ["3", "Short training", "Queue board and consult / prescription"],
    ["4", "Soft launch", "Beside phone booking — no big-bang day"],
    ["5", "Everyday", "Clearer serials at every sitting you run"],
  ];
  steps.forEach((st, i) => {
    const y = 2.3 + i * 0.85;
    dot(s, M, y + 0.05, 0.55, MINT, st[0], INK);
    head(s, st[1], {
      x: M + 0.85,
      y,
      w: 3.2,
      h: 0.65,
      size: 22,
      color: INK,
      valign: "middle",
    });
    s.addText(st[2], {
      x: M + 4.2,
      y,
      w: 8,
      h: 0.65,
      fontFace: BODY_F,
      fontSize: 16,
      color: MUTED,
      margin: 0,
      valign: "middle",
    });
  });
}

/* ============ 13 — CLOSE ============ */
{
  const s = coverSlide();
  s.addShape(pres.ShapeType.ellipse, {
    x: 8.8,
    y: -1.8,
    w: 6.8,
    h: 6.8,
    fill: { color: COVER2 },
    line: { color: COVER2, width: 1 },
  });

  eyebrow(s, "Whenever you are ready", MINT, M, 1.1);
  head(s, "Walk the flow live.", {
    x: M,
    y: 1.6,
    w: 8,
    h: 0.8,
    size: 42,
    color: WHITE,
    valign: "middle",
  });
  s.addText(
    "Book as your patient → see the TV → call a serial → open the consult screen → send a prescription home.",
    {
      x: M,
      y: 2.55,
      w: 7.5,
      h: 0.9,
      fontFace: BODY_F,
      fontSize: 16,
      color: "D7EDEC",
      margin: 0,
      valign: "top",
    }
  );

  mintRule(s, M, 3.65, 0.55);

  head(s, "Then we lock the first sittings\nand give you a simple go-live checklist.", {
    x: M,
    y: 3.9,
    w: 7.5,
    h: 0.95,
    size: 24,
    color: MINT,
    valign: "top",
  });

  card(s, 8.7, 3.6, 4.0, 2.7, "0A2F30");
  s.addText("WhatsApp", {
    x: 8.7,
    y: 3.9,
    w: 4.0,
    h: 0.35,
    fontFace: BODY_F,
    fontSize: 12,
    bold: true,
    charSpacing: 2,
    color: MINT,
    align: "center",
    margin: 0,
    valign: "middle",
  });
  head(s, "01818-614349", {
    x: 8.7,
    y: 4.4,
    w: 4.0,
    h: 0.6,
    size: 26,
    color: WHITE,
    align: "center",
    valign: "middle",
  });
  s.addText("Joy Chowdhury\nChamberQ", {
    x: 8.7,
    y: 5.15,
    w: 4.0,
    h: 0.7,
    fontFace: BODY_F,
    fontSize: 14,
    color: "A8D4CF",
    align: "center",
    margin: 0,
    valign: "top",
  });

  s.addText("With respect — for the doctor whose room is already full.", {
    x: M,
    y: 6.75,
    w: CW,
    h: 0.35,
    fontFace: BODY_F,
    fontSize: 13,
    italic: true,
    color: "8BBDB8",
    margin: 0,
    valign: "middle",
  });
  s.addNotes("Close by offering a live walkthrough on their phone — not a contract.");
}

const out = path.join(__dirname, "ChamberQ-Maestro-Experienced-Solo-Pitch.pptx");
const fontsDir = path.join(__dirname, "../proposals/assets/fonts");
const fontCache = path.join(__dirname, ".font-cache");

/** Extract Helvetica Neue faces from macOS TTC at build time (not committed — Apple system font). */
async function ensureHelveticaFaces() {
  const needed = ["HelveticaNeue-Regular.ttf", "HelveticaNeue-Bold.ttf"];
  fs.mkdirSync(fontCache, { recursive: true });
  const missing = needed.filter((f) => !fs.existsSync(path.join(fontCache, f)));
  if (missing.length === 0) return fontCache;

  const { execFileSync } = await import("child_process");
  const script = `
from fontTools.ttLib import TTCollection
from pathlib import Path
ttc = TTCollection("/System/Library/Fonts/HelveticaNeue.ttc")
out = Path(${JSON.stringify(fontCache)})
wanted = {"Regular", "Bold"}
for font in ttc.fonts:
    family = style = None
    for rec in font["name"].names:
        if rec.nameID == 1 and rec.langID in (0, 1033):
            family = rec.toUnicode()
        if rec.nameID == 2 and rec.langID in (0, 1033):
            style = rec.toUnicode()
    if family == "Helvetica Neue" and style in wanted:
        font.save(out / f"HelveticaNeue-{style}.ttf")
        print("saved", style)
`;
  execFileSync("python3", ["-c", script], { stdio: "inherit" });
  for (const f of needed) {
    if (!fs.existsSync(path.join(fontCache, f))) {
      throw new Error(`Failed to extract ${f} from HelveticaNeue.ttc`);
    }
  }
  return fontCache;
}

function fontBuf(dir, file) {
  const buf = fs.readFileSync(path.join(dir, file));
  return buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength);
}

const helveticaDir = await ensureHelveticaFaces();

await pres.addFont({
  fontFace: HEAD,
  fontFile: fontBuf(fontsDir, "BebasNeue-Regular.ttf"),
  fontType: "ttf",
});
await pres.addFont({
  fontFace: BODY_F,
  fontFile: fontBuf(helveticaDir, "HelveticaNeue-Regular.ttf"),
  fontType: "ttf",
});
await pres.addFont({
  fontFace: BODY_F,
  fontFile: fontBuf(helveticaDir, "HelveticaNeue-Bold.ttf"),
  fontType: "ttf",
});

const written = await pres.writeFile({ fileName: out });
console.log("written:", written);
console.log("embedded:", HEAD, "(Bebas) +", BODY_F, "(Regular+Bold, build-time extract)");

// Force theme major/minor away from Calibri so Keynote/PowerPoint do not substitute.
const zip = await JSZip.loadAsync(fs.readFileSync(written));
const themePath = Object.keys(zip.files).find((p) => /ppt\/theme\/theme\d*\.xml$/.test(p));
if (themePath) {
  let theme = await zip.file(themePath).async("string");
  theme = theme
    .replace(/typeface="Calibri Light"/g, `typeface="${HEAD}"`)
    .replace(/typeface="Calibri"/g, `typeface="${BODY_F}"`);
  zip.file(themePath, theme);
  const patched = await zip.generateAsync({ type: "nodebuffer", compression: "DEFLATE" });
  fs.writeFileSync(written, patched);
  console.log("theme fonts patched in", themePath);
} else {
  console.warn("no theme xml found — Calibri may remain as theme default");
}
