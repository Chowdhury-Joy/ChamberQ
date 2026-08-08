#!/usr/bin/env python3
"""Solo doctor share sheet — what you get on the Solo plan (plain English)."""

from pathlib import Path

from reportlab.lib.pagesizes import LETTER
from reportlab.lib.units import inch
from reportlab.lib import colors
from reportlab.platypus import (
    SimpleDocTemplate,
    Table,
    TableStyle,
    Paragraph,
    Spacer,
    KeepTogether,
)
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.enums import TA_LEFT, TA_CENTER

NAVY = colors.HexColor("#0E1954")
NAVY2 = colors.HexColor("#1B2978")
BLUE = colors.HexColor("#30A9E5")
INK = colors.HexColor("#1A1F36")
MUTED = colors.HexColor("#6B7399")
LINE = colors.HexColor("#E4E8F0")
ROWALT = colors.HexColor("#F4F6FA")
WHITE = colors.white

HERE = Path(__file__).resolve().parent
OUT = HERE / "ChamberQ-Solo-What-You-Get.pdf"

# Must match config/marketing.php Solo defaults
SOLO_SETUP, SOLO_MONTHLY = 15000, 3000

PAGE_W, PAGE_H = LETTER
MARGIN = 0.52 * inch

doc = SimpleDocTemplate(
    str(OUT),
    pagesize=LETTER,
    leftMargin=MARGIN,
    rightMargin=MARGIN,
    topMargin=0.45 * inch,
    bottomMargin=0.4 * inch,
    title="ChamberQ Solo — What you get",
    author="ChamberQ",
)

styles = {
    "eyebrow": ParagraphStyle(
        "eyebrow",
        fontName="Helvetica-Bold",
        fontSize=9,
        textColor=BLUE,
        leading=11,
        spaceAfter=1,
    ),
    "h1": ParagraphStyle(
        "h1",
        fontName="Helvetica-Bold",
        fontSize=20,
        textColor=NAVY,
        leading=24,
        spaceAfter=3,
    ),
    "sub": ParagraphStyle(
        "sub",
        fontName="Helvetica",
        fontSize=9.5,
        textColor=MUTED,
        leading=12.5,
        spaceAfter=8,
    ),
    "sec": ParagraphStyle(
        "sec",
        fontName="Helvetica-Bold",
        fontSize=10,
        textColor=WHITE,
        leading=12,
        alignment=TA_LEFT,
    ),
    "item": ParagraphStyle(
        "item",
        fontName="Helvetica",
        fontSize=8.6,
        textColor=INK,
        leading=11,
    ),
    "item_b": ParagraphStyle(
        "item_b",
        fontName="Helvetica-Bold",
        fontSize=8.6,
        textColor=NAVY,
        leading=11,
    ),
    "close_kicker": ParagraphStyle(
        "close_kicker",
        fontName="Helvetica-Bold",
        fontSize=8.5,
        textColor=BLUE,
        leading=10,
        spaceAfter=3,
    ),
    "close_h": ParagraphStyle(
        "close_h",
        fontName="Helvetica-Bold",
        fontSize=12,
        textColor=WHITE,
        leading=15,
        spaceAfter=4,
    ),
    "close_body": ParagraphStyle(
        "close_body",
        fontName="Helvetica",
        fontSize=8.5,
        textColor=colors.HexColor("#C8D0E8"),
        leading=11.5,
    ),
    "close_price": ParagraphStyle(
        "close_price",
        fontName="Helvetica-Bold",
        fontSize=16,
        textColor=WHITE,
        leading=18,
        alignment=TA_CENTER,
    ),
    "close_price_sub": ParagraphStyle(
        "close_price_sub",
        fontName="Helvetica",
        fontSize=8,
        textColor=colors.HexColor("#A8B4D4"),
        leading=10,
        alignment=TA_CENTER,
    ),
    "foot": ParagraphStyle(
        "foot",
        fontName="Helvetica-Oblique",
        fontSize=7.5,
        textColor=MUTED,
        leading=10,
        alignment=TA_CENTER,
    ),
}


def section_header(title: str):
    t = Table([[Paragraph(title, styles["sec"])]], colWidths=[PAGE_W - 2 * MARGIN])
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), NAVY),
                ("LEFTPADDING", (0, 0), (-1, -1), 8),
                ("RIGHTPADDING", (0, 0), (-1, -1), 8),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ]
        )
    )
    return t


def feature_table(rows: list[tuple[str, str]]):
    data = [
        [Paragraph(name, styles["item_b"]), Paragraph(desc, styles["item"])]
        for name, desc in rows
    ]
    t = Table(data, colWidths=[2.1 * inch, PAGE_W - 2 * MARGIN - 2.1 * inch])
    cmds = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 3.8),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3.8),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("LINEBELOW", (0, 0), (-1, -2), 0.4, LINE),
        ("BACKGROUND", (0, 0), (0, -1), ROWALT),
    ]
    for i in range(len(rows)):
        if i % 2 == 1:
            cmds.append(("BACKGROUND", (1, i), (1, i), ROWALT))
    t.setStyle(TableStyle(cmds))
    return t


