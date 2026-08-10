#!/usr/bin/env python3
"""Generate HMS Build Estimate & Roadmap PDF.

Outputs:
  docs/planning/HMS-Build-Estimate-Roadmap.pdf
"""

from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT, TA_RIGHT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import (
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

HERE = Path(__file__).resolve().parent
OUT = HERE / "HMS-Build-Estimate-Roadmap.pdf"

PAGE_W, PAGE_H = A4
MARGIN = 16 * mm

INK = colors.HexColor("#0f3d3e")
INK_SOFT = colors.HexColor("#134e4a")
ACCENT = colors.HexColor("#0f766e")
BODY = colors.HexColor("#1a2332")
MUTED = colors.HexColor("#6b7280")
LINE = colors.HexColor("#e5e7eb")
CARD = colors.HexColor("#f4f7f7")
CARD_ACCENT = colors.HexColor("#ecf6f5")
MINT = colors.HexColor("#5eead4")
COVER_DARK = colors.HexColor("#0a2f30")
WARN_BG = colors.HexColor("#fef3c7")
WARN_BORDER = colors.HexColor("#f59e0b")
WHITE = colors.white


def styles():
    base = getSampleStyleSheet()
    return {
        "cover_brand": ParagraphStyle(
            "cover_brand",
            fontName="Helvetica-Bold",
            fontSize=11,
            textColor=WHITE,
            leading=14,
            spaceAfter=4,
        ),
        "cover_tag": ParagraphStyle(
            "cover_tag",
            fontName="Helvetica",
            fontSize=10,
            textColor=colors.HexColor("#c8e6e3"),
            leading=13,
        ),
        "cover_title": ParagraphStyle(
            "cover_title",
            fontName="Helvetica-Bold",
            fontSize=26,
            textColor=WHITE,
            leading=30,
            spaceAfter=14,
        ),
        "cover_lead": ParagraphStyle(
            "cover_lead",
            fontName="Helvetica",
            fontSize=11,
            textColor=colors.HexColor("#e8f5f4"),
            leading=15,
        ),
        "cover_meta": ParagraphStyle(
            "cover_meta",
            fontName="Helvetica",
            fontSize=10,
            textColor=colors.HexColor("#d0ebe9"),
            leading=14,
        ),
        "eyebrow": ParagraphStyle(
            "eyebrow",
            fontName="Helvetica-Bold",
            fontSize=8,
            textColor=ACCENT,
            leading=10,
            spaceAfter=6,
        ),
        "h1": ParagraphStyle(
            "h1",
            fontName="Helvetica-Bold",
            fontSize=20,
            textColor=INK,
            leading=24,
            spaceAfter=8,
        ),
        "subhead": ParagraphStyle(
            "subhead",
            fontName="Helvetica",
            fontSize=10,
            textColor=MUTED,
            leading=13,
            spaceAfter=12,
        ),
        "h2": ParagraphStyle(
            "h2",
            fontName="Helvetica-Bold",
            fontSize=12,
            textColor=INK_SOFT,
            leading=15,
            spaceBefore=10,
            spaceAfter=6,
        ),
        "h3": ParagraphStyle(
            "h3",
            fontName="Helvetica-Bold",
            fontSize=10.5,
            textColor=BODY,
            leading=13,
            spaceBefore=8,
            spaceAfter=4,
        ),
        "body": ParagraphStyle(
            "body",
            fontName="Helvetica",
            fontSize=9.5,
            textColor=BODY,
            leading=13,
            spaceAfter=6,
        ),
        "bullet": ParagraphStyle(
            "bullet",
            fontName="Helvetica",
            fontSize=9.5,
            textColor=BODY,
            leading=13,
            leftIndent=12,
            spaceAfter=4,
        ),
        "callout": ParagraphStyle(
            "callout",
            fontName="Helvetica",
            fontSize=9,
            textColor=BODY,
            leading=12.5,
            backColor=CARD_ACCENT,
            borderPadding=8,
            spaceAfter=10,
        ),
        "callout_warn": ParagraphStyle(
            "callout_warn",
            fontName="Helvetica",
            fontSize=9,
            textColor=BODY,
            leading=12.5,
            backColor=WARN_BG,
            borderPadding=8,
            spaceAfter=10,
        ),
        "answer": ParagraphStyle(
            "answer",
            fontName="Helvetica",
            fontSize=10,
            textColor=WHITE,
            leading=14,
            backColor=INK,
            borderPadding=10,
            spaceAfter=10,
        ),
        "footer": ParagraphStyle(
            "footer",
            fontName="Helvetica-Oblique",
            fontSize=8,
            textColor=MUTED,
            leading=10,
        ),
        "th": ParagraphStyle(
            "th",
            fontName="Helvetica-Bold",
            fontSize=8,
            textColor=WHITE,
            leading=10,
            alignment=TA_LEFT,
        ),
        "td": ParagraphStyle(
            "td",
            fontName="Helvetica",
            fontSize=8,
            textColor=BODY,
            leading=10.5,
        ),
        "td_center": ParagraphStyle(
            "td_center",
            fontName="Helvetica",
            fontSize=8,
            textColor=BODY,
            leading=10.5,
            alignment=TA_CENTER,
        ),
        "td_right": ParagraphStyle(
            "td_right",
            fontName="Helvetica",
            fontSize=8,
            textColor=BODY,
            leading=10.5,
            alignment=TA_RIGHT,
        ),
        "mono": ParagraphStyle(
            "mono",
            fontName="Courier",
            fontSize=8,
            textColor=BODY,
            leading=11,
            leftIndent=8,
        ),
    }


def cover_page(s):
    """Full-page cover using a background table."""
    content = [
        [Paragraph("CHAMBERQ", s["cover_brand"])],
        [Paragraph("Hospital &amp; clinic management on Laravel / Filament", s["cover_tag"])],
        [Spacer(1, 28 * mm)],
        [Paragraph("Full HMS Build Estimate &amp; Phased Roadmap", s["cover_title"])],
        [
            Paragraph(
                "How long to build a 13-module Hospital Management System on the ChamberQ stack — "
                "measured velocity, bottom-up estimate, and module-by-module delivery plan.",
                s["cover_lead"],
            )
        ],
        [Spacer(1, 40 * mm)],
        [
            Paragraph(
                "<b>Prepared:</b> 2026-08-09<br/>"
                "<b>Status:</b> Planning only — no code changes proposed<br/>"
                "<b>Team:</b> Owner + 1 developer + 1 domain/QA<br/>"
                "<b>Delivery:</b> Module by module → test → production → next",
                s["cover_meta"],
            )
        ],
    ]
    t = Table(content, colWidths=[PAGE_W - 2 * MARGIN])
    t.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, -1), COVER_DARK),
                ("LEFTPADDING", (0, 0), (-1, -1), 22 * mm),
                ("RIGHTPADDING", (0, 0), (-1, -1), 22 * mm),
                ("TOPPADDING", (0, 0), (-1, 0), 28 * mm),
                ("BOTTOMPADDING", (0, -1), (-1, -1), 18 * mm),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
            ]
        )
    )
    return t


