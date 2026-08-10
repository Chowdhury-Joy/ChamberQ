#!/usr/bin/env python3
"""Generate ChamberQ competitor comparison PDFs (ChamberQ strengths only).

Outputs:
  ChamberQ-Competitor-Comparison.pdf      — v1: Yes/No text + pricing
  ChamberQ-Competitor-Comparison-v2.pdf   — v2: ✓/✗ icons, Part. text, tinted cells, no pricing
  ChamberQ-Competitor-Comparison-v3.pdf   — v3: honest mix, partial %, gaps, footnotes
"""

from pathlib import Path
from typing import Optional

from reportlab.lib import colors
from reportlab.lib.enums import TA_CENTER
from reportlab.lib.pagesizes import landscape, letter
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib.units import inch
from reportlab.platypus import (
    PageBreak,
    Paragraph,
    SimpleDocTemplate,
    Spacer,
    Table,
    TableStyle,
)

from competitor_comparison_data import COMPETITORS, FEATURES, PRICING_ROW, N, P, Y
from competitor_comparison_data_v3 import (
    COMPETITORS_V3,
    CQ_FEATURE_FOOTNOTES,
    FEATURES_V3,
    FOOTNOTE_LINES_V3,
    GAP_FEATURES_V3,
)

NAVY = colors.HexColor("#0E1954")
NAVY2 = colors.HexColor("#1B2978")
BLUE = colors.HexColor("#30A9E5")
INK = colors.HexColor("#1A1F36")
MUTED = colors.HexColor("#6B7399")
LINE = colors.HexColor("#E4E8F0")
ROWALT = colors.HexColor("#F4F6FA")
WHITE = colors.white

ICON_GREEN = "#00C853"
ICON_RED = "#FF1744"
TEXT_AMBER = "#E65100"

BG_YES = colors.HexColor("#C8E6C9")   # light green
BG_NO = colors.HexColor("#FFCDD2")    # light red
BG_PART = colors.HexColor("#FFF9C4")  # light yellow

HERE = Path(__file__).resolve().parent
OUT_V1 = HERE / "ChamberQ-Competitor-Comparison.pdf"
OUT_V2 = HERE / "ChamberQ-Competitor-Comparison-v2.pdf"
OUT_V3 = HERE / "ChamberQ-Competitor-Comparison-v3.pdf"

SOLO_SETUP, SOLO_MONTHLY = 15000, 3000
CLINIC_SETUP, CLINIC_MONTHLY = 75000, 7500

PAGE_W, PAGE_H = landscape(letter)
MARGIN = 0.45 * inch


def make_styles(*, v2: bool):
    return {
        "eyebrow": ParagraphStyle(
            "eyebrow", fontName="Helvetica-Bold", fontSize=9, textColor=BLUE, leading=11
        ),
        "h1": ParagraphStyle(
            "h1", fontName="Helvetica-Bold", fontSize=16, textColor=NAVY, leading=19, spaceAfter=2
        ),
        "sub": ParagraphStyle(
            "sub", fontName="Helvetica", fontSize=8.5, textColor=MUTED, leading=11, spaceAfter=3
        ),
        "section": ParagraphStyle(
            "section", fontName="Helvetica-Bold", fontSize=8, textColor=NAVY2, leading=10
        ),
        "feat": ParagraphStyle(
            "feat", fontName="Helvetica", fontSize=7.5, textColor=INK, leading=9.5
        ),
        "head": ParagraphStyle(
            "head", fontName="Helvetica-Bold", fontSize=7.2, textColor=WHITE, leading=8.5,
            alignment=TA_CENTER,
        ),
        "head_cq": ParagraphStyle(
            "head_cq", fontName="Helvetica-Bold", fontSize=7.2, textColor=WHITE, leading=8.5,
            alignment=TA_CENTER,
        ),
        "cell_yes": ParagraphStyle(
            "cell_yes", fontName="Helvetica-Bold", fontSize=7.5,
            textColor=colors.HexColor(ICON_GREEN),
            alignment=TA_CENTER, leading=9,
        ),
        "cell_part": ParagraphStyle(
            "cell_part", fontName="Helvetica-Bold", fontSize=6.5,
            textColor=colors.HexColor(TEXT_AMBER),
            alignment=TA_CENTER, leading=8,
        ),
        "cell_no": ParagraphStyle(
            "cell_no", fontName="Helvetica-Bold", fontSize=7.5,
            textColor=colors.HexColor(ICON_RED),
            alignment=TA_CENTER, leading=9,
        ),
        "cell_icon": ParagraphStyle(
            "cell_icon", fontName="Helvetica-Bold", fontSize=14,
            textColor=colors.HexColor(ICON_GREEN),
            alignment=TA_CENTER, leading=16,
        ),
        "cell_price": ParagraphStyle(
            "cell_price", fontName="Helvetica", fontSize=7, textColor=INK,
            alignment=TA_CENTER, leading=8.5,
        ),
        "foot": ParagraphStyle(
            "foot", fontName="Helvetica-Oblique", fontSize=7.5, textColor=MUTED, leading=10
        ),
        "legend": ParagraphStyle(
            "legend", fontName="Helvetica", fontSize=7.5, textColor=MUTED, leading=10
        ),
    }


