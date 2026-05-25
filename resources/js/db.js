/**
 * INSA POS — Local IndexedDB Layer
 * Provides offline-first data storage using Dexie.js.
 * All methods return Promises and never throw uncaught errors.
 */
(function () {
    'use strict';

    const DB_NAME = 'insapos';
    const DB_VERSION = 1;

    let _db = null;

    function getDb() {
        if (_db) return _db;
        _db = new Dexie(DB_NAME);
        _db.version(DB_VERSION).stores({
            products:          'id, sku, barcode, category_id, name, updated_at',
            customers:         'id, name, phone, email, updated_at',
            cart:              '++id, product_id',
            transactions_local:'local_id, server_id, status, created_at',
            sync_queue:        '++id, type, ref, status, created_at',
            receipts:          '++id, local_tx_id, sale_number, created_at',
            settings:          'key',
        });
        return _db;
    }

    function generateUUID() {
        if (crypto.randomUUID) return crypto.randomUUID();
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    }

    // ── Products ──────────────────────────────────────────────

    const products = {
        async getAll() {
            try {
                return await getDb().products.toArray();
            } catch (e) {
                console.error('[db] products.getAll failed:', e);
                return [];
            }
        },

        async getById(id) {
            try {
                return await getDb().products.get(id) || null;
            } catch (e) {
                console.error('[db] products.getById failed:', e);
                return null;
            }
        },

        async getByBarcode(barcode) {
            try {
                return await getDb().products.where('barcode').equals(barcode).first() || null;
            } catch (e) {
                console.error('[db] products.getByBarcode failed:', e);
                return null;
            }
        },

        async bulkPut(list) {
            try {
                await getDb().products.bulkPut(list);
                return true;
            } catch (e) {
                console.error('[db] products.bulkPut failed:', e);
                return false;
            }
        },

        async clear() {
            try {
                await getDb().products.clear();
            } catch (e) {
                console.error('[db] products.clear failed:', e);
            }
        },

        async count() {
            try {
                return await getDb().products.count();
            } catch (e) {
                return 0;
            }
        },
    };

    // ── Customers ─────────────────────────────────────────────

    const customers = {
        async getAll() {
            try {
                return await getDb().customers.toArray();
            } catch (e) {
                console.error('[db] customers.getAll failed:', e);
                return [];
            }
        },

        async search(query) {
            try {
                const q = query.toLowerCase();
                return await getDb().customers
                    .filter(c =>
                        (c.name && c.name.toLowerCase().includes(q)) ||
                        (c.phone && c.phone.includes(q)) ||
                        (c.email && c.email.toLowerCase().includes(q))
                    )
                    .toArray();
            } catch (e) {
                console.error('[db] customers.search failed:', e);
                return [];
            }
        },

        async bulkPut(list) {
            try {
                await getDb().customers.bulkPut(list);
                return true;
            } catch (e) {
                console.error('[db] customers.bulkPut failed:', e);
                return false;
            }
        },

        async clear() {
            try {
                await getDb().customers.clear();
            } catch (e) {
                console.error('[db] customers.clear failed:', e);
            }
        },
    };

    // ── Cart ──────────────────────────────────────────────────

    const cart = {
        async getAll() {
            try {
                return await getDb().cart.toArray();
            } catch (e) {
                console.error('[db] cart.getAll failed:', e);
                return [];
            }
        },

        async add(item) {
            try {
                return await getDb().cart.add(item);
            } catch (e) {
                console.error('[db] cart.add failed:', e);
                return null;
            }
        },

        async clear() {
            try {
                await getDb().cart.clear();
            } catch (e) {
                console.error('[db] cart.clear failed:', e);
            }
        },
    };

    // ── Local Transactions ────────────────────────────────────

    const transactions = {
        async add(tx) {
            try {
                if (!tx.local_id) tx.local_id = generateUUID();
                if (!tx.status) tx.status = 'pending';
                if (!tx.created_at) tx.created_at = new Date().toISOString();
                await getDb().transactions_local.put(tx);
                return tx;
            } catch (e) {
                console.error('[db] transactions.add failed:', e);
                return null;
            }
        },

        async getAll() {
            try {
                return await getDb().transactions_local.toArray();
            } catch (e) {
                console.error('[db] transactions.getAll failed:', e);
                return [];
            }
        },

        async getPending() {
            try {
                return await getDb().transactions_local
                    .where('status').equals('pending')
                    .toArray();
            } catch (e) {
                console.error('[db] transactions.getPending failed:', e);
                return [];
            }
        },

        async markSynced(localId, serverId) {
            try {
                await getDb().transactions_local
                    .where('local_id').equals(localId)
                    .modify({ status: 'synced', server_id: serverId, synced_at: new Date().toISOString() });
                return true;
            } catch (e) {
                console.error('[db] transactions.markSynced failed:', e);
                return false;
            }
        },

        async markFailed(localId, error) {
            try {
                await getDb().transactions_local
                    .where('local_id').equals(localId)
                    .modify({ status: 'failed', sync_error: error });
                return true;
            } catch (e) {
                console.error('[db] transactions.markFailed failed:', e);
                return false;
            }
        },

        async markConflict(localId, conflictData) {
            try {
                await getDb().transactions_local
                    .where('local_id').equals(localId)
                    .modify({ status: 'conflict', conflict_data: conflictData });
                return true;
            } catch (e) {
                console.error('[db] transactions.markConflict failed:', e);
                return false;
            }
        },

        async getByLocalId(localId) {
            try {
                return await getDb().transactions_local.where('local_id').equals(localId).first() || null;
            } catch (e) {
                console.error('[db] transactions.getByLocalId failed:', e);
                return null;
            }
        },

        async pendingCount() {
            try {
                return await getDb().transactions_local.where('status').equals('pending').count();
            } catch (e) {
                return 0;
            }
        },

        async clearSynced() {
            try {
                await getDb().transactions_local.where('status').equals('synced').delete();
            } catch (e) {
                console.error('[db] transactions.clearSynced failed:', e);
            }
        },
    };

    // ── Sync Queue ────────────────────────────────────────────

    const syncQueue = {
        async add(job) {
            try {
                if (!job.status) job.status = 'pending';
                if (!job.created_at) job.created_at = new Date().toISOString();
                return await getDb().sync_queue.add(job);
            } catch (e) {
                console.error('[db] syncQueue.add failed:', e);
                return null;
            }
        },

        async getPending() {
            try {
                return await getDb().sync_queue
                    .where('status').equals('pending')
                    .toArray();
            } catch (e) {
                console.error('[db] syncQueue.getPending failed:', e);
                return [];
            }
        },

        async markDone(id) {
            try {
                await getDb().sync_queue.update(id, { status: 'done', completed_at: new Date().toISOString() });
            } catch (e) {
                console.error('[db] syncQueue.markDone failed:', e);
            }
        },

        async markFailed(id, error) {
            try {
                await getDb().sync_queue.update(id, { status: 'failed', error: error });
            } catch (e) {
                console.error('[db] syncQueue.markFailed failed:', e);
            }
        },

        async clearDone() {
            try {
                await getDb().sync_queue.where('status').equals('done').delete();
            } catch (e) {
                console.error('[db] syncQueue.clearDone failed:', e);
            }
        },
    };

    // ── Receipts ──────────────────────────────────────────────

    const receipts = {
        async add(receipt) {
            try {
                if (!receipt.created_at) receipt.created_at = new Date().toISOString();
                return await getDb().receipts.add(receipt);
            } catch (e) {
                console.error('[db] receipts.add failed:', e);
                return null;
            }
        },

        async getByTxId(localTxId) {
            try {
                return await getDb().receipts
                    .where('local_tx_id').equals(localTxId)
                    .first() || null;
            } catch (e) {
                console.error('[db] receipts.getByTxId failed:', e);
                return null;
            }
        },

        async getAll() {
            try {
                return await getDb().receipts.orderBy('created_at').reverse().toArray();
            } catch (e) {
                console.error('[db] receipts.getAll failed:', e);
                return [];
            }
        },
    };

    // ── Settings ──────────────────────────────────────────────

    const settings = {
        async get(key, defaultValue) {
            try {
                const row = await getDb().settings.get(key);
                return row ? row.value : (defaultValue !== undefined ? defaultValue : null);
            } catch (e) {
                console.error('[db] settings.get failed:', e);
                return defaultValue !== undefined ? defaultValue : null;
            }
        },

        async set(key, value) {
            try {
                await getDb().settings.put({ key, value });
                return true;
            } catch (e) {
                console.error('[db] settings.set failed:', e);
                return false;
            }
        },

        async remove(key) {
            try {
                await getDb().settings.delete(key);
            } catch (e) {
                console.error('[db] settings.remove failed:', e);
            }
        },
    };

    // ── Public API ────────────────────────────────────────────

    window.INSADB = {
        products,
        customers,
        cart,
        transactions,
        syncQueue,
        receipts,
        settings,
        generateUUID,

        async init() {
            try {
                await getDb().open();
                console.log('[db] IndexedDB ready');
                return true;
            } catch (e) {
                console.error('[db] IndexedDB init failed:', e);
                return false;
            }
        },
    };
})();
