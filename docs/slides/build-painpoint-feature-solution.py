#!/usr/bin/env python3
"""Generate ChamberQ one-page Pain Point / Feature / Solution PDF leave-behind."""

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
INK = colors.HexColor("#1A1F36")
MUTED = colors.HexColor("#6B7399")
LINE = colors.HexColor("#E4E8F0")
ROWALT = colors.HexColor("#F4F6FA")
WHITE = colors.white

HERE = Path(__file__).resolve().parent
OUT = HERE / "ChamberQ-Painpoint-Feature-Solution.pdf"

# Prices must match config/marketing.php (MARKETING_SOLO_* / MARKETING_CLINIC_* defaults)
SOLO_SETUP, SOLO_MONTHLY = 15000, 3000
CLINIC_SETUP, CLINIC_MONTHLY = 75000, 7500

PAGE_W, PAGE_H = LETTER
MARGIN = 0.55 * inch

doc = SimpleDocTemplate(
    str(OUT),
    pagesize=LETTER,
    leftMargin=MARGIN,
    rightMargin=MARGIN,
    topMargin=0.5 * inch,
    bottomMargin=0.45 * inch,
    title="ChamberQ — Pain Point / Feature / Solution",
    author="ChamberQ",
)

styles = {
    "eyebrow": ParagraphStyle(
        "eyebrow",
        fontName="Helvetica-Bold",
        fontSize=9.5,
        textColor=BLUE,
        leading=12,
        spaceAfter=2,
    ),
    "h1": ParagraphStyle(
        "h1",
        fontName="Helvetica-Bold",
        fontSize=22,
        textColor=NAVY,
        leading=26,
        spaceAfter=2,
    ),
    "sub": ParagraphStyle(
        "sub",
        fontName="Helvetica",
        fontSize=10.5,
        textColor=MUTED,
        leading=14,
    ),
    "colhead": ParagraphStyle(
        "colhead",
        fontName="Helvetica-Bold",
        fontSize=9.5,
        textColor=WHITE,
        leading=12,
        alignment=TA_LEFT,
    ),
    "num": ParagraphStyle(
        "num",
        fontName="Helvetica-Bold",
        fontSize=9.5,
        textColor=WHITE,
        leading=12,
        alignment=TA_CENTER,
    ),
    "pain": ParagraphStyle(
        "pain",
        fontName="Helvetica",
        fontSize=9,
        textColor=INK,
        leading=11.6,
    ),
    "feature": ParagraphStyle(
        "feature",
        fontName="Helvetica-Bold",
        fontSize=9,
        textColor=NAVY,
        leading=11.6,
    ),
    "solution": ParagraphStyle(
        "solution",
        fontName="Helvetica",
        fontSize=9,
        textColor=INK,
        leading=11.6,
    ),
    "foot": ParagraphStyle(
        "foot",
        fontName="Helvetica-Oblique",
        fontSize=8,
        textColor=MUTED,
        leading=11,
    ),
}