def check_icon() -> str:
    return f'<font color="{ICON_GREEN}" size="14"><b>✓</b></font>'


def cross_icon() -> str:
    return f'<font color="{ICON_RED}" size="14"><b>✗</b></font>'


def partial_label(pct: int) -> str:
    return f"Partial {pct}%"


def normalize_status(value) -> tuple:
    """Return (kind, pct) where kind is Y, P, or N."""
    if value == Y:
        return Y, None
    if value == N:
        return N, None
    if isinstance(value, int):
        return P, max(1, min(60, value))
    if value == P:
        return P, 40
    return N, None


def status_cell_from_value(value, styles: dict, *, v2: bool, v3: bool = False) -> Paragraph:
    kind, pct = normalize_status(value)
    if v2 or v3:
        if kind == Y:
            return Paragraph(check_icon(), styles["cell_icon"])
        if kind == P:
            label = partial_label(pct) if v3 else "Part."
            return Paragraph(label, styles["cell_part"])
        return Paragraph(cross_icon(), styles["cell_icon"])
    if kind == Y:
        return Paragraph("Yes", styles["cell_yes"])
    if kind == P:
        return Paragraph(partial_label(pct) if v3 else "Part.", styles["cell_part"])
    return Paragraph("No", styles["cell_no"])


def status_cell(status: str, styles: dict, *, v2: bool) -> Paragraph:
    return status_cell_from_value(status, styles, v2=v2, v3=False)


def cq_cell(styles: dict, *, v2: bool, feat_label: str = "", gaps: bool = False) -> Paragraph:
    if gaps:
        return Paragraph(cross_icon(), styles["cell_icon"])
    if v2:
        note = CQ_FEATURE_FOOTNOTES.get(feat_label, "") if feat_label else ""
        if note:
            return Paragraph(f'{check_icon()}<br/><font size="5" color="#555555">{note}</font>', styles["cell_icon"])
        return Paragraph(check_icon(), styles["cell_icon"])
    return Paragraph("Yes", styles["cell_yes"])


def cell_status_from_para(para: Paragraph) -> Optional[str]:
    """Return Y, P, N for styling v2/v3 backgrounds."""
    text = para.text if hasattr(para, "text") else str(para)
    if text == "Part." or text.startswith("Partial "):
        return P
    if "✓" in text:
        return Y
    if "✗" in text:
        return N
    if text == "Yes":
        return Y
    if text == "No":
        return N
    return None


