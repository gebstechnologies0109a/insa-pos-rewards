/**
 * INSABuddy — Hardware Bridge Client for INSA POS
 * Communicates with the INSABuddy Android companion app (port 18181)
 * or the built-in INSAPOSv3 service layer (port 18182).
 */
const INSABuddy = {
    BASE_URL: 'http://127.0.0.1:18181',
    _connected: false,
    _pollingInterval: null,
    _isV2: false,
    _printerLayoutCache: null,

    /**
     * Detect INSAPOSv3 native bridge and switch to its local port.
     * Call this early (e.g. on DOMContentLoaded).
     */
    detectV2() {
        if (typeof window.INSAPOS !== 'undefined') {
            this._isV2 = true;
            const port = (typeof window.INSAPOS_SERVICE_PORT !== 'undefined')
                ? window.INSAPOS_SERVICE_PORT
                : 18182;
            this.BASE_URL = `http://127.0.0.1:${port}`;
            return true;
        }
        return false;
    },

    isV2() { return this._isV2; },

    /**
     * Ping the INSABuddy service to check if it's running.
     */
    async ping() {
        try {
            const res = await fetch(`${this.BASE_URL}/ping`, {
                signal: AbortSignal.timeout(2000),
            });
            if (res.ok) {
                const data = await res.json();
                this._connected = true;
                return data;
            }
            this._connected = false;
            return null;
        } catch {
            this._connected = false;
            return null;
        }
    },

    /**
     * Whether INSABuddy was reachable on last ping.
     */
    isConnected() {
        return this._connected;
    },

    /**
     * Print raw ESC/POS data (base64-encoded).
     */
    async print(base64Data) {
        return this._post('/print', { data: base64Data });
    },

    /**
     * Print plain text through the connected thermal printer.
     */
    async printText(text) {
        return this._post('/print', { text });
    },

    /**
     * Resolve receipt column width from paper size and font mode.
     */
    resolvePrinterLayout(paperSize, fontMode) {
        const paper = (paperSize === '87mm' || paperSize === '80mm') ? '87mm' : '57mm';
        const font = fontMode === 'fine_print' ? 'fine_print' : 'paper_size';
        const dotWidth = paper === '87mm' ? 576 : 384;
        let charWidth = 32;
        if (paper === '87mm' && font === 'fine_print') charWidth = 64;
        else if (paper === '87mm') charWidth = 48;
        else if (font === 'fine_print') charWidth = 42;
        return { paper_size: paper, font_mode: font, char_width: charWidth, dot_width: dotWidth };
    },

    /**
     * Get printer layout settings from the local Android service.
     */
    async getPrinterSettings() {
        try {
            const data = await this._get('/printer/settings');
            if (data && data.ok) {
                const layout = this.resolvePrinterLayout(data.paper_size, data.font_mode);
                this._printerLayoutCache = layout;
                return { ...data, ...layout, layout };
            }
        } catch {
            /* local service unavailable */
        }
        const layout = this.resolvePrinterLayout('57mm', 'paper_size');
        this._printerLayoutCache = layout;
        return { ok: true, ...layout, layout };
    },

    /**
     * Save printer layout settings on the Android device (local override).
     */
    async savePrinterSettings(paperSize, fontMode) {
        const data = await this._post('/printer/settings', {
            paper_size: paperSize,
            font_mode: fontMode,
        });
        if (data && data.ok) {
            this._printerLayoutCache = this.resolvePrinterLayout(data.paper_size, data.font_mode);
        }
        return data;
    },

    /**
     * Print a receipt from structured data.
     * Generates ESC/POS commands for a formatted receipt.
     */
    async printReceipt(receipt) {
        const settings = await this.getPrinterSettings();
        const w = settings.char_width || 32;
        const labelWidth = Math.max(w - 10, Math.floor(w * 0.65));
        const valueWidth = w - labelWidth;
        const itemNameWidth = Math.max(w - 13, Math.floor(w * 0.55));
        const lines = [];
        const divider = '='.repeat(w);

        lines.push('\x1B\x61\x01'); // center align
        lines.push((receipt.storeName || (window.location.hostname.includes('epayplus') ? 'ePay Plus' : 'INSA POS')).substring(0, w));
        if (receipt.branchName) lines.push(String(receipt.branchName).substring(0, w));
        lines.push(divider);
        lines.push('\x1B\x61\x00'); // left align

        if (receipt.saleNumber) {
            lines.push(`Sale #: ${receipt.saleNumber}`.substring(0, w));
        }
        lines.push(`Date: ${receipt.date || new Date().toLocaleString()}`.substring(0, w));
        lines.push(`Cashier: ${receipt.cashier || ''}`.substring(0, w));
        lines.push(divider);

        if (receipt.items && receipt.items.length) {
            for (const item of receipt.items) {
                const name = String(item.name).substring(0, itemNameWidth).padEnd(itemNameWidth);
                const qty = String(item.qty).padStart(3);
                const total = (item.qty * item.price).toFixed(2).padStart(Math.min(8, valueWidth));
                lines.push(`${name} ${qty} ${total}`.substring(0, w));
            }
        }

        lines.push(divider);
        lines.push(`${'Subtotal:'.padEnd(labelWidth)}${(receipt.subtotal || 0).toFixed(2).padStart(valueWidth)}`.substring(0, w));
        if (receipt.discount > 0) {
            lines.push(`${'Discount:'.padEnd(labelWidth)}${receipt.discount.toFixed(2).padStart(valueWidth)}`.substring(0, w));
        }
        lines.push(`${'TOTAL:'.padEnd(labelWidth)}${(receipt.total || 0).toFixed(2).padStart(valueWidth)}`.substring(0, w));
        lines.push(divider);
        lines.push(`Payment: ${receipt.paymentMethod || 'Cash'}`.substring(0, w));
        if (receipt.amountTendered) {
            lines.push(`${'Tendered:'.padEnd(labelWidth)}${receipt.amountTendered.toFixed(2).padStart(valueWidth)}`.substring(0, w));
            lines.push(`${'Change:'.padEnd(labelWidth)}${(receipt.change || 0).toFixed(2).padStart(valueWidth)}`.substring(0, w));
        }
        lines.push('');
        lines.push('\x1B\x61\x01'); // center
        lines.push('Thank you for your purchase!');
        lines.push('');
        lines.push('');

        return this.printText(lines.join('\n'));
    },

    /**
     * Open the cash drawer via ESC/POS pulse command.
     */
    async openDrawer() {
        return this._post('/drawer/open', {});
    },

    /**
     * Trigger a single barcode/QR scan.
     * Returns { success, value, format } or null on error.
     */
    async scan() {
        return this._post('/scan', {});
    },

    /**
     * Get the last barcode from a USB/Bluetooth HID barcode scanner.
     * Returns { success, value, source: "hid" } or null on error.
     */
    async getHidScan() {
        return this._get('/scan/hid');
    },

    /**
     * Enable or disable continuous scanning mode.
     */
    async setContinuousScan(enabled) {
        return this._post('/scan/continuous', { enabled });
    },

    /**
     * Get device information from the Android device.
     */
    async getDeviceInfo() {
        return this._get('/device/info');
    },

    /**
     * Get current printer status.
     */
    async getPrinterStatus() {
        return this._get('/printer/status');
    },

    /**
     * List all available printers detected by INSABuddy.
     */
    async listPrinters() {
        return this._get('/printer/list');
    },

    /**
     * Select a printer by type and name.
     */
    async selectPrinter(type, name) {
        return this._post('/printer/select', { type, name });
    },

    /**
     * Send a test print to the selected printer (type/name optional but recommended).
     */
    async testPrint(type, name) {
        const body = {};
        if (type) body.type = type;
        if (name) body.name = name;
        return this._post('/printer/test', body);
    },

    /**
     * Whether the connected bridge exposes full I/O device APIs (INSAPOSv3).
     */
    hasIoApi() {
        return this._isV2;
    },

    /**
     * Scan for keyboards, mice, and barcode scanners.
     */
    async scanIoDevices() {
        return this._get('/device/io/scan');
    },

    /**
     * Get saved I/O preferences.
     */
    async getIoStatus() {
        return this._get('/device/io/status');
    },

    /**
     * Save I/O preferences (keyboard, mouse, scanner, camera toggle).
     */
    async saveIoPreferences(prefs) {
        return this._post('/device/io/save', prefs);
    },

    /**
     * Test an I/O device (keyboard, mouse, scanner).
     */
    async testIoDevice(type, deviceId = null) {
        const body = { type };
        if (deviceId) body.device_id = deviceId;
        return this._post('/device/io/test', body);
    },

    parseIoScan(data) {
        if (!data) {
            return {
                keyboards: [],
                mice: [],
                scanners: [],
                preferences: {},
                ioApi: false,
            };
        }
        const mapList = (arr) => {
            if (!Array.isArray(arr)) return [];
            return arr.map(d => ({
                id: String(d.id ?? ''),
                name: d.name || '',
                type: d.type || '',
                connected: d.connected !== false,
            })).filter(d => d.id && d.name);
        };
        const prefs = data.preferences || {};
        return {
            keyboards: mapList(data.keyboards),
            mice: mapList(data.mice),
            scanners: mapList(data.scanners),
            preferences: {
                default_keyboard_id: prefs.default_keyboard_id || null,
                default_mouse_id: prefs.default_mouse_id || null,
                default_scanner_id: prefs.default_scanner_id || null,
                use_camera_for_scan: prefs.use_camera_for_scan !== false,
            },
            ioApi: data.io_api === true || this._isV2,
        };
    },

    parseIoPreferences(data) {
        const prefs = data?.preferences || data || {};
        return {
            default_keyboard_id: prefs.default_keyboard_id || null,
            default_mouse_id: prefs.default_mouse_id || null,
            default_scanner_id: prefs.default_scanner_id || null,
            use_camera_for_scan: prefs.use_camera_for_scan !== false,
        };
    },

    /**
     * Normalize printer list response from INSABuddy or INSAPOSv3.
     */
    parsePrinterList(data) {
        if (!data) return [];
        const raw = data.printers || [];
        if (!Array.isArray(raw)) return [];
        return raw.map(p => ({
            type: p.type || 'unknown',
            name: p.name || '',
            connected: !!p.connected,
        })).filter(p => p.name);
    },

    /**
     * Normalize printer status response from either bridge.
     */
    parsePrinterStatus(data) {
        if (!data) return { connected: false, name: null, type: null };
        return {
            connected: !!data.connected,
            name: data.name && data.name !== 'No printer' ? data.name : null,
            type: data.type && data.type !== 'none' ? data.type : null,
        };
    },

    isSuccessResponse(data) {
        if (!data) return false;
        return data.ok === true || data.success === true;
    },

    /**
     * Whether a print/test response indicates the job was sent.
     */
    isPrintSuccess(data) {
        if (!data) return false;
        return data.printed === true
            || (this.isSuccessResponse(data) && data.printed !== false);
    },

    /**
     * Extract a user-facing error from a bridge API response.
     */
    parseApiError(data, fallback = 'Request failed') {
        if (!data) return 'Local hardware service unavailable';
        if (data.error) return String(data.error);
        if (data.message) return String(data.message);
        if (data.reason) return String(data.reason);
        return fallback;
    },

    /**
     * Start polling INSABuddy status at the given interval (ms).
     * Calls `onChange(connected)` whenever status changes.
     */
    startPolling(intervalMs = 20000, onChange = null) {
        this.stopPolling();
        let wasConnected = this._connected;

        this._pollingInterval = setInterval(async () => {
            await this.ping();
            if (this._connected !== wasConnected) {
                wasConnected = this._connected;
                if (onChange) onChange(this._connected);
            }
        }, intervalMs);

        // Immediate first check
        this.ping().then(() => {
            if (onChange) onChange(this._connected);
        });
    },

    /**
     * Stop status polling.
     */
    stopPolling() {
        if (this._pollingInterval) {
            clearInterval(this._pollingInterval);
            this._pollingInterval = null;
        }
    },

    // --- Internal helpers ---

    async _get(path) {
        this.detectV2();
        try {
            const res = await fetch(`${this.BASE_URL}${path}`, {
                signal: AbortSignal.timeout(5000),
            });
            return await res.json();
        } catch {
            return null;
        }
    },

    async _post(path, body) {
        this.detectV2();
        try {
            const res = await fetch(`${this.BASE_URL}${path}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
                signal: AbortSignal.timeout(30000),
            });
            return await res.json();
        } catch {
            return null;
        }
    },
};

// Export for module use if available
if (typeof module !== 'undefined' && module.exports) {
    module.exports = INSABuddy;
}
if (typeof window !== 'undefined') {
    window.INSABuddy = INSABuddy;

    // Auto-detect INSAPOSv3 bridge when available
    INSABuddy.detectV2();
    document.addEventListener('insapos:ready', function() {
        INSABuddy.detectV2();
    });
}
