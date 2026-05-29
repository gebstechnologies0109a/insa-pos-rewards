/**
 * INSA POS — Sync Engine
 * Manages offline/online synchronization between IndexedDB and the server.
 * Pushes local transactions, pulls product/customer updates, handles conflicts.
 */
(function () {
    'use strict';

    const SYNC_INTERVAL_IDLE_MS = 90000;
    const SYNC_INTERVAL_ACTIVE_MS = 30000;
    const FULL_PULL_INTERVAL_MS = 300000;
    const CATALOG_TTL_MS = 1800000;
    const PING_TIMEOUT_MS = 3000;

    let _scheduleTimer = null;
    let _syncing = false;
    let _downloading = false;
    let _online = false;
    let _initialized = false;
    let _onlineListenersBound = false;
    let _lastFullPullAt = 0;
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

    function scheduleNext(delayMs) {
        if (_scheduleTimer) clearTimeout(_scheduleTimer);
        _scheduleTimer = setTimeout(runScheduledSync, delayMs);
    }

    async function runScheduledSync() {
        if (document.hidden) {
            scheduleNext(SYNC_INTERVAL_IDLE_MS);
            return;
        }
        await syncNow(false);
        const db = window.INSADB;
        let pending = 0;
        try {
            pending = db ? await db.transactions.pendingCount() : 0;
        } catch (e) { /* ignore */ }
        scheduleNext(pending > 0 ? SYNC_INTERVAL_ACTIVE_MS : SYNC_INTERVAL_IDLE_MS);
    }

    function setBranchId(branchId) {
        if (branchId == null || branchId === '') return;
        const prev = _branchId;
        _branchId = branchId;
        if (_initialized && prev !== branchId) {
            downloadAll({ force: false, silent: true }).catch(() => {});
        }
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
        if (!db) return false;

        const pending = await db.transactions.getPending();
        if (pending.length === 0) return false;

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
                    await db.transactions.markSynced(tx.local_id, data.server_id);
                } else {
                    await db.transactions.markFailed(tx.local_id, data.message || 'Unknown error');
                    emit('syncError', { local_id: tx.local_id, error: data.message });
                }
            } catch (e) {
                console.error('[sync] push failed for', tx.local_id, e);
            }
        }
        return true;
    }

    // ── Pull catalog (products + categories) ─────────────────

    async function pullCatalog(forceFull = false) {
        const db = window.INSADB;
        if (!db) return { products: 0, categories: 0 };

        try {
            emit('syncStatus', 'pulling-products');

            let url = '/api/pos/products/all';
            if (_branchId) url += '?branch_id=' + encodeURIComponent(_branchId);

            const res = await fetch(url, { headers: headers() });
            const data = await res.json();
            let productCount = 0;
            let categoryCount = 0;

            if (data.products && data.products.length > 0) {
                await db.products.bulkPut(data.products);
                productCount = data.products.length;
                emit('productsUpdated', productCount);
            }

            const categories = data.categories || [];
            if (categories.length > 0) {
                await db.categories.bulkPut(categories);
                categoryCount = categories.length;
            }

            const syncedAt = new Date().toISOString();
            await db.settings.set('products_last_sync', syncedAt);
            await db.settings.set('catalog_last_sync', syncedAt);
            await db.settings.set('catalog_synced_at', syncedAt);
            return { products: productCount, categories: categoryCount };
        } catch (e) {
            console.error('[sync] pullCatalog failed:', e);
            return { products: 0, categories: 0 };
        }
    }

    /** @deprecated use pullCatalog */
    async function pullProducts(forceFull = false) {
        return pullCatalog(forceFull);
    }

    // ── Pull Customers ────────────────────────────────────────

    async function pullCustomers() {
        const db = window.INSADB;
        if (!db) return 0;

        try {
            emit('syncStatus', 'pulling-customers');

            const res = await fetch('/api/pos/customers/all', { headers: headers() });
            const data = await res.json();

            if (data.customers && data.customers.length > 0) {
                await db.customers.bulkPut(data.customers);
                emit('customersUpdated', data.customers.length);
                await db.settings.set('customers_last_sync', new Date().toISOString());
                return data.customers.length;
            }

            await db.settings.set('customers_last_sync', new Date().toISOString());
            return 0;
        } catch (e) {
            console.error('[sync] pullCustomers failed:', e);
            return 0;
        }
    }

    // ── Pull POS settings ─────────────────────────────────────

    async function pullSettings() {
        const db = window.INSADB;
        if (!db) return;

        try {
            const res = await fetch('/api/pos/settings', { headers: headers() });
            const data = await res.json();
            if (data.success && data.settings) {
                await db.settings.set('pos_settings', JSON.stringify(data.settings));
                await db.settings.set('settings_last_sync', new Date().toISOString());
            }
        } catch (e) {
            console.error('[sync] pullSettings failed:', e);
        }
    }

    // ── Pull POS settings (rewards / overrides) ───────────────

    async function pullSettings() {
        const db = window.INSADB;
        if (!db) return 0;

        try {
            emit('syncStatus', 'pulling-settings');
            const res = await fetch('/api/pos/settings', { headers: headers() });
            const data = await res.json();
            if (data.success && data.settings) {
                await db.settings.set('pos_settings', data.settings);
                await db.settings.set('settings_last_sync', new Date().toISOString());
                return Object.keys(data.settings).length;
            }
            return 0;
        } catch (e) {
            console.error('[sync] pullSettings failed:', e);
            return 0;
        }
    }

    async function markCacheReady() {
        const db = window.INSADB;
        if (!db) return false;
        const count = await db.products.count();
        const ready = count > 0;
        if (ready) {
            await db.settings.set('cache_ready', true);
            if (_branchId != null) {
                await db.settings.set('cache_ready_branch_id', _branchId);
            }
            await db.settings.set('cache_ready_at', new Date().toISOString());
        }
        emit('cacheReady', { ready, productCount: count, branchId: _branchId });
        return ready;
    }

    async function isCacheReady() {
        const db = window.INSADB;
        if (!db) return false;
        const ready = await db.settings.get('cache_ready', false);
        if (!ready) return false;
        if (_branchId != null) {
            const branch = await db.settings.get('cache_ready_branch_id', null);
            if (branch != null && String(branch) !== String(_branchId)) return false;
        }
        return (await db.products.count()) > 0;
    }

    async function isCatalogStale() {
        const db = window.INSADB;
        if (!db) return true;
        if ((await db.products.count()) === 0) return true;
        if (_branchId != null) {
            const branch = await db.settings.get('cache_ready_branch_id', null);
            if (branch != null && String(branch) !== String(_branchId)) return true;
            const session = await db.settings.get('catalog_synced_session', null);
            if (session != null && String(session) === String(_branchId)) return false;
        }
        const syncedAt = await db.settings.get('catalog_synced_at', null)
            || await db.settings.get('catalog_last_sync', null)
            || await db.settings.get('products_last_sync', null)
            || await db.settings.get('cache_ready_at', null);
        if (!syncedAt) return true;
        const ageMs = Date.now() - Date.parse(syncedAt);
        return !Number.isFinite(ageMs) || ageMs >= CATALOG_TTL_MS;
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

    async function pullInventory(forceFull = false) {
        const db = window.INSADB;
        if (!db || !_branchId) return 0;

        try {
            emit('syncStatus', 'pulling-inventory');
            let url = '/api/pos/sync/pull?branch_id=' + encodeURIComponent(_branchId);
            if (!forceFull) {
                const lastSync = await db.settings.get('inventory_last_sync', null);
                if (lastSync) url += '&since=' + encodeURIComponent(lastSync);
            }

            const res = await fetch(url, { headers: headers() });
            const data = await res.json();

            if (data.products && data.products.length > 0) {
                await db.products.bulkPut(data.products);
                if (db.productStock) {
                    await db.productStock.bulkPut(data.products, _branchId);
                }
                emit('productsUpdated', data.products.length);
                await db.settings.set('inventory_last_sync', data.pulled_at || new Date().toISOString());
                return data.products.length;
            }

            await db.settings.set('inventory_last_sync', data.pulled_at || new Date().toISOString());
            return 0;
        } catch (e) {
            console.error('[sync] pullInventory failed:', e);
            return 0;
        }
    }

    // ── Update Local Cache ────────────────────────────────────

    async function updateLocalCache(forceFull = true) {
        const needsCatalog = forceFull || await isCatalogStale();
        if (needsCatalog) {
            await pullCatalog(forceFull);
            await pullCustomers();
            await pullSettings();
        }
        await pullInventory(forceFull);
        await markCacheReady();
    }

    function shouldRunFullPull(forceFullPull) {
        return forceFullPull === true;
    }

    /**
     * Initial / manual full download for offline-first POS.
     * @param {{ force?: boolean, silent?: boolean }} options
     */
    async function downloadAll(options = {}) {
        if (_downloading) return { skipped: true };
        _downloading = true;
        const force = options.force === true;
        const silent = options.silent !== false;

        if (!silent) emit('downloadStart', { force });

        const result = { online: false, products: 0, categories: 0, customers: 0, stock: 0, settings: 0, fromCache: false, cacheReady: false };

        if (!_branchId) {
            console.warn('[sync] downloadAll skipped — branch_id not set');
            emit('downloadComplete', { ...result, error: 'branch_id required' });
            _downloading = false;
            return result;
        }

        try {
            const online = await checkOnline();
            result.online = online;

            if (!online) {
                emit('downloadProgress', {
                    phase: 'offline',
                    percent: 100,
                    message: 'Offline — using cached store data',
                });
                result.cacheReady = await markCacheReady();
                result.fromCache = result.cacheReady;
                emit('downloadComplete', result);
                emit('syncStatus', 'offline');
                return result;
            }

            const catalogStale = force || await isCatalogStale();
            const db = window.INSADB;
            if (catalogStale) {
                if (!silent) emit('downloadProgress', { phase: 'products', percent: 5, message: 'Downloading products…' });
                const catalog = await pullCatalog(force);
                result.products = catalog.products;
                result.categories = catalog.categories;
                if (db && _branchId != null) {
                    await db.settings.set('catalog_synced_session', _branchId);
                }
                if (!silent) emit('downloadProgress', { phase: 'products', percent: 40, message: 'Products saved locally' });
            } else if (!silent) {
                emit('downloadProgress', { phase: 'products', percent: 35, message: 'Using cached products' });
            }

            if (!silent) emit('downloadProgress', { phase: 'inventory', percent: 50, message: 'Downloading stock levels…' });
            result.stock = await pullInventory(force);
            if (!silent) emit('downloadProgress', { phase: 'inventory', percent: 75, message: 'Stock levels updated' });

            if (catalogStale) {
                if (!silent) emit('downloadProgress', { phase: 'customers', percent: 82, message: 'Downloading customers…' });
                result.customers = await pullCustomers();
                if (!silent) emit('downloadProgress', { phase: 'customers', percent: 90, message: 'Customers saved locally' });

                if (!silent) emit('downloadProgress', { phase: 'settings', percent: 93, message: 'Downloading settings…' });
                result.settings = await pullSettings();
                if (!silent) emit('downloadProgress', { phase: 'settings', percent: 96, message: 'Settings saved locally' });
            }

            result.cacheReady = await markCacheReady();
            _lastFullPullAt = Date.now();
            if (!silent) emit('downloadProgress', { phase: 'done', percent: 100, message: 'Store data ready' });
            emit('syncStatus', 'synced');
            if (!silent) emit('downloadComplete', result);
            return result;
        } catch (e) {
            console.error('[sync] downloadAll failed:', e);
            emit('syncStatus', 'error');
            emit('downloadComplete', { ...result, error: e.message });
            return result;
        } finally {
            _downloading = false;
        }
    }

    // ── Main Sync Cycle ───────────────────────────────────────
    // forceFullPull=true: manual / initial sync (always pull catalog)
    // forceFullPull=false: scheduled idle tick (push + occasional pull)

    async function syncNow(forceFullPull = false) {
        if (_syncing) return;
        _syncing = true;
        emit('syncStatus', 'syncing');

        try {
            const online = await checkOnline();

            if (!online) {
                emit('syncStatus', 'offline');
                return;
            }

            await pushTransactions();

            if (shouldRunFullPull(forceFullPull)) {
                await updateLocalCache(forceFullPull);
                _lastFullPullAt = Date.now();
            }

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

        const skipInitialDownload = options.skipInitialDownload === true;

        if (_initialized) {
            if (!skipInitialDownload) {
                downloadAll({ force: false, silent: true }).catch(() => {});
            }
            return;
        }
        _initialized = true;

        if (!_onlineListenersBound) {
            _onlineListenersBound = true;
            window.addEventListener('online', () => {
                _online = true;
                emit('connectivity', true);
                downloadAll({ force: false, silent: true }).catch(() => {});
            });
            window.addEventListener('offline', () => {
                _online = false;
                emit('connectivity', false);
                emit('syncStatus', 'offline');
            });
            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && _initialized) scheduleNext(1000);
            });
        }

        if (!skipInitialDownload) {
            const initialDelay = options.deferInitialSync ? 12000 : 400;
            setTimeout(() => downloadAll({ force: false, silent: true }), initialDelay);
        }

        const scheduleDelay = options.deferInitialSync ? 12000 + SYNC_INTERVAL_IDLE_MS : SYNC_INTERVAL_IDLE_MS;
        scheduleNext(scheduleDelay);
        setTimeout(() => pullFromBuddy(), (options.deferInitialSync ? 12000 : 400) + 2000);

        console.log('[sync] engine initialized');
    }

    function destroy() {
        if (_scheduleTimer) {
            clearTimeout(_scheduleTimer);
            _scheduleTimer = null;
        }
        _initialized = false;
    }

    // ── Public API ────────────────────────────────────────────

    window.SyncEngine = {
        init,
        destroy,
        syncNow,
        downloadAll,
        prefetchCatalog: downloadAll,
        pushTransactions,
        pullProducts,
        pullCatalog,
        pullInventory,
        pullCustomers,
        pullSettings,
        updateLocalCache,
        pullFromBuddy,
        pushToBuddy,
        checkOnline,
        setBranchId,
        markCacheReady,
        isCacheReady,

        isOnline() { return _online; },
        isSyncing() { return _syncing; },
        isDownloading() { return _downloading; },

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
