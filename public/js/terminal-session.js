/**
 * INSA POS v3 — Cashier seat / terminal session (browser fingerprint + server register).
 */
(function () {
    'use strict';

    const STORAGE_KEY = 'insapos_terminal_session_id';
    const FINGERPRINT_KEY = 'insapos_device_fingerprint';

    function generateUUID() {
        if (crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    function getFingerprint() {
        try {
            if (typeof window.INSAPOS !== 'undefined' && typeof window.INSAPOS.getDeviceFingerprint === 'function') {
                const nativeFp = window.INSAPOS.getDeviceFingerprint();
                if (nativeFp) {
                    localStorage.setItem(FINGERPRINT_KEY, nativeFp);
                    return nativeFp;
                }
            }
            let fp = localStorage.getItem(FINGERPRINT_KEY);
            if (fp) return fp;
            const parts = [
                navigator.userAgent,
                navigator.language,
                screen.width + 'x' + screen.height,
                new Date().getTimezoneOffset(),
            ];
            fp = 'web-' + btoa(parts.join('|')).replace(/[^a-zA-Z0-9]/g, '').slice(0, 48);
            localStorage.setItem(FINGERPRINT_KEY, fp);
            return fp;
        } catch {
            return 'web-anon-' + generateUUID().slice(0, 16);
        }
    }

    function getCsrf() {
        const el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.content : '';
    }

    async function register(branchId) {
        const sessionId = localStorage.getItem(STORAGE_KEY);
        const body = {
            device_fingerprint: getFingerprint(),
            branch_id: branchId || null,
        };
        if (sessionId) body.session_id = sessionId;

        const res = await fetch('/api/pos/terminal/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
            },
            body: JSON.stringify(body),
        });

        const data = await res.json();

        if (data.success && data.session_id) {
            localStorage.setItem(STORAGE_KEY, data.session_id);
            return { ok: true, sessionId: data.session_id, slots: data.slots };
        }

        return {
            ok: false,
            message: data.message || 'Could not start cashier session.',
            code: data.code || 'error',
        };
    }

    async function endSession() {
        const sessionId = localStorage.getItem(STORAGE_KEY);
        if (!sessionId) return;

        try {
            await fetch('/api/pos/terminal/end', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrf(),
                },
                body: JSON.stringify({
                    session_id: sessionId,
                    device_fingerprint: getFingerprint(),
                }),
                keepalive: true,
            });
        } catch (e) {
            console.warn('[terminal-session] end failed', e);
        }

        localStorage.removeItem(STORAGE_KEY);
    }

    window.addEventListener('beforeunload', () => {
        endSession();
    });

    window.PosTerminalSession = {
        register,
        endSession,
        getSessionId() { return localStorage.getItem(STORAGE_KEY); },
        getFingerprint,
    };
})();
