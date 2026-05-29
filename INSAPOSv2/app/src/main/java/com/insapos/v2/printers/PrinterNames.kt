package com.insapos.v2.printers

/** Normalizes printer names from USB descriptors (often padded with null bytes). */
object PrinterNames {
    fun sanitize(raw: String?): String {
        if (raw.isNullOrEmpty()) return ""
        val sb = StringBuilder(raw.length)
        for (ch in raw) {
            if (ch == '\u0000') continue
            if (ch.isISOControl() && ch != '\t') continue
            sb.append(ch)
        }
        return sb.toString().trim()
    }

    fun namesMatch(a: String?, b: String?): Boolean {
        val sa = sanitize(a)
        val sb = sanitize(b)
        if (sa.isEmpty() || sb.isEmpty()) return false
        return sa == sb || sa.startsWith(sb) || sb.startsWith(sa)
    }
}