# Solo only — no Clinic / multi-doctor / labs
sections = [
    (
        "Your patients",
        [
            ("Your own webpage", "Your name, photo, chambers, timings, and a clear Book button — looks like a real clinic site"),
            ("Online serial", "Patients pick a day and session on their phone. Seats left are clear. No “what’s my serial?” calls"),
            ("Pay at the chamber", "Same as today — they book online, pay you at the desk. No online visit payment"),
            ("Digital ticket", "Shows their place in line. They can WhatsApp it to family, or Print / Save as PDF"),
            ("Family on one phone", "If the number is known, they pick who the visit is for — each person keeps a separate record"),
            ("Find my ticket later", "Portal: enter phone → see recent bookings (not a medical file)"),
            ("English or Bangla", "Booking, ticket, and portal work in both languages"),
            ("Walk-ins still OK", "Your staff can add walk-ins. Online does not replace the door"),
        ],
    ),
    (
        "Your chamber day",
        [
            ("Live queue", "Call → patient arrived → complete. Fair order. Walk-ins fit in when you want"),
            ("Waiting-room screen", "TV or tablet shows who is being called — less shouting at the door"),
            ("Spoken call (optional)", "Screen can say “Calling number 12” (or Bangla) with or without a chime"),
            ("Come-around time", "Ticket can show roughly when to arrive — fewer people sitting for hours"),
            ("You’re running late", "One action can notify waiting patients by SMS and/or WhatsApp"),
            ("Daily list", "See who’s booked today. Search by name or phone"),
        ],
    ),
    (
        "Inside the consult",
        [
            ("Consult screen", "When you call a patient, their past visits appear — last advice, medicines, follow-up"),
            ("Patient memory", "Name, phone, age, allergies, past visits — so returning patients aren’t a blank slate"),
            ("Visit notes (optional)", "Diagnosis, advice, tests, follow-up — only when you want to record them"),
            ("Voice or paper photo", "Speak a short note, or photograph your handwritten slip — typing is not required"),
            ("Printable prescription", "Medicines with dose/frequency; prints with your name and registration"),
            ("Send Rx to patient", "Short link by WhatsApp/SMS — medicines only, no diagnosis on that link"),
            ("Same as last visit", "Reuse last medicines when it makes sense"),
            ("Staff help (optional)", "You can allow staff to type medicines from your paper after the consult — they never see diagnosis"),
        ],
    ),
    (
        "Running your practice",
        [
            ("Up to 5 chambers", "Sit at different hospitals on different days — one Solo plan, one system"),
            ("Your schedule", "Set session days, times, and how many seats"),
            ("Vacation / holiday", "Block a day; open serials cancel; message cancelled patients on WhatsApp in one list"),
            ("Reports", "End of day / week / month — how busy you were, without guessing"),
            ("Edit your site", "You or staff update text and photos. We help at setup"),
            ("SMS confirmations", "Optional prepaid credits (~Tk 0.50 each). Booking still works if credits run out"),
            ("Your team roles", "Admin, Doctor, Staff — front desk can run the queue without seeing clinical notes"),
            ("We set it up with you", "WhatsApp onboarding — no self-signup, no coding"),
        ],
    ),
]

story = []
story.append(Paragraph("CHAMBERQ  ·  SOLO PLAN", styles["eyebrow"]))
story.append(Paragraph("What you get", styles["h1"]))
story.append(
    Paragraph(
        "For one doctor — up to 5 chambers. Built for Bangladesh chamber practice. "
        "Patients book a serial on their phone and still pay you at the desk. "
        "This is everything included on Solo.",
        styles["sub"],
    )
)

for title, rows in sections:
    story.append(
        KeepTogether([section_header(title), feature_table(rows), Spacer(1, 8)])
    )

close_left = [
    Paragraph("READY WHEN YOU ARE", styles["close_kicker"]),
    Paragraph("Message us on WhatsApp — we build your page and go live with you.", styles["close_h"]),
    Paragraph(
        "Patients still pay at the chamber. Walk-ins still welcome. "
        "You keep seeing patients; we handle the setup.",
        styles["close_body"],
    ),
]
close_right = [
    Paragraph(f"Tk {SOLO_SETUP:,}", styles["close_price"]),
    Paragraph("one-time setup", styles["close_price_sub"]),
    Spacer(1, 5),
    Paragraph(f"Tk {SOLO_MONTHLY:,} / month", styles["close_price"]),
    Paragraph("1 doctor · up to 5 chambers", styles["close_price_sub"]),
]

close = Table([[close_left, close_right]], colWidths=[5.2 * inch, 2.45 * inch])
close.setStyle(
    TableStyle(
        [
            ("BACKGROUND", (0, 0), (0, 0), NAVY),
            ("BACKGROUND", (1, 0), (1, 0), NAVY2),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("LEFTPADDING", (0, 0), (0, 0), 14),
            ("RIGHTPADDING", (0, 0), (0, 0), 10),
            ("LEFTPADDING", (1, 0), (1, 0), 8),
            ("RIGHTPADDING", (1, 0), (1, 0), 8),
            ("TOPPADDING", (0, 0), (-1, -1), 12),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 12),
        ]
    )
)
story.append(Spacer(1, 4))
story.append(close)
story.append(Spacer(1, 6))
story.append(
    Paragraph(
        "SMS packs are optional and extra. Bangla homepage and custom domain available if you want them. "
        "No Clinic / multi-doctor / lab features on this sheet — ask us if you grow into a multi-doctor practice.",
        styles["foot"],
    )
)

doc.build(story)
print(f"written → {OUT}")
