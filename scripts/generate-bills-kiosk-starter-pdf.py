#!/usr/bin/env python3
"""Generate ePay Plus Bills Kiosk Starter Guide PDF to Downloads."""
import re
from datetime import date
from pathlib import Path

import markdown
from xhtml2pdf import pisa

ROOT = Path(r"c:\laragon\www\ePay Plus")
DOCS = ROOT / "docs"
MD_MAIN = DOCS / "EPAYPLUS_BILLS_KIOSK_STARTER_GUIDE.md"
DATE_STR = date.today().isoformat()
OUT_PDF = Path(rf"C:\Users\Admin\Downloads\ePayPlus-Bills-Kiosk-Starter-Guide-{DATE_STR}.pdf")
OUT_HTML = OUT_PDF.with_suffix(".html")

CSS = """
@page { size: A4; margin: 2cm; }
body {
  font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
  font-size: 10pt;
  line-height: 1.45;
  color: #1a1a1a;
}
.title-page { page-break-after: always; text-align: center; padding-top: 6cm; }
.title-page h1 { font-size: 22pt; color: #0f3460; border: none; }
.title-page .subtitle { font-size: 14pt; color: #444; margin-top: 1em; }
.title-page .meta { font-size: 11pt; color: #666; margin-top: 2em; }
h1 { font-size: 18pt; color: #0f3460; border-bottom: 2px solid #0f3460; padding-bottom: 6px; }
h2 { font-size: 14pt; color: #16213e; margin-top: 1.2em; page-break-after: avoid; }
h3 { font-size: 11pt; color: #1a1a2e; page-break-after: avoid; }
table { border-collapse: collapse; width: 100%; margin: 0.8em 0; font-size: 9pt; }
th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
th { background: #e8eef5; }
code, pre { font-family: Consolas, monospace; font-size: 8.5pt; background: #f4f4f4; }
pre { padding: 8px; white-space: pre-wrap; word-wrap: break-word; }
pre.mermaid-fallback { border-left: 3px solid #0f3460; }
hr { border: none; border-top: 1px solid #ddd; margin: 1.5em 0; }
ul, ol { margin: 0.4em 0; padding-left: 1.4em; }
li { margin: 0.2em 0; }
a { color: #0f3460; }
"""


def mermaid_to_pre(md_text: str) -> str:
    def block_repl(m: re.Match) -> str:
        inner = m.group(1).strip()
        return (
            "\n\n**Architecture diagram (Mermaid source):**\n\n"
            f"```\n{inner}\n```\n\n"
        )

    return re.sub(r"```mermaid\n(.*?)```", block_repl, md_text, flags=re.DOTALL)


def ascii_blocks_preserve(md_text: str) -> str:
    """Ensure ASCII art fences render as pre in markdown."""
    return md_text


def main() -> None:
    if not MD_MAIN.is_file():
        raise SystemExit(f"Missing source: {MD_MAIN}")

    md_text = MD_MAIN.read_text(encoding="utf-8")
    md_text = mermaid_to_pre(md_text)
    md_text = ascii_blocks_preserve(md_text)
    body = markdown.markdown(md_text, extensions=["tables", "fenced_code", "toc"])

    title = f"""
<div class="title-page">
  <h1>ePay Plus</h1>
  <p class="subtitle">Bills Payment Kiosk Starter Guide</p>
  <p class="meta">{DATE_STR}<br/>
  Beginner checklist &amp; glossary &middot; Philippines kiosk context</p>
</div>
"""
    html = f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>ePay Plus — Bills Payment Kiosk Starter Guide</title>
  <style>{CSS}</style>
</head>
<body>
{title}
{body}
<p style="margin-top:2em;font-size:9pt;color:#666;">Generated {DATE_STR} — {MD_MAIN.name} + xhtml2pdf</p>
</body>
</html>"""

    OUT_HTML.write_text(html, encoding="utf-8")
    with OUT_PDF.open("wb") as pdf_file:
        status = pisa.CreatePDF(html, dest=pdf_file, encoding="utf-8")
    if status.err:
        raise SystemExit(f"PDF errors: {status.err}")

    print(f"MD:  {MD_MAIN} ({MD_MAIN.stat().st_size} bytes)")
    print(f"HTML: {OUT_HTML} ({OUT_HTML.stat().st_size} bytes)")
    print(f"PDF: {OUT_PDF} ({OUT_PDF.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
