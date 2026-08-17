#!/usr/bin/env python3
"""ChamberQ business card from the official logo lockup (teal #0f766e)."""

from __future__ import annotations

from pathlib import Path
from xml.sax.saxutils import escape

from fontTools.ttLib import TTCollection
from fontTools.pens.svgPathPen import SVGPathPen

ROOT = Path(__file__).resolve().parent

# Official ChamberQ teal (sampled from docs/proposals/assets/chamberq-logo.png)
INK = "#0f766e"
FRONT_BG = "#0f766e"
BACK_BG = "#ecf6f5"  # light tint already used in ChamberQ marketing
WHITE = "#ffffff"

TRIM_W, TRIM_H = 88.9, 50.8
BLEED = 3.0
PAGE_W, PAGE_H = TRIM_W + BLEED * 2, TRIM_H + BLEED * 2
CX, CY = PAGE_W / 2, PAGE_H / 2
SAFE = BLEED + 6.0

PHONE = "01818-614349"
EMAIL = "owner@chamberq.com"
ADDR1 = "Abu Raihan Villa, House 33, Lane-C"
ADDR2 = "Road-1, South Khulshi, Chattogram"

MARK = "chamberq-logomark.png"
MARK_WHITE = "chamberq-logomark-white.png"
WORD = "chamberq-wordmark.png"
MARK_ASPECT = 1704 / 1936
WORD_ASPECT = 5960 / 1024

HELVETICA = "/System/Library/Fonts/HelveticaNeue.ttc"


def helvetica(style: str = "Regular"):
    col = TTCollection(HELVETICA)
    for font in col.fonts:
        if str(font["name"].getDebugName(2)) == style:
            return font, font.getBestCmap(), font.getGlyphSet(), font["head"].unitsPerEm
    raise SystemExit(f"Helvetica Neue {style} not found")


def text_width(gs, cmap, upem: int, text: str, size_mm: float, tracking_em: float) -> float:
    scale = size_mm / upem
    x = 0.0
    for i, ch in enumerate(text):
        x += gs[cmap[ord(ch)]].width
        if i < len(text) - 1:
            x += tracking_em * upem
    return x * scale


def outlined_text(font_pack, text: str, size_mm: float, x: float, baseline: float, tracking_em: float, anchor: str) -> str:
    _, cmap, gs, upem = font_pack
    scale = size_mm / upem
    w = text_width(gs, cmap, upem, text, size_mm, tracking_em)
    origin = x - w / 2 if anchor == "middle" else x if anchor == "start" else x - w
    xg = 0.0
    chunks = []
    for i, ch in enumerate(text):
        pen = SVGPathPen(gs)
        gs[cmap[ord(ch)]].draw(pen)
        chunks.append(f'<path transform="translate({xg:.3f} 0)" d="{pen.getCommands()}"/>')
        xg += gs[cmap[ord(ch)]].width
        if i < len(text) - 1:
            xg += tracking_em * upem
    inner = f'<g transform="scale({scale:.6f} {-scale:.6f})" fill="{INK}">{"".join(chunks)}</g>'
    return f'<g transform="translate({origin:.3f} {baseline:.3f})">{inner}</g>'


def svg_wrap(body: str, title: str) -> str:
    return f"""<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
     width="{PAGE_W}mm" height="{PAGE_H}mm" viewBox="0 0 {PAGE_W} {PAGE_H}"
     role="img" aria-label="{escape(title)}">
  <title>{escape(title)}</title>
  {body}
</svg>
"""


def front() -> str:
    mark_h = 22.0
    mark_w = mark_h * MARK_ASPECT
    return f"""
  <g id="Front">
    <rect width="{PAGE_W}" height="{PAGE_H}" fill="{FRONT_BG}"/>
    <image href="{MARK_WHITE}" xlink:href="{MARK_WHITE}"
           x="{CX - mark_w / 2:.3f}" y="{CY - mark_h / 2:.3f}"
           width="{mark_w:.3f}" height="{mark_h:.3f}" preserveAspectRatio="xMidYMid meet"/>
  </g>"""


