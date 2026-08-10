#!/usr/bin/env python3
"""Build portrait A4 competitor comparison for doctor proposals + standalone PDF.

- Injects comparison sheets into Dr-Shamim / Dr-Sharfuddin HTML proposals
- Writes docs/slides/ChamberQ-Competitor-Comparison-v3-portrait.pdf
- Doctor proposals get ChamberQ *strengths* only (no gaps table)
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER, TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import mm
from reportlab.platypus import (
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

ROOT = Path(__file__).resolve().parents[2]
SLIDES = ROOT / "docs" / "slides"
PROPOSALS = ROOT / "docs" / "proposals"

import importlib.util

sys.path.insert(0, str(SLIDES))
from competitor_comparison_data_v3 import (  # noqa: E402
    COMPETITORS_V3,
    CQ_FEATURE_FOOTNOTES,
    FEATURES_V3,
    FOOTNOTE_LINES_V3,
    GAP_FEATURES_V3,
    N,
    P,
    Y,
)

_spec = importlib.util.spec_from_file_location(
    "build_competitor_comparison",
    SLIDES / "build-competitor-comparison.py",
)
_bcc = importlib.util.module_from_spec(_spec)
assert _spec.loader is not None
_spec.loader.exec_module(_bcc)

ICON_GREEN = _bcc.ICON_GREEN
ICON_RED = _bcc.ICON_RED
cq_cell = _bcc.cq_cell
make_styles = _bcc.make_styles
normalize_status = _bcc.normalize_status
status_cell_from_value = _bcc.status_cell_from_value
style_table = _bcc.style_table

OUT_PDF = SLIDES / "ChamberQ-Competitor-Comparison-v3-portrait.pdf"
PAGE_W, PAGE_H = A4
MARGIN = 12 * mm

SHORT = {
    "eDoctorBD": "eDoctor",
    "ProtonEMR": "Proton",
    "PrescriptionSoftwareBD": "RxSWBD",
    "Bissoy Serial": "Bissoy",
    "Doctors Care": "Drs Care",
    "DPAS (Solvers)": "DPAS",
}

# Doctor proposals: drop last two distant rivals (Doctors Care, DPAS).

PROPOSAL_COMPETITORS = COMPETITORS_V3[:4]

# One-page sales chart: strongest Solo/Maestro differentiators only (readable type).
# Labels shortened for print; data keys still match FEATURES_V3 / CQ footnotes.
PROPOSAL_FEATURES = [
    (
        "Your front door",
        [
            ("Portfolio website under your name", {
                "edoctor": Y, "proton": N, "rxswbd": N, "bissoy": N,
            }),
            ("Page builder — hero, FAQ, Book", {
                "edoctor": 35, "proton": N, "rxswbd": N, "bissoy": N,
            }),
        ],
    ),
    (
        "Booking & serials",
        [
            ("Online serial booking", {
                "edoctor": Y, "proton": Y, "rxswbd": Y, "bissoy": 40,
            }),
            ("Session capacity / seat limits", {
                "edoctor": Y, "proton": Y, "rxswbd": 40, "bissoy": 35,
            }),
            ("Multi-chamber schedules", {
                "edoctor": Y, "proton": Y, "rxswbd": Y, "bissoy": N,
            }),
            ("Household picker (same phone)", {
                "edoctor": N, "proton": N, "rxswbd": N, "bissoy": N,
            }),
            ("Walk-ins + online in one queue", {
                "edoctor": 50, "proton": Y, "rxswbd": 40, "bissoy": Y,
            }),
            ("Day off — auto-cancel + patient notice", {
                "edoctor": 45, "proton": 40, "rxswbd": 35, "bissoy": N,
            }),
        ],
    ),
    (
        "Queue & waiting room",
        [
            ("Live outdoor / TV queue screen", {
                "edoctor": 40, "proton": Y, "rxswbd": N, "bissoy": Y,
            }),
            ("Voice serial call-out", {
                "edoctor": N, "proton": 35, "rxswbd": N, "bissoy": Y,
            }),
            ("Shareable ticket + WhatsApp", {
                "edoctor": 40, "proton": 45, "rxswbd": N, "bissoy": N,
            }),
            ("Live position + wait estimate", {
                "edoctor": N, "proton": 40, "rxswbd": N, "bissoy": N,
            }),
            ('"Doctor is late" SMS to waiting list', {
                "edoctor": N, "proton": N, "rxswbd": N, "bissoy": 35,
            }),
            ("Call next / arrived / in-chamber flow", {
                "edoctor": 50, "proton": Y, "rxswbd": 40, "bissoy": 40,
            }),
        ],
    ),
    (
        "Patient communication",
        [
            ("WhatsApp share — ticket / Rx (free)", {
                "edoctor": N, "proton": Y, "rxswbd": N, "bissoy": N,
            }),
            ("Patient portal — look up by phone", {
                "edoctor": Y, "proton": 45, "rxswbd": N, "bissoy": N,
            }),
            ("Booking confirmation SMS", {
                "edoctor": Y, "proton": Y, "rxswbd": 35, "bissoy": 40,
            }),
        ],
    ),
    (
        "Consult & prescriptions",
        [
            ("Digital prescriptions + print", {
                "edoctor": Y, "proton": Y, "rxswbd": Y, "bissoy": N,
            }),
            ("Consult screen while patient is in", {
                "edoctor": 50, "proton": 45, "rxswbd": N, "bissoy": N,
            }),
            ("Complete visit, then call next", {
                "edoctor": N, "proton": 40, "rxswbd": N, "bissoy": N,
            }),
            ("Staff can type paper Rx for doctor", {
                "edoctor": N, "proton": N, "rxswbd": N, "bissoy": N,
            }),
            ("Short prescription share link", {
                "edoctor": 40, "proton": Y, "rxswbd": N, "bissoy": N,
            }),
            ("Medicine list learns your favourites", {
                "edoctor": N, "proton": 50, "rxswbd": 35, "bissoy": N,
            }),
        ],
    ),
    (
        "Setup",
        [
            ("Done-with-you setup (not DIY only)", {
                "edoctor": Y, "proton": 45, "rxswbd": N, "bissoy": Y,
            }),
            ("Day reports — bookings, queue, completion", {
                "edoctor": 45, "proton": 40, "rxswbd": 35, "bissoy": 35,
            }),
        ],
    ),
]

# Footnotes that still apply to the curated chart
PROPOSAL_FOOTNOTES = [
    "† Multi-chamber: up to 5 on Solo / Maestro. · Booking SMS: prepaid wallet.",
]

# Map short labels → original footnote keys where needed
PROPOSAL_FOOTNOTE_KEYS = {
    "Multi-chamber schedules": "Multi-chamber schedules (different days/locations)",
    "Booking confirmation SMS": "Booking confirmation SMS (prepaid wallet)",
}


PROPOSAL_FILES = [
    PROPOSALS / "Dr-Shamim-Ahmed-ChamberQ-Proposal.html",
    PROPOSALS / "Dr-Sharfuddin-Mahmood-ChamberQ-Proposal.html",
]

CMP_CSS = """
  /* —— Competitor comparison (1 page, readable) —— */
  .sheet-cmp { padding-bottom: 4mm; }
  .sheet-cmp .eyebrow { margin-bottom: 8px; }
  .sheet-cmp h1 { font-size: 26pt; margin-bottom: 6px; }
  .sheet-cmp .subhead {
    font-size: 9.5pt;
    margin-bottom: 12px;
    line-height: 1.4;
    max-width: none;
  }
  .cmp-yes { color: #00C853; font-weight: 700; }
  .cmp-no { color: #FF1744; font-weight: 700; }
  .cmp-part { color: #E65100; font-weight: 700; font-size: 7.5pt; }
  .cmp-wrap { width: 100%; overflow: hidden; }
  table.cmp {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    font-size: 7.6pt;
    line-height: 1.22;
  }
  table.cmp col.cmp-feat-col { width: 40%; }
  table.cmp col.cmp-comp-col { width: 12%; }
  table.cmp th, table.cmp td {
    border: 0.4pt solid #d5dde8;
    padding: 4px 3px;
    vertical-align: middle;
    text-align: center;
  }
  table.cmp th {
    background: #0E1954;
    color: #fff;
    font-weight: 700;
    font-size: 7.2pt;
    line-height: 1.2;
    padding: 6px 3px;
  }
  table.cmp th.cmp-cq { background: #1B2978; }
  table.cmp td.cmp-feat {
    text-align: left;
    font-size: 7.6pt;
    color: #1A1F36;
    padding-left: 7px;
    padding-right: 5px;
  }
  table.cmp tr.cmp-sec td {
    background: #D8E6F5;
    text-align: left;
    font-weight: 700;
    color: #1B2978;
    font-size: 7.6pt;
    letter-spacing: 0.02em;
    padding: 5px 7px;
  }
  table.cmp tr.cmp-alt td.cmp-feat { background: #F4F6FA; }
  table.cmp td.bg-y { background: #E8F8EA; }
  table.cmp td.bg-n { background: #FDECEE; }
  table.cmp td.bg-p { background: #FFF9C4; }
  table.cmp .cmp-yes,
  table.cmp .cmp-no { font-size: 11pt; line-height: 1; }
  table.cmp .cmp-note {
    display: inline;
    font-size: 6.5pt;
    font-weight: 400;
    color: #555;
    margin-left: 3px;
  }
  .cmp-foot {
    font-size: 7.5pt;
    color: var(--muted);
    font-style: italic;
    margin: 10px 0 0;
    line-height: 1.35;
  }
"""


def cell_html(value, *, cq: bool = False, feat: str = "", gaps: bool = False) -> tuple[str, str]:
    """Return (inner_html, bg_class)."""
    if cq:
        if gaps:
            return '<span class="cmp-no">✗</span>', "bg-n"
        key = PROPOSAL_FOOTNOTE_KEYS.get(feat, feat)
        note = CQ_FEATURE_FOOTNOTES.get(key, "")
        inner = '<span class="cmp-yes">✓</span>'
        if note:
            # shorten † text for one-pager
            if "up to 5" in note:
                note = "† up to 5"
            inner += f'<span class="cmp-note">{note}</span>'
        return inner, "bg-y"

    kind, pct = normalize_status(value)
    if kind == Y:
        return '<span class="cmp-yes">✓</span>', "bg-y"
    if kind == P:
        return f'<span class="cmp-part">Part. {pct}%</span>', "bg-p"
    return '<span class="cmp-no">✗</span>', "bg-n"


def html_table(sections, *, gaps: bool = False, competitors=None) -> str:
    competitors = competitors if competitors is not None else PROPOSAL_COMPETITORS
    heads = ["Feature", "ChamberQ"] + [SHORT.get(label, label) for label, _ in competitors]
    n_comp_cols = 1 + len(competitors)  # ChamberQ + rivals
    colgroup = (
        '<colgroup><col class="cmp-feat-col">'
        + "".join('<col class="cmp-comp-col">' for _ in range(n_comp_cols))
        + "</colgroup>"
    )
    thead = "<tr>" + "".join(
        f'<th class="{"cmp-cq" if i == 1 else ""}">{h}</th>' for i, h in enumerate(heads)
    ) + "</tr>"

    body = []
    data_i = 0
    for section_title, items in sections:
        body.append(
            f'<tr class="cmp-sec"><td colspan="{len(heads)}">{section_title}</td></tr>'
        )
        for feat_label, comp_map in items:
            data_i += 1
            alt = " cmp-alt" if data_i % 2 == 0 else ""
            cq_inner, cq_bg = cell_html(None, cq=True, feat=feat_label, gaps=gaps)
            cells = [f'<td class="cmp-feat">{feat_label}</td>', f'<td class="{cq_bg}">{cq_inner}</td>']
            for _, key in competitors:
                inner, bg = cell_html(comp_map[key], gaps=False)
                cells.append(f'<td class="{bg}">{inner}</td>')
            body.append(f'<tr class="{alt.strip()}">' + "".join(cells) + "</tr>")

    return (
        '<div class="cmp-wrap"><table class="cmp">'
        f"{colgroup}<thead>{thead}</thead><tbody>{''.join(body)}</tbody></table></div>"
    )


def proposal_comparison_pages(start_page: int) -> tuple[str, int]:
    """Return HTML for comparison sheets and the next page number after them."""
    # One readable portrait page — curated strengths only
    pages_spec = [
        ("How ChamberQ compares", PROPOSAL_FEATURES, True),
    ]
    parts = []
    page = start_page
    for title, sections, first in pages_spec:
        intro = ""
        if first:
            intro = (
                '<p class="subhead">ChamberQ vs tools doctors often hear about — '
                "<strong>eDoctorBD</strong>, <strong>ProtonEMR</strong>, RxSWBD, Bissoy. "
                '<span class="cmp-yes">✓</span> yes &nbsp;·&nbsp; '
                '<span class="cmp-no">✗</span> no &nbsp;·&nbsp; '
                '<span class="cmp-part">Part.</span> partial</p>'
            )
        else:
            intro = ""

        foot_notes = ""
        if page == start_page + len(pages_spec) - 1:
            foot_notes = (
                '<p class="cmp-foot">'
                + " · ".join(PROPOSAL_FOOTNOTES)
                + "</p>"
            )

        parts.append(
            f"""<!-- ===================== COMPARISON {page} ===================== -->
<section class="sheet sheet-cmp">
  <p class="eyebrow">Comparison</p>
  <h1>{title}</h1>
  {intro}
  {html_table(sections)}
  {foot_notes}
  <div class="footer"><span>ChamberQ · MAESTRO</span><span>Page {page}</span></div>
</section>
"""
        )
        page += 1
    return "\n".join(parts), page


def inject_css(html: str) -> str:
    # Always refresh comparison CSS so column widths / rivals stay in sync.
    if "/* —— Competitor comparison" in html:
        return re.sub(
            r'\n  /\* —— Competitor comparison.*?(?=\n  /\* Content pages: black & white|\n</style>)',
            CMP_CSS,
            html,
            count=1,
            flags=re.S,
        )
    return html.replace("</style>\n</head>", CMP_CSS + "\n</style>\n</head>", 1)


def renumber_from_investment(html: str, invest_page: int) -> str:
    """Renumber footers from Investment onward."""
    html = re.sub(
        r'(<!-- ===================== SECTION [78] ===================== -->.*?<span>Page )\d+(</span>)',
        rf'\g<1>{invest_page}\2',
        html,
        count=1,
        flags=re.S,
    )
    html = re.sub(
        r'(<!-- ===================== CLOSING ===================== -->.*?<span>Page )\d+(</span>)',
        rf'\g<1>{invest_page + 1}\2',
        html,
        count=1,
        flags=re.S,
    )
    return html


def inject_proposal(path: Path) -> None:
    html = path.read_text(encoding="utf-8")
    html = inject_css(html)

    # Remove previous comparison blocks if re-run
    html = re.sub(
        r'\n<!-- ===================== COMPARISON \d+ ===================== -->.*?</section>\n',
        "\n",
        html,
        flags=re.S,
    )

    # Comparison starts at page 9 (after Kept simple = page 8)
    cmp_html, next_page = proposal_comparison_pages(9)

    marker = "<!-- ===================== SECTION 8 ===================== -->"
    if marker not in html:
        marker = "<!-- ===================== SECTION 7 ===================== -->"
    if marker not in html:
        raise SystemExit(f"Investment marker missing in {path.name}")
    if "<!-- ===================== COMPARISON" not in html:
        html = html.replace(marker, cmp_html + "\n" + marker)

    html = renumber_from_investment(html, next_page)

    # Mention comparison in intro blurb if present
    old_blurb = (
        "Inside: your digital front desk, a morning with your patients, how your desk and consult "
        "screen work together, how prescriptions go home, what we build with you, and clear Maestro pricing."
    )
    new_blurb = (
        "Inside: your digital front desk, a morning with your patients, how your desk and consult "
        "screen work together, how prescriptions go home, what we build with you, how ChamberQ "
        "compares, and clear Maestro pricing."
    )
    if "Sharfuddin" in path.name:
        old_blurb = old_blurb.replace("a morning", "an evening")
        new_blurb = new_blurb.replace("a morning", "an evening")
    html = html.replace(old_blurb, new_blurb)

    path.write_text(html, encoding="utf-8")
    print(f"updated → {path}")


def build_table_data_portrait(sections, styles, *, gaps: bool = False):
    competitors = COMPETITORS_V3
    n_comp = len(competitors)
    usable_w = PAGE_W - 2 * MARGIN
    feat_w = 48 * mm
    comp_w = (usable_w - feat_w) / (1 + n_comp)
    col_widths = [feat_w] + [comp_w] * (1 + n_comp)

    header = [Paragraph("Feature", styles["head"])]
    header.append(Paragraph("ChamberQ", styles["head_cq"]))
    for label, _ in competitors:
        short = SHORT.get(label, label)
        header.append(Paragraph(short.replace(" ", "<br/>"), styles["head"]))

    rows = [header]
    row_meta = [(0, "header")]

    for section_title, items in sections:
        sec_row = [Paragraph(f"<b>{section_title}</b>", styles["section"])]
        sec_row.extend([""] * (1 + n_comp))
        rows.append(sec_row)
        row_meta.append((len(rows) - 1, "section"))

        for feat_label, comp_map in items:
            row = [
                Paragraph(feat_label, styles["feat"]),
                cq_cell(styles, v2=True, feat_label=feat_label, gaps=gaps),
            ]
            for _, key in competitors:
                row.append(status_cell_from_value(comp_map[key], styles, v2=True, v3=True))
            rows.append(row)
            row_meta.append((len(rows) - 1, "data"))

    return rows, col_widths, row_meta


def write_portrait_pdf() -> None:
    styles = make_styles(v2=True)
    # Tighten for portrait
    styles["feat"] = ParagraphStyle(
        "feat_p",
        parent=styles["feat"],
        fontSize=6.5,
        leading=8,
    )
    styles["head"] = ParagraphStyle(
        "head_p",
        parent=styles["head"],
        fontSize=6.2,
        leading=7.5,
        alignment=TA_CENTER,
    )
    styles["head_cq"] = ParagraphStyle(
        "head_cq_p",
        parent=styles["head_cq"],
        fontSize=6.2,
        leading=7.5,
        alignment=TA_CENTER,
    )
    styles["section"] = ParagraphStyle(
        "section_p",
        parent=styles["section"],
        fontSize=7,
        leading=9,
    )
    styles["cell_part"] = ParagraphStyle(
        "cell_part_p",
        parent=styles["cell_part"],
        fontSize=5.5,
        leading=7,
    )
    styles["cell_icon"] = ParagraphStyle(
        "cell_icon_p",
        parent=styles["cell_icon"],
        fontSize=11,
        leading=13,
    )

    story = []
    story.append(Paragraph("INTERNAL SALES · BANGLADESH CHAMBER SOFTWARE", styles["eyebrow"]))
    story.append(Paragraph("ChamberQ — honest feature comparison (v3 · portrait)", styles["h1"]))
    story.append(
        Paragraph(
            "Our strengths only, plus a later table for gaps we can add within ~2 months. "
            "Rivals: <b>2 mid-close</b> (eDoctorBD, ProtonEMR) + <b>4 distant</b>. "
            f'<font color="{ICON_GREEN}"><b>✓</b></font> · '
            f'<font color="{ICON_RED}"><b>✗</b></font> · '
            "<b>Partial N%</b> (max 60%). A4 portrait.",
            styles["sub"],
        )
    )

    chunks = [FEATURES_V3[:2], FEATURES_V3[2:4], FEATURES_V3[4:]]
    for i, chunk in enumerate(chunks):
        if i:
            story.append(PageBreak())
            story.append(Paragraph("ChamberQ — our strengths (continued)", styles["h1"]))
        rows, widths, meta = build_table_data_portrait(chunk, styles)
        t = Table(rows, colWidths=widths, repeatRows=1)
        style_table(t, meta, v2=True)
        story.append(t)

    story.append(PageBreak())
    story.append(Paragraph("<b>What rivals have — not in ChamberQ today</b>", styles["h1"]))
    story.append(
        Paragraph(
            "Straightforward to ship within ~2 months if a doctor asks. "
            "ChamberQ column is ✗ for all rows below.",
            styles["sub"],
        )
    )
    grows, gw, gm = build_table_data_portrait(GAP_FEATURES_V3, styles, gaps=True)
    tg = Table(grows, colWidths=gw, repeatRows=1)
    style_table(tg, gm, v2=True)
    story.append(tg)
    story.append(Spacer(1, 6))
    for line in FOOTNOTE_LINES_V3:
        story.append(Paragraph(line, styles["foot"]))

    doc = SimpleDocTemplate(
        str(OUT_PDF),
        pagesize=A4,
        leftMargin=MARGIN,
        rightMargin=MARGIN,
        topMargin=10 * mm,
        bottomMargin=10 * mm,
        title="ChamberQ — Competitive Comparison v3 (portrait)",
        author="ChamberQ",
    )
    doc.build(story)
    print(f"written → {OUT_PDF}")


def md_comparison_block() -> str:
    lines = [
        "## How ChamberQ compares",
        "",
        "Honest feature look vs tools doctors in Bangladesh often hear about "
        "(eDoctorBD, ProtonEMR, RxSWBD, Bissoy Serial, Doctors Care, DPAS).",
        "",
        "Legend: **Yes** · **No** · **Partial N%** (max 60%). Full portrait chart is in the HTML proposal "
        "(and `docs/slides/ChamberQ-Competitor-Comparison-v3-portrait.pdf`).",
        "",
        "Highlights where ChamberQ stands out for a solo chamber:",
        "",
        "- Branded portfolio website + page builder",
        "- Household picker (same phone, pick who the visit is for)",
        "- Live outdoor / TV queue screen + voice call-out",
        "- Shareable ticket with live position / wait estimate",
        "- “Doctor is late” SMS to everyone waiting",
        "- WhatsApp share for ticket / cancel / prescription (free)",
        "- Consult screen + complete visit before calling next",
        "- Staff can type a paper prescription for the doctor",
        "",
        "† Bangla homepage: paid add-on · Lab-at-booking: Clinic only · "
        "Booking SMS: prepaid wallet · Multi-chamber: up to 5 on Solo/Maestro.",
        "",
    ]
    return "\n".join(lines)


def inject_markdown(path: Path) -> None:
    text = path.read_text(encoding="utf-8")
    block = md_comparison_block()
    if "## How ChamberQ compares" in text:
        text = re.sub(
            r"## How ChamberQ compares\n.*?(?=\n## )",
            block + "\n",
            text,
            count=1,
            flags=re.S,
        )
    else:
        text = text.replace("\n## 7. Investment\n", "\n" + block + "\n## 7. Investment\n")
        # Renumber 7→8 if we inserted before Investment — keep Investment as 7 and shift?
        # Better: insert as new section before Investment and renumber
        text = text.replace("## 7. Investment", "## 8. Investment")
        text = text.replace("## 8. How we go live together", "## 9. How we go live together")
        text = text.replace("## 9. Whenever you are ready", "## 10. Whenever you are ready")
        # Fix double-renumber if already updated
        text = text.replace("## 9. How we go live together", "## 9. How we go live together")
    # If section was inserted without renumbering first time
    if "## How ChamberQ compares" in text and "## 7. Investment" in text:
        text = text.replace("## 7. Investment", "## 8. Investment")
        text = text.replace("## 8. How we go live", "## 9. How we go live")
        text = text.replace("## 9. Whenever you are ready", "## 10. Whenever you are ready")
    path.write_text(text, encoding="utf-8")
    print(f"updated → {path}")


if __name__ == "__main__":
    write_portrait_pdf()
    for p in PROPOSAL_FILES:
        inject_proposal(p)
    for p in [
        PROPOSALS / "Dr-Shamim-Ahmed-ChamberQ-Proposal.md",
        PROPOSALS / "Dr-Sharfuddin-Mahmood-ChamberQ-Proposal.md",
    ]:
        inject_markdown(p)
