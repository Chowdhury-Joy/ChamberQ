#!/usr/bin/env python3
"""Generate ChamberQ Solo Top-7 Pain Point / Feature / Solution PDF (no Clinic).

Top 7 = highest-desire Solo features for a sales meeting close —
not the first seven rows of the full product map.
"""

from pathlib import Path

from reportlab.lib.pagesizes import LETTER
from reportlab.lib.units import inch
from reportlab.lib import colors
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER

# ---------------- palette (matches the client decks) ----------------
NAVY = colors.HexColor("#0E1954")
NAVY2 = colors.HexColor("#1B2978")
BLUE = colors.HexColor("#30A9E5")
MINT = colors.HexColor("#02C39A")
INK = colors.HexColor("#1A1F36")
MUTED = colors.HexColor("#6B7399")
LINE = colors.HexColor("#E4E8F0")
ROWALT = colors.HexColor("#F4F6FA")
WHITE = colors.white

HERE = Path(__file__).resolve().parent
OUT = HERE / "ChamberQ-Painpoint-Feature-Solution-Solo-Top7.pdf"

# Prices must match config/marketing.php MARKETING_SOLO_* defaults
SOLO_SETUP, SOLO_MONTHLY = 15000, 3000

MARGIN = 0.5 * inch

doc = SimpleDocTemplate(
    str(OUT),
    pagesize=LETTER,
    leftMargin=MARGIN,
    rightMargin=MARGIN,
    topMargin=0.42 * inch,
    bottomMargin=0.4 * inch,
    title="ChamberQ — Pain Point / Feature / Solution (Solo, Top 7)",
    author="ChamberQ",
)

styles = {
    "eyebrow": ParagraphStyle(
        "eyebrow7",
        fontName="Helvetica-Bold",
        fontSize=9.5,
        textColor=BLUE,
        leading=12,
        spaceAfter=2,
    ),
    "h1": ParagraphStyle(
        "h17",
        fontName="Helvetica-Bold",
        fontSize=22,
        textColor=NAVY,
        leading=26,
        spaceAfter=2,
    ),
    "sub": ParagraphStyle(
        "sub7",
        fontName="Helvetica",
        fontSize=11,
        textColor=MUTED,
        leading=14.5,
    ),
    "colhead": ParagraphStyle(
        "colhead7",
        fontName="Helvetica-Bold",
        fontSize=10,
        textColor=WHITE,
        leading=12,
        alignment=TA_LEFT,
    ),
    "num": ParagraphStyle(
        "num7",
        fontName="Helvetica-Bold",
        fontSize=11,
        textColor=WHITE,
        leading=13,
        alignment=TA_CENTER,
    ),
    "pain": ParagraphStyle(
        "pain7",
        fontName="Helvetica",
        fontSize=10,
        textColor=INK,
        leading=13,
    ),
    "feature": ParagraphStyle(
        "feature7",
        fontName="Helvetica-Bold",
        fontSize=10,
        textColor=NAVY,
        leading=13,
    ),
    "solution": ParagraphStyle(
        "solution7",
        fontName="Helvetica",
        fontSize=10,
        textColor=INK,
        leading=13,
    ),
    "close_kicker": ParagraphStyle(
        "close_kicker7",
        fontName="Helvetica-Bold",
        fontSize=9,
        textColor=MINT,
        leading=11,
        spaceAfter=2,
    ),
    "close_h": ParagraphStyle(
        "close_h7",
        fontName="Helvetica-Bold",
        fontSize=14,
        textColor=WHITE,
        leading=18,
        spaceAfter=4,
    ),
    "close_body": ParagraphStyle(
        "close_body7",
        fontName="Helvetica",
        fontSize=10,
        textColor=colors.HexColor("#D6DCF0"),
        leading=13.5,
    ),
    "close_price": ParagraphStyle(
        "close_price7",
        fontName="Helvetica-Bold",
        fontSize=12,
        textColor=MINT,
        leading=15,
        alignment=TA_CENTER,
    ),
    "close_price_sub": ParagraphStyle(
        "close_price_sub7",
        fontName="Helvetica",
        fontSize=8.5,
        textColor=colors.HexColor("#A8B0D0"),
        leading=11,
        alignment=TA_CENTER,
    ),
    "foot": ParagraphStyle(
        "foot7",
        fontName="Helvetica-Oblique",
        fontSize=8.5,
        textColor=MUTED,
        leading=11,
    ),
}

# Highest-desire Solo features (sales priority) — not rows 1–7 of the full map.
# Excluded on purpose: Clinic/multi-doctor, labs, and secondary ops (household,
# vacation, privacy, voice capture) — those live on the full 15-row page.
rows_data = [
    (
        "Your phone won’t stop. Every consult interrupted by “Doctor, is there a serial?”",
        "Online serial booking",
        "Patients book themselves. You finish a consult without answering the same call again.",
    ),
    (
        "A packed waiting room, restless patients, arguments over whose turn it is",
        "Live queue + patient ticket",
        "They see when to come. The room thins out. You walk into a calmer chamber.",
    ),
    (
        "Nobody knows who’s next — staff shout numbers, people keep asking",
        "Outdoor screen with voice announce",
        "The screen calls the next serial. Fair. Clear. No more “am I next?” at the door.",
    ),
    (
        "You have no real website — only a Facebook post patients can’t book from",
        "Branded patient website",
        "Your name, credentials, and one clear Book button. Patients find you — and book.",
    ),
    (
        "A returning patient — and you can’t remember last time’s advice or medicines",
        "Patient record + Consult Screen",
        "Their last visit is on screen the second they’re called. You look prepared. You are.",
    ),
    (
        "Paper prescriptions get lost. Patients come back guessing what you wrote",
        "Digital prescriptions, reprintable",
        "One tap to reprint — with your name and registration. Clean. Professional. Yours.",
    ),
    (
        "You sit at 2–3 different chambers — and nothing talks to each other",
        "Multi-chamber (up to 5)",
        "Every location, schedule, and serial in one place. Same calm system wherever you sit.",
    ),
]