def build_table_data_v3(sections, styles, *, gaps: bool = False):
    competitors = COMPETITORS_V3
    n_comp = len(competitors)
    usable_w = PAGE_W - 2 * MARGIN
    feat_w = 2.35 * inch
    comp_w = (usable_w - feat_w) / (1 + n_comp)
    col_widths = [feat_w] + [comp_w] * (1 + n_comp)

    header = [Paragraph("Feature", styles["head"])]
    header.append(Paragraph("ChamberQ", styles["head_cq"]))
    for label, _ in competitors:
        short = label.replace("PrescriptionSoftwareBD", "RxSWBD").replace(" (Solvers)", "")
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


def build_table_data(sections, styles, *, v2: bool, include_pricing: bool):
    n_comp = len(COMPETITORS)
    usable_w = PAGE_W - 2 * MARGIN
    feat_w = 2.5 * inch
    comp_w = (usable_w - feat_w) / (1 + n_comp)
    col_widths = [feat_w] + [comp_w] * (1 + n_comp)

    header = [Paragraph("Feature", styles["head"])]
    header.append(Paragraph("ChamberQ", styles["head_cq"]))
    for label, _ in COMPETITORS:
        short = label.replace("PrescriptionSoftwareBD", "RxSWBD").replace(" (Solvers)", "")
        header.append(Paragraph(short.replace(" ", "<br/>"), styles["head"]))

    rows = [header]
    row_meta = [(0, "header")]

    if include_pricing:
        for label, cq_price, prices in PRICING_ROW:
            row = [
                Paragraph(label, styles["feat"]),
                Paragraph(cq_price, styles["cell_price"]),
            ]
            for _, key in COMPETITORS:
                row.append(Paragraph(prices[key], styles["cell_price"]))
            rows.append(row)
            row_meta.append((len(rows) - 1, "price"))

    for section_title, items in sections:
        sec_row = [Paragraph(f"<b>{section_title}</b>", styles["section"])]
        sec_row.extend([""] * (1 + n_comp))
        rows.append(sec_row)
        row_meta.append((len(rows) - 1, "section"))

        for feat_label, comp_map in items:
            row = [Paragraph(feat_label, styles["feat"]), cq_cell(styles, v2=v2)]
            for _, key in COMPETITORS:
                row.append(status_cell(comp_map[key], styles, v2=v2))
            rows.append(row)
            row_meta.append((len(rows) - 1, "data"))

    return rows, col_widths, row_meta


def bg_for_status(status: Optional[str]):
    if status == Y:
        return BG_YES
    if status == P:
        return BG_PART
    if status == N:
        return BG_NO
    return None


def style_table(table, row_meta, *, v2: bool):
    cmds = [
        ("BACKGROUND", (0, 0), (-1, 0), NAVY),
        ("BACKGROUND", (1, 0), (1, 0), NAVY2),
        ("TEXTCOLOR", (0, 0), (-1, 0), WHITE),
        ("TOPPADDING", (0, 0), (-1, -1), 5),
        ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
        ("LEFTPADDING", (0, 0), (-1, -1), 4),
        ("RIGHTPADDING", (0, 0), (-1, -1), 4),
        ("VALIGN", (0, 0), (-1, -1), "MIDDLE"),
        ("ALIGN", (1, 0), (-1, -1), "CENTER"),
        ("BOX", (0, 0), (-1, -1), 0.75, NAVY2),
        ("LINEBELOW", (0, 0), (-1, -1), 0.35, LINE),
    ]

    data_row_idx = 0
    for ridx, kind in row_meta:
        if kind == "header":
            continue
        if kind == "section":
            cmds.append(("BACKGROUND", (0, ridx), (-1, ridx), colors.HexColor("#D8E6F5")))
            cmds.append(("SPAN", (0, ridx), (-1, ridx)))
            continue
        if kind == "price":
            cmds.append(("BACKGROUND", (0, ridx), (-1, ridx), colors.HexColor("#EEF2F8")))
            continue

        data_row_idx += 1
        if data_row_idx % 2 == 0:
            cmds.append(("BACKGROUND", (0, ridx), (0, ridx), ROWALT))

        for c in range(1, len(table._argW)):
            cell = table._cellvalues[ridx][c]
            if not hasattr(cell, "text"):
                continue
            status = cell_status_from_para(cell)
            if v2:
                bg = bg_for_status(status)
                if bg:
                    cmds.append(("BACKGROUND", (c, ridx), (c, ridx), bg))
            elif status == Y:
                cmds.append(("TEXTCOLOR", (c, ridx), (c, ridx), colors.HexColor(ICON_GREEN)))
            elif status == N:
                cmds.append(("TEXTCOLOR", (c, ridx), (c, ridx), colors.HexColor(ICON_RED)))
            elif status == P:
                cmds.append(("TEXTCOLOR", (c, ridx), (c, ridx), colors.HexColor(TEXT_AMBER)))

    table.setStyle(TableStyle(cmds))