# Pain point → Feature → Solution (plain English, chamber language)
rows_data = [
    (
        "Assistant spends the whole day on the phone giving out serials",
        "Online serial booking",
        "Patient books their own serial on their phone — no call needed",
    ),
    (
        "Patients crowd the room for hours; arguments over turns",
        "Live queue + patient ticket",
        "Ticket shows a live “come around ~X:XX” time, so patients arrive closer to their turn",
    ),
    (
        "No order in the waiting room — nobody knows who's next",
        "Outdoor screen with voice announce",
        "Screen shows the number and calls it aloud; nobody has to ask or argue",
    ),
    (
        "A returning patient's history is gone — paper lost, nothing remembered",
        "Patient record + Consult Screen",
        "Last diagnosis and advice appear automatically the moment the patient is called",
    ),
    (
        "Prescriptions illegible or lost — patient re-describes symptoms",
        "Digital prescriptions, reprintable",
        "Printed with the doctor's credentials, reprinted in one tap any time",
    ),
    (
        "Doctor doesn't want to type notes during a consult",
        "Voice note, paper photo, or staff entry",
        "Speak briefly, photograph your paper slip, or let staff type medicines after — nothing is compulsory",
    ),
    (
        "Staff could see sensitive patient information",
        "Doctor-only clinical data",
        "Staff run the queue and bookings; diagnosis and voice notes stay doctor-only",
    ),
    (
        "Doctor has no real website, only a Facebook post",
        "Branded patient website",
        "A proper site with credentials, chambers, timings, and one clear Book button",
    ),
    (
        "Family members sharing one phone number get mixed up in records",
        "Household / patient picker",
        "On a known number, the patient chooses who the booking is for — each person keeps a separate record",
    ),
    (
        "Doctor needs to close for a day — travel, emergency, holiday",
        "Vacation mode / slot blocks",
        "Block a date; existing serials auto-cancel with a one-tap WhatsApp notice to each patient",
    ),
    (
        "A junior doctor or associate joins with no shared system to hand off to",
        "Multi-doctor / Clinic tier",
        "Associates work in the same system; each doctor keeps their own clinical notes private",
    ),
    (
        "Practice has multiple chambers or locations to track separately",
        "Multi-chamber support",
        "All chambers, schedules, and bookings live in one place",
    ),
    (
        "Lab tests are a separate errand for the patient",
        "Lab tests inside booking",
        "Patient adds a lab test to the same appointment — no separate visit needed",
    ),
    (
        "Doctor fears going digital means losing control of cash or payments",
        "Pay-at-chamber only",
        "No online payment, no gateway commission — same cash flow as today",
    ),
    (
        "Doctor worries setup will be a hassle",
        "We set it up together",
        "Site, chambers, schedule, and logins built with the doctor — no computer skills needed to go live",
    ),
]

story = []

story.append(Paragraph("FOR DOCTORS &amp; CHAMBERS IN BANGLADESH", styles["eyebrow"]))
story.append(Paragraph("ChamberQ &mdash; Pain Point, Feature, Solution", styles["h1"]))
story.append(
    Paragraph(
        "A one-page map from what a chamber struggles with today to the exact feature that fixes it.",
        styles["sub"],
    )
)
story.append(Spacer(1, 8))

# column widths: # | Pain Point | Feature | Solution
col_widths = [0.4 * inch, 2.45 * inch, 1.6 * inch, 2.65 * inch]

header = [
    Paragraph("#", styles["colhead"]),
    Paragraph("PAIN POINT", styles["colhead"]),
    Paragraph("FEATURE", styles["colhead"]),
    Paragraph("SOLUTION", styles["colhead"]),
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
    ("TOPPADDING", (0, 0), (-1, 0), 7),
    ("BOTTOMPADDING", (0, 0), (-1, 0), 7),
    ("LEFTPADDING", (0, 0), (-1, -1), 7),
    ("RIGHTPADDING", (0, 0), (-1, -1), 7),
    ("LEFTPADDING", (0, 1), (0, -1), 2),
    ("RIGHTPADDING", (0, 1), (0, -1), 2),
    ("TOPPADDING", (0, 1), (-1, -1), 5.5),
    ("BOTTOMPADDING", (0, 1), (-1, -1), 5.5),
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

story.append(Spacer(1, 10))
story.append(
    Paragraph(
        "Patients still pay at the chamber, in cash — nothing here changes how you're paid. "
        f"Solo: Tk {SOLO_SETUP:,} setup / Tk {SOLO_MONTHLY:,} per month (1 doctor, up to 5 chambers). "
        f"Clinic: Tk {CLINIC_SETUP:,} setup / Tk {CLINIC_MONTHLY:,} per month "
        "(multiple doctors, chambers &amp; labs).",
        styles["foot"],
    )
)

doc.build(story)
print(f"written → {OUT}")
