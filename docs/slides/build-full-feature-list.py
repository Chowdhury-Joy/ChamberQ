#!/usr/bin/env python3
"""Generate ChamberQ full feature-list PDF (leave-behind / sales reference)."""

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
BLUE = colors.HexColor("#30A9E5")
MINT = colors.HexColor("#02C39A")
INK = colors.HexColor("#1A1F36")
MUTED = colors.HexColor("#6B7399")
LINE = colors.HexColor("#E4E8F0")
ROWALT = colors.HexColor("#F4F6FA")
WHITE = colors.white

HERE = Path(__file__).resolve().parent
OUT = HERE / "ChamberQ-Full-Feature-List.pdf"

# Must match config/marketing.php defaults
SOLO_SETUP, SOLO_MONTHLY = 15000, 3000
CLINIC_SETUP, CLINIC_MONTHLY = 75000, 7500

PAGE_W, PAGE_H = LETTER
MARGIN = 0.5 * inch

doc = SimpleDocTemplate(
    str(OUT),
    pagesize=LETTER,
    leftMargin=MARGIN,
    rightMargin=MARGIN,
    topMargin=0.42 * inch,
    bottomMargin=0.4 * inch,
    title="ChamberQ — Full Feature List",
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
        spaceAfter=2,
    ),
    "sub": ParagraphStyle(
        "sub",
        fontName="Helvetica",
        fontSize=9.5,
        textColor=MUTED,
        leading=12.5,
        spaceAfter=6,
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
        fontSize=8.4,
        textColor=INK,
        leading=10.8,
    ),
    "item_b": ParagraphStyle(
        "item_b",
        fontName="Helvetica-Bold",
        fontSize=8.4,
        textColor=NAVY,
        leading=10.8,
    ),
    "plan_name": ParagraphStyle(
        "plan_name",
        fontName="Helvetica-Bold",
        fontSize=11,
        textColor=NAVY,
        leading=13,
    ),
    "plan_price": ParagraphStyle(
        "plan_price",
        fontName="Helvetica-Bold",
        fontSize=12,
        textColor=NAVY,
        leading=14,
        alignment=TA_CENTER,
    ),
    "plan_sub": ParagraphStyle(
        "plan_sub",
        fontName="Helvetica",
        fontSize=8,
        textColor=MUTED,
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
    return Table(
        [[Paragraph(title, styles["sec"])]],
        colWidths=[PAGE_W - 2 * MARGIN],
    )


def feature_table(rows: list[tuple[str, str]]):
    """rows: (name, plain-English what it does)"""
    data = []
    for name, desc in rows:
        data.append(
            [
                Paragraph(name, styles["item_b"]),
                Paragraph(desc, styles["item"]),
            ]
        )
    t = Table(data, colWidths=[2.15 * inch, PAGE_W - 2 * MARGIN - 2.15 * inch])
    style_cmds = [
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 6),
        ("RIGHTPADDING", (0, 0), (-1, -1), 6),
        ("TOPPADDING", (0, 0), (-1, -1), 3.5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 3.5),
        ("BOX", (0, 0), (-1, -1), 0.5, LINE),
        ("LINEBELOW", (0, 0), (-1, -2), 0.4, LINE),
        ("BACKGROUND", (0, 0), (0, -1), ROWALT),
    ]
    for i in range(len(rows)):
        if i % 2 == 1:
            style_cmds.append(("BACKGROUND", (1, i), (1, i), ROWALT))
    t.setStyle(TableStyle(style_cmds))
    return t