def build_story(styles, *, v2: bool, include_pricing: bool):
    story = []
    story.append(Paragraph("INTERNAL SALES · BANGLADESH CHAMBER SOFTWARE", styles["eyebrow"]))
    title = (
        "ChamberQ — features we ship vs. low-overlap rivals (v2)"
        if v2
        else "ChamberQ — features we ship vs. low-overlap rivals"
    )
    story.append(Paragraph(title, styles["h1"]))

    if v2:
        story.append(
            Paragraph(
                "Only ChamberQ capabilities are listed. Rivals with the <b>least</b> overlap. "
                f'<font color="{ICON_GREEN}"><b>✓</b></font> on light green · '
                f'<font color="{ICON_RED}"><b>✗</b></font> on light red · '
                "<b>Part.</b> on light yellow. No pricing.",
                styles["sub"],
            )
        )
    else:
        story.append(
            Paragraph(
                "Only ChamberQ capabilities are listed. Rivals with the <b>least</b> overlap. "
                f'<font color="{ICON_GREEN}"><b>Yes</b></font> · '
                f'<font color="{TEXT_AMBER}"><b>Part.</b></font> · '
                f'<font color="{ICON_RED}"><b>No</b></font>. '
                "Closer rivals (eDoctorBD, Doctors Canvas, Reiva) omitted.",
                styles["sub"],
            )
        )

    page1 = FEATURES[:3]
    rows1, w1, m1 = build_table_data(page1, styles, v2=v2, include_pricing=include_pricing)
    t1 = Table(rows1, colWidths=w1, repeatRows=1)
    style_table(t1, m1, v2=v2)
    story.append(t1)

    story.append(PageBreak())

    story.append(Paragraph("ChamberQ — features we ship (continued)", styles["h1"]))
    if v2:
        story.append(
            Paragraph(
                f'<font color="{ICON_GREEN}"><b>✓</b></font> has it · '
                f'<font color="{ICON_RED}"><b>✗</b></font> missing · '
                "<b>Part.</b> partial",
                styles["sub"],
            )
        )
    page2 = FEATURES[3:]
    rows2, w2, m2 = build_table_data(page2, styles, v2=v2, include_pricing=False)
    t2 = Table(rows2, colWidths=w2, repeatRows=1)
    style_table(t2, m2, v2=v2)
    story.append(t2)

    story.append(Spacer(1, 6))
    if not v2:
        story.append(
            Paragraph(
                "<b>ChamberQ pricing:</b> Solo Tk {:,} setup / Tk {:,} per month. "
                "Clinic Tk {:,} setup / Tk {:,} per month. SMS prepaid (~Tk 0.50/credit).".format(
                    SOLO_SETUP, SOLO_MONTHLY, CLINIC_SETUP, CLINIC_MONTHLY
                ),
                styles["foot"],
            )
        )
    story.append(
        Paragraph(
            "<b>Pitch angle:</b> These products win on <i>price</i>, not the full patient journey. "
            "Point at the ✗ cells — no branded site, no ticket + ETA, no doctor-late SMS, "
            "no staff paper-Rx entry.",
            styles["legend"],
        )
    )
    return story


