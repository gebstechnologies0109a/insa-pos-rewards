/**
 * INSABuddy — Hardware Bridge Client for INSA POS
 * Communicates with the INSABuddy Android companion app (port 18181)
 * or the built-in INSAPOSv2 service layer (port 18182).
 */
const INSABuddy = {
    BASE_URL: 'http://127.0.0.1:18181',
    _connected: false,
    _pollingInterval: null,
    _isV2: false,

    /**
     * Detect INSAPOSv2 native bridge and switch to its local port.
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
     * Print a receipt from structured data.
     * Generates ESC/POS commands for a formatted receipt.
     */
    async printReceipt(receipt) {
        const lines = [];
        const divider = '================================';

        lines.push('\x1B\x61\x01'); // center align
        lines.push(receipt.storeName || (window.location.hostname.includes('epayplus') ? 'ePay Plus' : 'INSA POS'));
        lines.push(receipt.branchName || '');
        lines.push(divider);
        lines.push('\x1B\x61\x00'); // left align

        if (receipt.saleNumber) {
            lines.push(`Sale #: ${receipt.saleNumber}`);
        }
        lines.push(`Date: ${receipt.date || new Date().toLocaleString()}`);
        lines.push(`Cashier: ${receipt.cashier || ''}`);
        lines.push(divider);

        if (receipt.items && receipt.items.length) {
            for (const item of receipt.items) {
                const name = item.name.substring(0, 20).padEnd(20);
                const qty = String(item.qty).padStart(3);
                const total = (item.qty * item.price).toFixed(2).padStart(8);
                lines.push(`${name} ${qty} ${total}`);
            }
        }

        lines.push(divider);
        lines.push(`${'Subtotal:'.padEnd(24)}${(receipt.subtotal || 0).toFixed(2).padStart(8)}`);
        if (receipt.discount > 0) {
            lines.push(`${'Discount:'.padEnd(24)}${receipt.discount.toFixed(2).padStart(8)}`);
        }
        lines.push(`${'TOTAL:'.padEnd(24)}${(receipt.total || 0).toFixed(2).padStart(8)}`);
        lines.push(divider);
        lines.push(`Payment: ${receipt.paymentMethod || 'Cash'}`);
        if (receipt.amountTendered) {
            lines.push(`${'Tendered:'.padEnd(24)}${receipt.amountTendered.toFixed(2).padStart(8)}`);
            lines.push(`${'Change:'.padEnd(24)}${(receipt.change || 0).toFixed(2).padStart(8)}`);
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
     * Send a test print to the currently selected printer.
     */
    async testPrint() {
        return this._post('/printer/test', {});
    },

    /**
     * Normalize printer list response from INSABuddy or INSAPOSv2.
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

    // Auto-detect INSAPOSv2 bridge when available
    INSABuddy.detectV2();
    document.addEventListener('insapos:ready', function() {
        INSABuddy.detectV2();
    });
}