story = []

story.append(Paragraph("FOR SOLO DOCTORS &amp; CHAMBERS IN BANGLADESH", styles["eyebrow"]))
story.append(Paragraph("Imagine tomorrow’s chamber.", styles["h1"]))
story.append(
    Paragraph(
        "Fewer phone calls. Shorter waits. Patients who feel respected — and tell others. "
        "These are the seven features that change a Solo doctor’s day.",
        styles["sub"],
    )
)
story.append(Spacer(1, 10))

col_widths = [0.38 * inch, 2.5 * inch, 1.55 * inch, 2.72 * inch]

header = [
    Paragraph("#", styles["colhead"]),
    Paragraph("WHAT’S WEARING YOU DOWN", styles["colhead"]),
    Paragraph("WHAT FIXES IT", styles["colhead"]),
    Paragraph("HOW TOMORROW FEELS", styles["colhead"]),
]

table_rows = [header]
for i, (pain, feat, sol) in enumerate(rows_data, 1):
    table_rows.append(
        [
            Paragraph(str(i), styles["num"]),
            Paragraph(pain, styles["pain"]),
            Paragraph(feat, styles["feature"]),
            Paragraph(sol, styles["solution"]),
        ]
    )

t = Table(table_rows, colWidths=col_widths, repeatRows=1)

style_cmds = [
    ("BACKGROUND", (0, 0), (-1, 0), NAVY),
    ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
    ("TOPPADDING", (0, 0), (-1, 0), 8),
    ("BOTTOMPADDING", (0, 0), (-1, 0), 8),
    ("LEFTPADDING", (0, 0), (-1, -1), 7),
    ("RIGHTPADDING", (0, 0), (-1, -1), 7),
    ("LEFTPADDING", (0, 1), (0, -1), 2),
    ("RIGHTPADDING", (0, 1), (0, -1), 2),
    ("TOPPADDING", (0, 1), (-1, -1), 8),
    ("BOTTOMPADDING", (0, 1), (-1, -1), 8),
    ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
    ("ALIGN", (0, 1), (0, -1), "CENTER"),
    ("LINEBELOW", (0, 0), (-1, -1), 0.5, LINE),
    ("BOX", (0, 0), (-1, -1), 0.75, NAVY2),
]
for i in range(1, len(table_rows)):
    if i % 2 == 0:
        style_cmds.append(("BACKGROUND", (0, i), (-1, i), ROWALT))
    style_cmds.append(("BACKGROUND", (0, i), (0, i), BLUE if i % 2 else NAVY2))

t.setStyle(TableStyle(style_cmds))
story.append(t)

story.append(Spacer(1, 12))

close_left = [
    Paragraph("START TODAY", styles["close_kicker"]),
    Paragraph("Say yes in this meeting — your page can be live tomorrow.", styles["close_h"]),
    Paragraph(
        "Patients still pay you at the chamber, in cash — same as now. "
        "We build the site, sessions, and queue with you. "
        "You keep seeing patients. We handle the setup.",
        styles["close_body"],
    ),
]
close_right = [
    Paragraph(f"Tk {SOLO_SETUP:,}", styles["close_price"]),
    Paragraph("one-time setup", styles["close_price_sub"]),
    Spacer(1, 4),
    Paragraph(f"Tk {SOLO_MONTHLY:,} / month", styles["close_price"]),
    Paragraph("1 doctor · up to 5 chambers", styles["close_price_sub"]),
]

close = Table(
    [[close_left, close_right]],
    colWidths=[5.35 * inch, 2.2 * inch],
)
close.setStyle(
    TableStyle(
        [
            ("BACKGROUND", (0, 0), (-1, -1), NAVY),
            ("BACKGROUND", (1, 0), (1, 0), NAVY2),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("LEFTPADDING", (0, 0), (0, 0), 14),
            ("RIGHTPADDING", (0, 0), (0, 0), 10),
            ("TOPPADDING", (0, 0), (-1, -1), 12),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 12),
            ("LEFTPADDING", (1, 0), (1, 0), 10),
            ("RIGHTPADDING", (1, 0), (1, 0), 10),
            ("BOX", (0, 0), (-1, -1), 0, NAVY),
        ]
    )
)
story.append(close)

story.append(Spacer(1, 8))
story.append(
    Paragraph(
        "This isn’t software you wrestle with alone — we go live with you. "
        "The only question left is whether tomorrow’s chamber feels like today’s.",
        styles["foot"],
    )
)

doc.build(story)
print(f"written → {OUT}")