def write_pdf(path: Path, *, v2: bool, include_pricing: bool):
    styles = make_styles(v2=v2)
    doc = SimpleDocTemplate(
        str(path),
        pagesize=landscape(letter),
        leftMargin=MARGIN,
        rightMargin=MARGIN,
        topMargin=0.38 * inch,
        bottomMargin=0.35 * inch,
        title="ChamberQ — Competitive Comparison",
        author="ChamberQ",
    )
    doc.build(build_story(styles, v2=v2, include_pricing=include_pricing))
    print(f"written → {path}")


def build_story_v3(styles):
    story = []
    story.append(Paragraph("INTERNAL SALES · BANGLADESH CHAMBER SOFTWARE", styles["eyebrow"]))
    story.append(Paragraph("ChamberQ — honest feature comparison (v3)", styles["h1"]))
    story.append(
        Paragraph(
            "Our strengths only, plus a second table for gaps we can add within ~2 months. "
            "Rivals: <b>2 mid-close</b> (eDoctorBD, ProtonEMR) + <b>4 distant</b> (RxSWBD, Bissoy, "
            "Doctors Care, DPAS). "
            f'<font color="{ICON_GREEN}"><b>✓</b></font> · '
            f'<font color="{ICON_RED}"><b>✗</b></font> · '
            "<b>Partial N%</b> (max 60%).",
            styles["sub"],
        )
    )

    page1 = FEATURES_V3[:3]
    rows1, w1, m1 = build_table_data_v3(page1, styles)
    t1 = Table(rows1, colWidths=w1, repeatRows=1)
    style_table(t1, m1, v2=True)
    story.append(t1)

    story.append(PageBreak())

    story.append(Paragraph("ChamberQ — our strengths (continued)", styles["h1"]))
    page2 = FEATURES_V3[3:]
    rows2, w2, m2 = build_table_data_v3(page2, styles)
    t2 = Table(rows2, colWidths=w2, repeatRows=1)
    style_table(t2, m2, v2=True)
    story.append(t2)

    story.append(Spacer(1, 8))
    story.append(Paragraph("<b>What rivals have — not in ChamberQ today</b>", styles["h1"]))
    story.append(
        Paragraph(
            "Straightforward to ship within ~2 months if a doctor asks. "
            "ChamberQ column is ✗ for all rows below.",
            styles["sub"],
        )
    )
    grows, gw, gm = build_table_data_v3(GAP_FEATURES_V3, styles, gaps=True)
    tg = Table(grows, colWidths=gw, repeatRows=1)
    style_table(tg, gm, v2=True)
    story.append(tg)

    story.append(Spacer(1, 6))
    for line in FOOTNOTE_LINES_V3:
        story.append(Paragraph(line, styles["foot"]))
    story.append(
        Paragraph(
            "<b>Sales line:</b> We do not have the bottom table today — if you need billing, "
            "bKash at booking, or a doctor app, we can prioritise it on your timeline.",
            styles["legend"],
        )
    )
    return story


def write_pdf_v3(path: Path):
    styles = make_styles(v2=True)
    doc = SimpleDocTemplate(
        str(path),
        pagesize=landscape(letter),
        leftMargin=MARGIN,
        rightMargin=MARGIN,
        topMargin=0.38 * inch,
        bottomMargin=0.35 * inch,
        title="ChamberQ — Competitive Comparison v3",
        author="ChamberQ",
    )
    doc.build(build_story_v3(styles))
    print(f"written → {path}")


if __name__ == "__main__":
    write_pdf(OUT_V1, v2=False, include_pricing=True)
    write_pdf(OUT_V2, v2=True, include_pricing=False)
    write_pdf_v3(OUT_V3)
    print("Portrait A4 twin: python3 docs/proposals/build-portrait-comparison.py")
