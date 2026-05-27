#!/usr/bin/env python3
"""Generate professional PDF from 60-min Android deep scan TXT report."""
import json
import os
import re
from datetime import datetime

from fpdf import FPDF

TXT_PATH = r"C:\Users\Admin\Downloads\Android-60min-Deep-Scan-Report.txt"
PDF_PATH = r"C:\Users\Admin\Downloads\Android-60min-Deep-Scan-Report.pdf"
META_PATH = r"C:\Users\Admin\Downloads\Android-60min-Deep-Scan-meta.json"


def safe_text(s: str, max_len: int = 12000) -> str:
    if not s:
        return ""
    s = s.replace("\r\n", "\n").replace("\r", "\n")
    s = re.sub(r"[^\x09\x0A\x0D\x20-\x7E\u00A0-\u024F]", "?", s)
    if len(s) > max_len:
        s = s[:max_len] + "\n... [truncated]"
    return s


def extract_samples(content: str) -> list:
    pattern = r"=== SAMPLE (\d+) @ ([\d\- :]+) \(([^)]+)\) ==="
    matches = list(re.finditer(pattern, content))
    rows = []
    for i, m in enumerate(matches):
        start = m.end()
        end = matches[i + 1].start() if i + 1 < len(matches) else content.find("COMPILED ANALYSIS")
        if end < 0:
            end = len(content)
        body = content[start:end]
        rows.append(
            {
                "num": m.group(1),
                "time": m.group(2),
                "label": m.group(3),
                "has_09net": "09NET256071439" in body,
                "has_fox": bool(re.search(r"Fox-[A-Fa-f0-9]+", body)),
                "has_redis": bool(re.search(r"25565|redis|Redis", body, re.I)),
                "has_dafox": "dafox" in body.lower(),
            }
        )
    return rows


def top_findings(content: str, meta: dict | None) -> list[str]:
    findings = []
    if meta and meta.get("Findings"):
        findings.extend(meta["Findings"][:8])
    if "Fox-B068B8" in content:
        findings.append("Fox-B068B8 machine ID observed (prior context)")
    if re.search(r"25565|RedisThread", content, re.I):
        findings.append("Redis/port 25565 activity logged during session")
    if "WebShoppePH" in content:
        findings.append("WiFi SSID WebShoppePH 5G in use")
    if "lockTask" in content or "LockTask" in content:
        findings.append("Lock task / kiosk mode indicators present")
    if "FoxDeviceAdminReceiver" in content or "device owner" in content.lower():
        findings.append("DaFox device owner (FoxDeviceAdminReceiver) active")
    real_09 = [
        ln
        for ln in content.split("\n")
        if "09NET256071439" in ln
        and "ADB_SERVICES" not in ln
        and "adb.exe:" not in ln
        and "Search Target" not in ln
        and "ANALYST CORRECTION" not in ln
    ]
    if not real_09:
        findings.append("09NET256071439: NOT FOUND (verified post-processing)")
    else:
        findings.append("09NET256071439: FOUND in report")
    seen = set()
    unique = []
    for f in findings:
        if f not in seen:
            seen.add(f)
            unique.append(f)
    return unique[:10]


class ScanReportPDF(FPDF):
    def header(self):
        self.set_font("Helvetica", "B", 11)
        self.set_text_color(40, 60, 100)
        self.cell(0, 8, "Android 60-Minute Deep Scan Report", align="C", new_x="LMARGIN", new_y="NEXT")
        self.set_draw_color(40, 60, 100)
        self.line(10, self.get_y(), 200, self.get_y())
        self.ln(3)

    def footer(self):
        self.set_y(-12)
        self.set_font("Helvetica", "I", 8)
        self.set_text_color(120, 120, 120)
        self.cell(0, 8, f"Page {self.page_no()}/{{nb}}", align="C")

    def section_title(self, title: str):
        self.ln(4)
        self.set_font("Helvetica", "B", 12)
        self.set_text_color(30, 50, 90)
        self.cell(0, 8, title, new_x="LMARGIN", new_y="NEXT")
        self.set_font("Helvetica", "", 9)
        self.set_text_color(0, 0, 0)

    def body_text(self, text: str):
        self.set_font("Helvetica", "", 8)
        w = self.epw
        for para in safe_text(text).split("\n"):
            para = para.strip()
            if not para:
                self.ln(2)
                continue
            self.multi_cell(w, 4, para)


