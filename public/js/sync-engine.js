/**
 * INSA POS — Sync Engine
 * Manages offline/online synchronization between IndexedDB and the server.
 * Pushes local transactions, pulls product/customer updates, handles conflicts.
 */
(function () {
    'use strict';

    const SYNC_INTERVAL_MS = 15000;
    const PING_TIMEOUT_MS = 3000;

    let _intervalId = null;
    let _syncing = false;
    let _online = false;
    let _listeners = {};
    let _csrfToken = '';
    let _branchId = null;

    function emit(event, data) {
        if (_listeners[event]) {
            _listeners[event].forEach(fn => {
                try { fn(data); } catch (e) { console.error('[sync] listener error:', e); }
            });
        }
    }

    function getCsrf() {
        if (_csrfToken) return _csrfToken;
        const el = document.querySelector('meta[name="csrf-token"]');
        _csrfToken = el ? el.content : '';
        return _csrfToken;
    }

    function headers() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': getCsrf(),
        };
    }

    // ── Connectivity Check ────────────────────────────────────

    async function checkOnline() {
        try {
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), PING_TIMEOUT_MS);
            const res = await fetch('/api/pos/ping', { signal: controller.signal });
            clearTimeout(timeout);
            const wasOnline = _online;
            _online = res.ok;
            if (_online !== wasOnline) emit('connectivity', _online);
            return _online;
        } catch {
            if (_online) {
                _online = false;
                emit('connectivity', false);
            }
            return false;
        }
    }

    // ── Push Transactions ─────────────────────────────────────

    async function pushTransactions() {
        const db = window.INSADB;
        if (!db) return;

        const pending = await db.transactions.getPending();
        if (pending.length === 0) return;

        emit('syncStatus', 'pushing');

        for (const tx of pending) {
            try {
                const res = await fetch('/api/pos/sync/push', {
                    method: 'POST',
                    headers: headers(),
                    body: JSON.stringify(tx),
                });

                const data = await res.json();

                if (data.success) {
                    await db.transactions.markSynced(tx.local_id, data.server_id || data.sale?.id);

                    // Mark corresponding sync queue jobs done
                    const jobs = await db.syncQueue.getPending();
                    for (const job of jobs) {
                        if (job.type === 'transaction_push' && job.ref === tx.local_id) {
                            await db.syncQueue.markDone(job.id);
                        }
                    }

                    emit('transactionSynced', { local_id: tx.local_id, server_id: data.server_id });
                } else if (data.conflict) {
                    await db.transactions.markConflict(tx.local_id, data.conflict);
                    emit('conflict', { local_id: tx.local_id, conflict: data.conflict });
                } else if (data.duplicate) {
                    // Idempotent: already synced
                    await db.transactions.markSynced(tx.local_id, data.server_id);
                } else {
                    await db.transactions.markFailed(tx.local_id, data.message || 'Unknown error');
                    emit('syncError', { local_id: tx.local_id, error: data.message });
                }
            } catch (e) {
                console.error('[sync] push failed for', tx.local_id, e);
                // Network error — leave as pending for next cycle
            }
        }
    }

    // ── Pull Products ─────────────────────────────────────────

    async function pullProducts() {
        const db = window.INSADB;
        if (!db) return;

        try {
            emit('syncStatus', 'pulling-products');

            const lastSync = await db.settings.get('products_last_sync', null);
            let url = '/api/pos/products/all';
            if (_branchId) url += '?branch_id=' + _branchId;
            if (lastSync) url += (url.includes('?') ? '&' : '?') + 'since=' + encodeURIComponent(lastSync);

            const res = await fetch(url, { headers: headers() });
            const data = await res.json();

            if (data.products && data.products.length > 0) {
                await db.products.bulkPut(data.products);
                emit('productsUpdated', data.products.length);
            }

            await db.settings.set('products_last_sync', new Date().toISOString());
        } catch (e) {
            console.error('[sync] pullProducts failed:', e);
        }
    }

    // ── Pull Customers ────────────────────────────────────────

    async function pullCustomers() {
        const db = window.INSADB;
        if (!db) return;

        try {
            emit('syncStatus', 'pulling-customers');

            const res = await fetch('/api/pos/customers/all', { headers: headers() });
            const data = await res.json();

            if (data.customers && data.customers.length > 0) {
                await db.customers.bulkPut(data.customers);
                emit('customersUpdated', data.customers.length);
            }

            await db.settings.set('customers_last_sync', new Date().toISOString());
        } catch (e) {
            console.error('[sync] pullCustomers failed:', e);
        }
    }

    // ── Pull from INSABuddy backup ────────────────────────────

    async function pullFromBuddy() {
        if (typeof INSABuddy === 'undefined' || !INSABuddy.isConnected()) return;
        const db = window.INSADB;
        if (!db) return;

        try {
            const data = await INSABuddy._get('/sync/pull');
            if (!data || !data.transactions) return;

            for (const tx of data.transactions) {
                const existing = await db.transactions.getByLocalId(tx.local_id);
                if (!existing) {
                    await db.transactions.add(tx);
                    emit('buddyRecovered', tx.local_id);
                }
            }

            if (data.receipts) {
                for (const r of data.receipts) {
                    const existing = await db.receipts.getByTxId(r.local_tx_id);
                    if (!existing) await db.receipts.add(r);
                }
            }
        } catch (e) {
            console.error('[sync] pullFromBuddy failed:', e);
        }
    }

    // ── Save to INSABuddy backup ──────────────────────────────

    async function pushToBuddy(tx, receipt) {
        if (typeof INSABuddy === 'undefined' || !INSABuddy.isConnected()) return;

        try {
            if (tx) await INSABuddy._post('/transaction/save', tx);
            if (receipt) await INSABuddy._post('/receipt/save', receipt);
        } catch (e) {
            console.error('[sync] pushToBuddy failed:', e);
        }
    }

    // ── Pull inventory deltas (batch stock + expiry flags) ───

    async function pullInventory() {
        const db = window.INSADB;
        if (!db || !_branchId) return;

        try {
            emit('syncStatus', 'pulling-inventory');
            const lastSync = await db.settings.get('inventory_last_sync', null);
            let url = '/api/pos/sync/pull?branch_id=' + encodeURIComponent(_branchId);
            if (lastSync) url += '&since=' + encodeURIComponent(lastSync);

            const res = await fetch(url, { headers: headers() });
            const data = await res.json();

            if (data.products && data.products.length > 0) {
                await db.products.bulkPut(data.products);
                if (db.productStock) {
                    await db.productStock.bulkPut(data.products, _branchId);
                }
                emit('productsUpdated', data.products.length);
            }

            await db.settings.set('inventory_last_sync', data.pulled_at || new Date().toISOString());
        } catch (e) {
            console.error('[sync] pullInventory failed:', e);
        }
    }

    // ── Update Local Cache ────────────────────────────────────

    async function updateLocalCache() {
        await pullProducts();
        await pullInventory();
        await pullCustomers();
    }

    // ── Main Sync Cycle ───────────────────────────────────────

    async function syncNow() {
        if (_syncing) return;
        _syncing = true;
        emit('syncStatus', 'syncing');

        try {
            const online = await checkOnline();

            if (!online) {
                emit('syncStatus', 'offline');
                _syncing = false;
                return;
            }

            await pushTransactions();
            await updateLocalCache();

            // Cleanup old synced data
            const db = window.INSADB;
            if (db) {
                await db.transactions.clearSynced();
                await db.syncQueue.clearDone();
            }

            const pendingCount = db ? await db.transactions.pendingCount() : 0;
            emit('syncStatus', pendingCount > 0 ? 'partial' : 'synced');
            emit('syncComplete', { pendingCount });
        } catch (e) {
            console.error('[sync] cycle error:', e);
            emit('syncStatus', 'error');
        } finally {
            _syncing = false;
        }
    }

    // ── Initialization ────────────────────────────────────────

    function init(options = {}) {
        if (options.branchId) _branchId = options.branchId;
        if (options.csrfToken) _csrfToken = options.csrfToken;

        // Start periodic sync
        if (_intervalId) clearInterval(_intervalId);
        _intervalId = setInterval(syncNow, options.interval || SYNC_INTERVAL_MS);

        // Listen for browser online/offline events
        window.addEventListener('online', () => {
            _online = true;
            emit('connectivity', true);
            syncNow();
        });

        window.addEventListener('offline', () => {
            _online = false;
            emit('connectivity', false);
            emit('syncStatus', 'offline');
        });

        // Initial sync — defer so POS UI paints first
        const initialDelay = options.deferInitialSync ? 8000 : 0;
        setTimeout(() => syncNow(), initialDelay);

        // Try recovering from INSABuddy on startup
        pullFromBuddy();

        console.log('[sync] engine initialized');
    }

    function destroy() {
        if (_intervalId) {
            clearInterval(_intervalId);
            _intervalId = null;
        }
    }

    // ── Public API ────────────────────────────────────────────

    window.SyncEngine = {
        init,
        destroy,
        syncNow,
        pushTransactions,
        pullProducts,
        pullInventory,
        pullCustomers,
        updateLocalCache,
        pullFromBuddy,
        pushToBuddy,
        checkOnline,

        isOnline() { return _online; },
        isSyncing() { return _syncing; },

        on(event, fn) {
            if (!_listeners[event]) _listeners[event] = [];
            _listeners[event].push(fn);
        },

        off(event, fn) {
            if (!_listeners[event]) return;
            _listeners[event] = _listeners[event].filter(f => f !== fn);
        },
    };
})();