def back_images() -> str:
    mark_h = 7.2
    mark_w = mark_h * MARK_ASPECT
    word_h = 6.4
    word_w = word_h * WORD_ASPECT
    gap = 2.2
    total = mark_w + gap + word_w
    x0 = CX - total / 2
    y_mark = CY - mark_h / 2 - 1.2
    y_word = CY - word_h / 2 - 1.2
    return f"""
    <image href="{MARK}" xlink:href="{MARK}"
           x="{x0:.3f}" y="{y_mark:.3f}"
           width="{mark_w:.3f}" height="{mark_h:.3f}"/>
    <image href="{WORD}" xlink:href="{WORD}"
           x="{x0 + mark_w + gap:.3f}" y="{y_word:.3f}"
           width="{word_w:.3f}" height="{word_h:.3f}"/>"""


def editable_back() -> str:
    return f"""
  <g id="Back">
    <rect width="{PAGE_W}" height="{PAGE_H}" fill="{BACK_BG}"/>
    {back_images()}
    <text x="{SAFE}" y="{PAGE_H - SAFE - 3.2}" text-anchor="start"
          font-family="Helvetica Neue, Helvetica, Arial, sans-serif"
          font-size="2.35" fill="{INK}">{escape(PHONE)}</text>
    <text x="{SAFE}" y="{PAGE_H - SAFE}" text-anchor="start"
          font-family="Helvetica Neue, Helvetica, Arial, sans-serif"
          font-size="2.35" fill="{INK}">{escape(EMAIL)}</text>
    <text x="{PAGE_W - SAFE}" y="{PAGE_H - SAFE - 3.2}" text-anchor="end"
          font-family="Helvetica Neue, Helvetica, Arial, sans-serif"
          font-size="2.35" fill="{INK}">{escape(ADDR1)}</text>
    <text x="{PAGE_W - SAFE}" y="{PAGE_H - SAFE}" text-anchor="end"
          font-family="Helvetica Neue, Helvetica, Arial, sans-serif"
          font-size="2.35" fill="{INK}">{escape(ADDR2)}</text>
  </g>"""


def outlined_back(regular) -> str:
    left1 = outlined_text(regular, PHONE, 2.35, SAFE, PAGE_H - SAFE - 3.2, 0.02, "start")
    left2 = outlined_text(regular, EMAIL, 2.35, SAFE, PAGE_H - SAFE, 0.02, "start")
    right1 = outlined_text(regular, ADDR1, 2.35, PAGE_W - SAFE, PAGE_H - SAFE - 3.2, 0.02, "end")
    right2 = outlined_text(regular, ADDR2, 2.35, PAGE_W - SAFE, PAGE_H - SAFE, 0.02, "end")
    return f"""
  <g id="Back">
    <rect width="{PAGE_W}" height="{PAGE_H}" fill="{BACK_BG}"/>
    {back_images()}
    <g id="contact">{left1}{left2}{right1}{right2}</g>
  </g>"""


def mockup(front_body: str, back_body: str) -> str:
    gap = 10
    W, H = 140, PAGE_H * 2 + gap + 36
    y1, y2 = 16, 16 + PAGE_H + gap
    ox = (W - PAGE_W) / 2
    return f"""<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
     width="{W}mm" height="{H}mm" viewBox="0 0 {W} {H}">
  <rect width="{W}" height="{H}" fill="#d7e8e6"/>
  <g transform="translate({ox:.2f} {y1})">{front_body}</g>
  <g transform="translate({ox:.2f} {y2})">{back_body}</g>
</svg>
"""


def write(path: Path, text: str) -> None:
    path.write_text(text.strip() + "\n", encoding="utf-8")
    print("wrote", path.name)


def main() -> None:
    regular = helvetica("Regular")
    write(ROOT / "chamberq-card-front-editable.svg", svg_wrap(front(), "ChamberQ card — front"))
    write(ROOT / "chamberq-card-front.svg", svg_wrap(front(), "ChamberQ card — front"))
    write(ROOT / "chamberq-card-back-editable.svg", svg_wrap(editable_back(), "ChamberQ card — back"))
    write(ROOT / "chamberq-card-back.svg", svg_wrap(outlined_back(regular), "ChamberQ card — back"))
    write(ROOT / "chamberq-card-preview.svg", mockup(front(), outlined_back(regular)))


if __name__ == "__main__":
    main()
