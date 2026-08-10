#!/usr/bin/env bash
# Print doctor proposal HTML → portrait A4 PDF via Brave (headless).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
BRAVE="${BRAVE_BIN:-/Applications/Brave Browser.app/Contents/MacOS/Brave Browser}"
if [[ ! -x "$BRAVE" ]]; then
  echo "Brave not found at: $BRAVE" >&2
  exit 1
fi

print_one() {
  local html="$1"
  local pdf="${html%.html}.pdf"
  echo "Printing $(basename "$html") → $(basename "$pdf")"
  "$BRAVE" --headless --disable-gpu --no-pdf-header-footer \
    --print-to-pdf="$pdf" "file://$html" 2>/dev/null
  ls -la "$pdf"
}

print_one "$ROOT/docs/proposals/Dr-Shamim-Ahmed-ChamberQ-Proposal.html"
print_one "$ROOT/docs/proposals/Dr-Sharfuddin-Mahmood-ChamberQ-Proposal.html"
echo "Done."
