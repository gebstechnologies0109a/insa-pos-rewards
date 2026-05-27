#!/usr/bin/env python3
"""Generate PDF from ePayPlus Post-Update Test Report TXT."""
import re
from datetime import datetime
from fpdf import FPDF

TXT_PATH = r"C:\Users\Admin\Downloads\ePayPlus-Post-Update-Test-Report.txt"
PDF_PATH = r"C:\Users\Admin\Downloads\ePayPlus-Post-Update-Test-Report.pdf"


def safe_text(s: str, max_len: int = 14000) -> str:
    if not s:
        return ""
    s = s.replace("\r\n", "\n").replace("\r", "\n")
    s = s.replace("\u2014", "-").replace("\u2013", "-")
    s = s.replace("\u20ac", "EUR").replace("\u20b1", "PHP")
    s = re.sub(r"[^\x09\x0A\x0D\x20-\x7E]", "?", s)
    if len(s) > max_len:
        s = s[:max_len] + "\n... [truncated]"
    return s


class ReportPDF(FPDF):
    def header(self):
        self.set_font("Helvetica", "B", 11)
        self.set_text_color(20, 80, 50)
        self.cell(0, 8, "ePayPlus v3.1 Post-Update Test Report", align="C", new_x="LMARGIN", new_y="NEXT")
        self.set_draw_color(20, 80, 50)
        self.line(10, self.get_y(), 200, self.get_y())
        self.ln(3)

    def footer(self):
        self.set_y(-12)
        self.set_font("Helvetica", "I", 8)
        self.set_text_color(120, 120, 120)
        self.cell(0, 8, f"Page {self.page_no()}/{{nb}}", align="C")

    def section(self, title: str):
        self.ln(3)
        self.set_font("Helvetica", "B", 11)
        self.set_text_color(20, 60, 40)
        self.cell(0, 7, title, new_x="LMARGIN", new_y="NEXT")
        self.set_font("Helvetica", "", 9)
        self.set_text_color(0, 0, 0)

    def body(self, text: str):
        self.set_font("Helvetica", "", 8)
        self.multi_cell(0, 4, safe_text(text))


def main():
    with open(TXT_PATH, "r", encoding="utf-8") as f:
        content = f.read()

    pdf = ReportPDF()
    pdf.alias_nb_pages()
    pdf.set_auto_page_break(auto=True, margin=14)
    pdf.add_page()

    pdf.set_font("Helvetica", "", 9)
    pdf.cell(0, 5, f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}", new_x="LMARGIN", new_y="NEXT")
    pdf.ln(2)

    sections = re.split(r"\n(?=== )", content)
    for block in sections:
        block = block.strip()
        if not block:
            continue
        lines = block.split("\n", 1)
        title = lines[0].strip("= ").strip()
        body = lines[1] if len(lines) > 1 else ""
        pdf.section(title)
        pdf.body(body)
        pdf.ln(1)

    pdf.output(PDF_PATH)
    print(f"PDF written: {PDF_PATH}")


if __name__ == "__main__":
    main()