def build_pdf():
    if not os.path.isfile(TXT_PATH):
        raise FileNotFoundError(f"Report not found: {TXT_PATH}")

    with open(TXT_PATH, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()

    meta = None
    if os.path.isfile(META_PATH):
        with open(META_PATH, "r", encoding="utf-8-sig") as f:
            meta = json.load(f)

    samples = extract_samples(content)
    findings = top_findings(content, meta)
    # Exclude false positives: ADB command echoes and error strings
    real_hits = [
        ln
        for ln in content.split("\n")
        if "09NET256071439" in ln
        and "ADB_SERVICES" not in ln
        and "service_to_fd" not in ln
        and "adb.exe:" not in ln
        and "Search Target:" not in ln
        and "Prior Context:" not in ln
        and "SEARCH RESULTS" not in ln
        and "Search target" not in ln
    ]
    found_09net = len(real_hits) > 0

    pdf = ScanReportPDF()
    pdf.alias_nb_pages()
    pdf.set_auto_page_break(auto=True, margin=15)
    pdf.set_margins(12, 12, 12)
    pdf.add_page()

    pdf.set_font("Helvetica", "B", 16)
    pdf.set_text_color(20, 40, 80)
    pdf.cell(0, 12, "Android Deep Monitoring Report", new_x="LMARGIN", new_y="NEXT")
    pdf.set_font("Helvetica", "", 10)
    pdf.set_text_color(60, 60, 60)
    pdf.cell(
        0,
        6,
        f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}",
        new_x="LMARGIN",
        new_y="NEXT",
    )
    pdf.cell(0, 6, "Device: JH2404230714 (Smart_9, Android 8.1)", new_x="LMARGIN", new_y="NEXT")
    pdf.cell(0, 6, "App: com.dafox.eloading (DaFox)", new_x="LMARGIN", new_y="NEXT")
    pdf.ln(6)

    pdf.section_title("Executive Summary")
    summary = (
        f"A 60-minute monitoring session collected {len(samples)} sample points at 5-minute intervals. "
        f"Target machine ID 09NET256071439: {'FOUND' if found_09net else 'NOT FOUND'}. "
        "DaFox kiosk app, peripherals, WiFi, and network sockets were observed. "
        "Prior context indicates Fox-B068B8 may be the active machine identifier."
    )
    pdf.body_text(summary)

    pdf.section_title("Top Findings")
    for i, f in enumerate(findings, 1):
        pdf.body_text(f"{i}. {f}")

    pdf.section_title("Sample Timeline")
    if samples:
        col_w = [18, 42, 38, 22, 22, 22, 26]
        headers = ["#", "Timestamp", "Label", "09NET", "Fox-ID", "Redis", "DaFox"]
        pdf.set_font("Helvetica", "B", 7)
        for h, w in zip(headers, col_w):
            pdf.cell(w, 6, h, border=1)
        pdf.ln()
        pdf.set_font("Helvetica", "", 7)
        for s in samples:
            pdf.cell(col_w[0], 5, s["num"], border=1)
            pdf.cell(col_w[1], 5, s["time"][:16], border=1)
            pdf.cell(col_w[2], 5, s["label"][:18], border=1)
            pdf.cell(col_w[3], 5, "Y" if s["has_09net"] else "N", border=1)
            pdf.cell(col_w[4], 5, "Y" if s["has_fox"] else "N", border=1)
            pdf.cell(col_w[5], 5, "Y" if s["has_redis"] else "N", border=1)
            pdf.cell(col_w[6], 5, "Y" if s["has_dafox"] else "N", border=1)
            pdf.ln()
    else:
        pdf.body_text("No sample headers parsed from TXT.")

    pdf.add_page()
    pdf.section_title("09NET256071439 Search")
    pdf.body_text(
        f"Result: {'DETECTED in logs/UI/files' if found_09net else 'NOT DETECTED across all samples'}. "
        "Searched logcat (300-line buffer), /sdcard grep, dumpsys window, and filtered log slices each interval."
    )

    pdf.section_title("Network & DaFox Patterns")
    net_snip = []
    for line in content.split("\n"):
        if re.search(r"25565|1883|8883|WebShoppe|Redis|mqtt|okhttp", line, re.I):
            net_snip.append(line.strip())
    pdf.body_text("\n".join(net_snip[:40]) if net_snip else "See full TXT for network dumps.")

    pdf.section_title("Recommendations for ePayPlus")
    recs = [
        "Map Fox-XXXXXXXX machine IDs to backend 09NET registry if needed.",
        "Validate Redis 25565 heartbeat before go-live on kiosk hardware.",
        "Respect device-owner lock; plan ePayPlus install policy with DaFox team.",
        "Correlate USB peripheral attach with transaction/network spikes.",
        "Maintain WebShoppePH 5G connectivity monitoring.",
    ]
    for r in recs:
        pdf.body_text(f"  - {r}")

    pdf.add_page()
    pdf.section_title("Appendix: Report Excerpt (first 8000 chars)")
    excerpt = content[:8000]
    pdf.body_text(excerpt)

    pdf.output(PDF_PATH)
    return PDF_PATH


if __name__ == "__main__":
    path = build_pdf()
    size = os.path.getsize(path)
    print(f"PDF written: {path} ({size} bytes)")