def styled_table(data, col_widths, header_rows=1):
    t = Table(data, colWidths=col_widths, repeatRows=header_rows)
    style_cmds = [
        ("GRID", (0, 0), (-1, -1), 0.5, LINE),
        ("VALIGN", (0, 0), (-1, -1), "TOP"),
        ("LEFTPADDING", (0, 0), (-1, -1), 5),
        ("RIGHTPADDING", (0, 0), (-1, -1), 5),
        ("TOPPADDING", (0, 0), (-1, -1), 4),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 4),
        ("BACKGROUND", (0, 0), (-1, header_rows - 1), INK),
        ("ROWBACKGROUNDS", (0, header_rows), (-1, -1), [WHITE, CARD]),
    ]
    t.setStyle(TableStyle(style_cmds))
    return t


def section_header(eyebrow, title, sub, s):
    return [
        Paragraph(eyebrow, s["eyebrow"]),
        Paragraph(title, s["h1"]),
        Paragraph(sub, s["subhead"]),
    ]


def build_story(s):
    usable = PAGE_W - 2 * MARGIN
    story = []

    # Cover
    story.append(cover_page(s))
    story.append(PageBreak())

    # Section 1 — Context
    story.extend(
        section_header(
            "SECTION 1",
            "Context",
            "ChamberQ is live with real paying clients. The next goal is a full Hospital Management System on the same stack.",
            s,
        )
    )
    story.append(
        Paragraph(
            "<b>Stack:</b> Laravel 12 / Filament 4 / stancl tenancy. Laravel 13 upgrade is explicitly deferred.",
            s["callout"],
        )
    )
    story.append(Paragraph("Competitor reference", s["h2"]))
    story.append(
        Paragraph(
            "Benchmark bid: <b>AGMM Soft → Moin Uddin Pain Solution, Chattogram</b>, dated 25 July 2026 — "
            "৳380,000 one-time + ৳12,000/month, 13 modules, claiming all modules within one month including training.",
            s["body"],
        )
    )
    story.append(Paragraph("Delivery model (confirmed)", s["h2"]))
    story.append(Paragraph("• <b>Module by module</b> — build, test in house, ship to production, then next.", s["bullet"]))
    story.append(Paragraph("• <b>Team:</b> owner + 1 developer + 1 domain/QA person.", s["bullet"]))
    story.append(
        Paragraph(
            "<b>Read the one-month claim correctly.</b> AGMM is implementing an existing product sold to "
            "Chittagong Medical College, Chevron Clinical Lab, Asian Specialized, and others. "
            "Building it took them years. Our comparison is <b>build-vs-build</b>.",
            s["callout_warn"],
        )
    )
    story.append(PageBreak())

    # Section 2 — Module comparison
    story.extend(
        section_header(
            "SECTION 2",
            "What the competitor is selling",
            "Every sub-module from the competitor PDF (pages 4–9), mapped against ChamberQ today.",
            s,
        )
    )
    mod_data = [
        [Paragraph("#", s["th"]), Paragraph("Module", s["th"]), Paragraph("Sub-modules", s["th"]), Paragraph("We have today", s["th"])],
        ["1", "Dashboard & Registration", "1", "partial"],
        ["2", "Diagnostic Department", "47", "none"],
        ["3", "Indoor Patient Dept (IPD)", "38", "~5 (visit records overlap)"],
        ["4", "Outdoor Patient Dept (OPD)", "10", "~7 — strongest area"],
        ["5", "Emergency", "8", "none"],
        ["6", "Accounts", "13", "none — zero money handling"],
        ["7", "Pharmacy", "40", "none (catalogue only, no stock)"],
        ["8", "Store (Inventory)", "24", "none"],
        ["9", "HR", "~12", "none"],
        ["10", "SMS", "1", "done — prepaid wallet, segment guard"],
        ["11", "Patient & Doctor Portal", "1", "done"],
        ["12", "MIS", "3", "~1 (booking KPIs, not financial)"],
        ["13", "Setting & Security", "7", "~3 (users, roles, organization)"],
        [Paragraph("<b>Total</b>", s["td"]), "", Paragraph("<b>~205</b>", s["td_center"]), Paragraph("<b>~28 (≈14%)</b>", s["td"])],
    ]
    cw = [usable * 0.06, usable * 0.28, usable * 0.14, usable * 0.52]
    mod_rows = []
    for row in mod_data:
        if isinstance(row[0], Paragraph):
            mod_rows.append(row)
        else:
            mod_rows.append(
                [
                    Paragraph(str(row[0]), s["td_center"]),
                    Paragraph(row[1], s["td"]),
                    Paragraph(str(row[2]), s["td_center"]),
                    Paragraph(row[3], s["td"]),
                ]
            )
    story.append(styled_table(mod_rows, cw))
    story.append(PageBreak())

    # Section 3 — Velocity
    story.extend(
        section_header(
            "SECTION 3",
            "Velocity baseline",
            "Measured from git log on this repo — not guessed.",
            s,
        )
    )
    vel_data = [
        [Paragraph("Metric", s["th"]), Paragraph("Value", s["th"])],
        ["First commit (c8b37ff)", "2026-07-25"],
        ["Latest commit", "2026-08-08"],
        ["Distinct working days", "10"],
        ["Commits", "81"],
        ["app/ LOC", "18,206"],
        ["Blade LOC", "9,874"],
        ["Migrations", "45"],
        ["Models", "26"],
        ["Filament resources", "15"],
        ["Panel pages", "49"],
        ["Services", "16"],
        ["Tests", "369"],
    ]
    vel_rows = [vel_data[0]]
    for row in vel_data[1:]:
        vel_rows.append([Paragraph(row[0], s["td"]), Paragraph(row[1], s["td"])])
    story.append(styled_table(vel_rows, [usable * 0.55, usable * 0.45]))
    story.append(
        Paragraph(
            "Also: three panels, hybrid path+domain multi-tenancy, production readiness gate, and live paying clients. "
            "This is the optimistic bound — greenfield, single-domain, no support load.",
            s["callout"],
        )
    )
    story.append(PageBreak())

    # Section 4 — Why slower
    story.extend(
        section_header(
            "SECTION 4",
            "Why HMS work is slower per screen",
            "ChamberQ greenfield velocity does not transfer 1:1 to hospital-grade modules.",
            s,
        )
    )
    reasons = [
        "<b>Financial correctness</b> is an invariant problem — ledgers need reconciliation tests, not feature tests.",
        "<b>Domain knowledge</b> we do not have — referral commissions, BD hospital accounting. Largest schedule risk; domain/QA hire retires it.",
        "<b>Live clients</b> — HMS migrations touch shared tables; regression and support compete for build hours.",
        "<b>Data migration</b> from whatever the client runs today.",
        "<b>Module-by-module shipping</b> adds release, live-DB migration, and pilot-support overhead per module.",
    ]
    for i, r in enumerate(reasons, 1):
        story.append(Paragraph(f"{i}. {r}", s["bullet"]))
    story.append(PageBreak())

    # Section 5 — Estimate
    story.extend(
        section_header(
            "SECTION 5",
            "The estimate",
            "Bottom-up, weighting ~205 sub-modules by real difficulty.",
            s,
        )
    )
    est_data = [
        [Paragraph("Weight", s["th"]), Paragraph("Count", s["th"]), Paragraph("Rate", s["th"]), Paragraph("Dev-days", s["th"])],
        ["Trivial CRUD setup", "~70", "5/day", "14"],
        ["Moderate transactional", "~85", "2/day", "43"],
        ["Heavy engine work", "~50", "0.5/day", "100"],
        [Paragraph("<b>Build subtotal</b>", s["td"]), "", "", Paragraph("<b>157</b>", s["td_right"])],
        ["Domain discovery & rework (dev share)", "", "", "15"],
        ["Data migration + import tooling", "", "", "10"],
        ["Hardening, permissions, MySQL validation", "", "", "15"],
        ["Bangla staff-panel UI + training (dev share)", "", "", "6"],
        ["UAT / pilot fix loop (dev share)", "", "", "10"],
        ["Per-module release overhead (13 × ~2d)", "", "", "26"],
        [Paragraph("<b>Total</b>", s["td"]), "", "", Paragraph("<b>≈ 239</b>", s["td_right"])],
    ]
    est_rows = [est_data[0]]
    for row in est_data[1:]:
        if isinstance(row[0], Paragraph):
            est_rows.append(row)
        else:
            est_rows.append(
                [
                    Paragraph(row[0], s["td"]),
                    Paragraph(row[1], s["td_center"]),
                    Paragraph(row[2], s["td_center"]),
                    Paragraph(row[3], s["td_right"]),
                ]
            )
    ecw = [usable * 0.52, usable * 0.12, usable * 0.14, usable * 0.14]
    story.append(styled_table(est_rows, ecw))
    story.append(
        Paragraph(
            "Two developers parallelize at ~1.7×, not 2× → <b>≈ 140 working days elapsed</b>.",
            s["body"],
        )
    )
    cadence = [
        [Paragraph("Cadence", s["th"]), Paragraph("Elapsed", s["th"])],
        ["5 focused days/week", "~28 weeks ≈ 6.5 months"],
        ["4 focused days/week (realistic)", "~35 weeks ≈ 8 months"],
    ]
    cad_rows = [cadence[0]]
    for row in cadence[1:]:
        cad_rows.append([Paragraph(row[0], s["td"]), Paragraph(row[1], s["td"])])
    story.append(styled_table(cad_rows, [usable * 0.55, usable * 0.45]))
    story.append(
        Paragraph(
            "<b>Answer: 7–9 months</b> to all 13 modules with the 3-person team, shipping module by module. "
            "First sellable module in <b>~2–3 months</b>.",
            s["answer"],
        )
    )
    story.append(
        Paragraph(
            "Second method (per-module sums: 47 weeks sequential, ~34 with parallel tracks) lands in the same place.",
            s["body"],
        )
    )
    story.append(PageBreak())

    # Section 6 — Roadmap
    story.extend(
        section_header(
            "SECTION 6",
            "Module-by-module roadmap",
            "Build → in-house test → production → next. Ordering by dependency and sellability.",
            s,
        )
    )
    road = [
        [Paragraph("#", s["th"]), Paragraph("Module", s["th"]), Paragraph("Weeks", s["th"]), Paragraph("Why here", s["th"])],
        ["1", "Settings & Security + Audit Log", "2", "Prerequisites for every financial screen; audit before money"],
        ["2", "OPD completion — Invoice, Cash Counter, Policy", "3", "First money on ground; validates billing primitives"],
        ["3", "Diagnostic Department", "8", "Largest module; biggest seller in BD market"],
        ["4", "Accounts (double-entry core)", "6", "Needs phases 2–3 generating real money"],
        ["5", "Pharmacy", "7", "Stock ledger engine — build once here"],
        ["6", "Store (Inventory)", "4", "Second face on phase 5; parallel with phase 4"],
        ["7", "IPD", "9", "Heaviest clinical; depends on 3, 4, 5"],
        ["8", "Emergency", "2", "Thin layer over IPD + billing"],
        ["9", "HR", "4", "Standalone; parallel with phase 7"],
        ["10", "SMS + Portal extension", "1", "Extend existing work"],
        ["11", "Unified Dashboard & Registration", "1", "Built last — unify what exists"],
        [Paragraph("<b>Total</b>", s["td"]), Paragraph("Sequential: <b>47 weeks</b>", s["td"]), Paragraph("~34 w/ parallel", s["td_center"]), Paragraph("Phases 6 & 9 parallel", s["td"])],
    ]
    rcw = [usable * 0.06, usable * 0.34, usable * 0.1, usable * 0.5]
    road_rows = [road[0]]
    for row in road[1:]:
        if isinstance(row[0], Paragraph):
            road_rows.append(row)
        else:
            road_rows.append(
                [
                    Paragraph(str(row[0]), s["td_center"]),
                    Paragraph(row[1], s["td"]),
                    Paragraph(str(row[2]), s["td_center"]),
                    Paragraph(row[3], s["td"]),
                ]
            )
    story.append(styled_table(road_rows, rcw))
    story.append(PageBreak())

    # Section 7 — Architecture
    story.extend(
        section_header(
            "SECTION 7",
            "Architectural decisions this plan forces",
            "Expensive to change later. Log in decisions.md when chosen — before code lands.",
            s,
        )
    )
    arch = [
        ("Ledgers, not mutable columns", "Append-only accounting and stock ledgers with balanced-entry invariants at a single write path — same lesson as GsmText inside SmsService::send()."),
        ("Shared-DB multi-tenancy (~150 tables)", "Stay shared-DB; invest in tenant-scope enforcement. Per-tenant DB breaks ResearchDataService and SellerOverviewService."),
        ("Bangla staff panel", "2026-08-08 made admin English-only. Ward/store/pharmacy staff need Bangla — hundreds of strings if extended like PatientFacingBanglaTest."),
        ("Real queue worker required", "SendDoctorLateNotices uses afterResponse() because no worker runs. Stock revaluation and period close cannot."),
        ("Clinical media backup", "Local disk, no off-server backup. Must close before IPD (phase 7), ideally before Diagnostic (phase 3)."),
        ("Laravel 13", "Not upgrading. Write upgrade-clean code over the 8-month build."),
    ]
    for title, text in arch:
        story.append(Paragraph(title, s["h3"]))
        story.append(Paragraph(text, s["body"]))
    story.append(PageBreak())

    # Section 8 — Commercial
    story.extend(
        section_header(
            "SECTION 8",
            "Commercial note",
            "",
            s,
        )
    )
    story.append(
        Paragraph(
            "Bid: <b>৳380,000 one-time + ৳12,000/month</b> ≈ $3,100 + $100/mo.",
            s["body"],
        )
    )
    story.append(
        Paragraph(
            "7–9 months at that price only works as <b>SaaS across many centres</b> — our multi-tenant platform. "
            "AGMM appears per-install with denied client DB access.",
            s["body"],
        )
    )
    story.append(
        Paragraph(
            "<b>That asymmetry is the real competitive position</b> — worth more than feature parity.",
            s["callout"],
        )
    )
    story.append(Paragraph("Verification (reproducible)", s["h2"]))
    for cmd in [
        "git log --reverse --format=\"%ad\" --date=short | head -1",
        "git log --all --format=\"%ad\" --date=short | sort -u | wc -l",
        "find app -name \"*.php\" | xargs wc -l | tail -1",
        "grep -rh \"public function test\" tests/ | wc -l",
    ]:
        story.append(Paragraph(cmd, s["mono"]))
    story.append(Spacer(1, 12))
    story.append(
        Paragraph(
            "ChamberQ — Full HMS Build Estimate & Phased Roadmap · Prepared 2026-08-09 · Planning only",
            s["footer"],
        )
    )

    return story


def main() -> None:
    s = styles()
    doc = SimpleDocTemplate(
        str(OUT),
        pagesize=A4,
        leftMargin=MARGIN,
        rightMargin=MARGIN,
        topMargin=18 * mm,
        bottomMargin=16 * mm,
        title="Full HMS Build Estimate & Phased Roadmap",
        author="ChamberQ",
    )
    doc.build(build_story(s))
    print(f"Written: {OUT}")


if __name__ == "__main__":
    main()