sections = [
    (
        "For patients (public website)",
        [
            ("Branded chamber page", "Doctor/clinic site with photo, about, conditions, FAQs, testimonials, and a clear Book button"),
            ("Online serial booking", "Pick location (if multi-chamber), day, and session; see seats left / Full / Closed"),
            ("Pay at chamber", "Book online; visit fee stays at the desk — no online payment for the consult"),
            ("Digital serial ticket", "Place in line, share/copy, WhatsApp, Print / Save as PDF; map link when set"),
            ("Household picker", "Known phone shows masked initials — patient picks who the visit is for"),
            ("Patient portal", "Enter phone → find recent tickets (not a medical file)"),
            ("EN / BN language", "Book, ticket, and portal system strings in English or Bangla"),
            ("Walk-ins welcome", "Staff can add walk-ins; online booking does not ban walk-ins"),
            ("PWA install", "Optional “add to home screen” for the chamber site"),
        ],
    ),
    (
        "Chamber day — live queue & waiting room",
        [
            ("Live Queue Control", "Start session → Call → Arrived → Complete; walk-ins; skip / Call now out of turn"),
            ("Session actions", "Mark late, pause/resume, cancel session, finish/end — with patient notices where set"),
            ("Outdoor waiting-room screen", "TV/tablet shows now serving + next"),
            ("Call announce", "Chime, spoken “Calling number…”, or both — English or বাংলা"),
            ("Wait-time ETA", "Schedule guess / live average / live steady (branding settings)"),
            ("Daily roster", "Today’s list, searchable; staff walk-ins and optional prescription entry"),
            ("Doctor late SMS/WhatsApp", "Notify waiting patients when the doctor is running late"),
        ],
    ),
    (
        "Consult, records & prescriptions",
        [
            ("Consult Screen", "Auto-updates when a patient is in chamber — history, notes, prescription"),
            ("Patient records", "Name, phone, age/DOB, sex, allergies/conditions/medicines; visit history for staff/doctor"),
            ("Visit notes", "Diagnosis (coded or free text), advice, tests advised, reports seen, follow-up"),
            ("Voice note + paper photo", "Doctor can speak a brief note or photograph a paper slip (private storage)"),
            ("Digital prescriptions", "Medicine picker with dose/frequency/duration; print with doctor credentials"),
            ("Patient prescription link", "Short expiring link (/p/…) — medicines only, no diagnosis — SMS and/or WhatsApp"),
            ("Same as last visit", "Reuse prior medicines/follow-up when appropriate"),
            ("My medicines", "Doctor personal defaults and hide list — shared catalogue stays untouched"),
            ("Staff type paper Rx", "Optional per doctor: staff enter medicines + photo after consult (no diagnosis access)"),
            ("End-of-session catch-up", "Banner for completed patients still missing notes"),
        ],
    ),
    (
        "Schedules, locations & ops",
        [
            ("Schedules & sessions", "Days, times, seat caps per chamber/doctor"),
            ("Multi-chamber", "Solo: up to 5 locations · Clinic: unlimited"),
            ("Vacation / holiday blocks", "Block a date; auto-cancel open serials; one-tap WhatsApp notify list"),
            ("Operational reports", "Day / week / month chamber KPIs"),
            ("Branding & website editor", "Logo, colors, fonts, contact, WhatsApp, call audio; page sections"),
            ("SMS credit wallet", "Prepaid confirmations (~৳0.50); booking still works if wallet is empty"),
            ("Notify preferences", "Per doctor: booking confirm, late, cancellation, prescription — SMS and/or WhatsApp"),
            ("Staff roles", "Admin · Doctor · Staff — queue, content, and clinical access split"),
            ("Custom domain", "Optional own domain (e.g. drkarim.com) at root paths"),
            ("Bangla homepage", "Paid add-on when Super Admin enables it"),
        ],
    ),
    (
        "Clinic plan extras (beyond Solo)",
        [
            ("Multiple doctors", "Associates in one system; each doctor’s clinical notes stay private"),
            ("Unlimited chambers", "No 5-location Solo cap"),
            ("Lab tests in booking", "Catalogue + collection slots; patient can add labs to the same visit"),
        ],
    ),
]

story = []
story.append(Paragraph("CHAMBERQ", styles["eyebrow"]))
story.append(Paragraph("Full feature list", styles["h1"]))
story.append(
    Paragraph(
        "Everything in the product today — for doctors, staff, and patients. "
        "Patients book a serial and pay at the chamber. We set you up over WhatsApp (no self-signup).",
        styles["sub"],
    )
)

for title, rows in sections:
    hdr = section_header(title)
    hdr.setStyle(
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
    block = KeepTogether([hdr, feature_table(rows), Spacer(1, 7)])
    story.append(block)

# Pricing band
price_data = [
    [
        Paragraph("<b>Solo</b> — one doctor, up to 5 chambers", styles["plan_name"]),
        Paragraph("<b>Clinic</b> — multi-doctor + labs", styles["plan_name"]),
    ],
    [
        Paragraph(f"৳{SOLO_SETUP:,} setup  ·  ৳{SOLO_MONTHLY:,} / month", styles["plan_price"]),
        Paragraph(f"৳{CLINIC_SETUP:,} setup  ·  ৳{CLINIC_MONTHLY:,} / month", styles["plan_price"]),
    ],
    [
        Paragraph("SMS credits extra · WhatsApp ticket share free · Done-with-you setup", styles["plan_sub"]),
        Paragraph("Same patient tools, scaled · SMS credits extra · Done-with-you setup", styles["plan_sub"]),
    ],
]
price = Table(price_data, colWidths=[(PAGE_W - 2 * MARGIN) / 2] * 2)
price.setStyle(
    TableStyle(
        [
            ("BOX", (0, 0), (-1, -1), 1, NAVY),
            ("LINEAFTER", (0, 0), (0, -1), 0.5, LINE),
            ("BACKGROUND", (0, 0), (-1, 0), ROWALT),
            ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
            ("LEFTPADDING", (0, 0), (-1, -1), 10),
            ("RIGHTPADDING", (0, 0), (-1, -1), 10),
            ("TOPPADDING", (0, 0), (-1, -1), 6),
            ("BOTTOMPADDING", (0, 0), (-1, -1), 6),
        ]
    )
)
story.append(Spacer(1, 2))
story.append(price)
story.append(Spacer(1, 6))
story.append(
    Paragraph(
        "Not included (on purpose for now): online visit payment · full hospital EMR/HIS · self-serve signup. "
        "Prices from config/marketing.php · regenerate with docs/slides/build-full-feature-list.py",
        styles["foot"],
    )
)

doc.build(story)
print(f"written → {OUT}")
