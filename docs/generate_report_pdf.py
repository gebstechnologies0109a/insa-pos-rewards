#!/usr/bin/env python3
"""Generate HTML and PDF from INSA_POS_System_Report.md."""
from pathlib import Path

import markdown
from xhtml2pdf import pisa

DOCS = Path(__file__).parent
MD_FILE = DOCS / "INSA_POS_System_Report.md"
HTML_FILE = DOCS / "INSA_POS_System_Report.html"
PDF_FILE = DOCS / "INSA_POS_System_Report.pdf"

CSS = """
@page { size: A4; margin: 2cm; }
body {
  font-family: 'Segoe UI', Helvetica, Arial, sans-serif;
  font-size: 10pt;
  line-height: 1.45;
  color: #1a1a1a;
}
h1 { font-size: 22pt; color: #0f3460; border-bottom: 2px solid #0f3460; padding-bottom: 6px; }
h2 { font-size: 14pt; color: #16213e; margin-top: 1.2em; page-break-after: avoid; }
h3 { font-size: 11pt; color: #1a1a2e; page-break-after: avoid; }
table { border-collapse: collapse; width: 100%; margin: 0.8em 0; font-size: 9pt; }
th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
th { background: #e8eef5; }
code, pre { font-family: Consolas, monospace; font-size: 8.5pt; background: #f4f4f4; }
pre { padding: 8px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
hr { border: none; border-top: 1px solid #ddd; margin: 1.5em 0; }
a { color: #0f3460; }
blockquote { border-left: 3px solid #0f3460; margin-left: 0; padding-left: 12px; color: #444; }
"""

def main() -> None:
    md_text = MD_FILE.read_text(encoding="utf-8")
    # Replace mermaid blocks with a short note for PDF
    import re
    md_text = re.sub(
        r"```mermaid\n.*?```",
        "_Architecture diagram: see markdown source or HTML version (Mermaid)._",
        md_text,
        flags=re.DOTALL,
    )
    body = markdown.markdown(
        md_text,
        extensions=["tables", "fenced_code", "toc"],
    )
    html = f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>INSA POS System Report</title>
  <style>{CSS}</style>
</head>
<body>
{body}
<p style="margin-top:2em;font-size:9pt;color:#666;">Generated May 27, 2026 — Print to PDF via browser if needed.</p>
</body>
</html>"""
    HTML_FILE.write_text(html, encoding="utf-8")
    print(f"Wrote {HTML_FILE}")

    with PDF_FILE.open("wb") as pdf_file:
        status = pisa.CreatePDF(html, dest=pdf_file, encoding="utf-8")
    if status.err:
        raise SystemExit(f"PDF generation errors: {status.err}")
    print(f"Wrote {PDF_FILE}")


if __name__ == "__main__":
    main()
