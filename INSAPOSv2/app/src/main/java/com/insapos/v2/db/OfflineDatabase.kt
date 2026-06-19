package com.insapos.v2.db

import android.content.ContentValues
import android.content.Context
import android.database.Cursor
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper
import android.util.Log
import org.json.JSONArray
import org.json.JSONObject

class OfflineDatabase(context: Context) : SQLiteOpenHelper(
    context, DB_NAME, null, DB_VERSION
) {
    companion object {
        private const val TAG = "OfflineDB"
        private const val DB_NAME = "insapos_offline.db"
        private const val DB_VERSION = 6
        const val DEFAULT_PRODUCT_PAGE_SIZE = 500
        const val MAX_PRODUCT_PAGE_SIZE = 2000
        const val MAX_SEARCH_RESULTS = 500
    }

    private val writeLock = Any()

    /** Serialize writes only; WAL allows concurrent reads during catalog sync. */
    private inline fun <T> withDb(block: () -> T): T = synchronized(writeLock) { block() }

    /** Read-only queries — no global lock so sales/print stay responsive during sync. */
    private inline fun <T> dbOp(block: () -> T): T = block()

    override fun onConfigure(db: SQLiteDatabase) {
        super.onConfigure(db)
        db.enableWriteAheadLogging()
    }

    /** Atomic write; return false to roll back. */
    fun runInTransaction(block: (SQLiteDatabase) -> Boolean): Boolean = withDb {
        val db = writableDatabase
        db.beginTransaction()
        try {
            val ok = block(db)
            if (ok) db.setTransactionSuccessful()
            ok
        } finally {
            db.endTransaction()
        }
    }

    override fun onCreate(db: SQLiteDatabase) {
        createV1Tables(db)
        createV2Tables(db)
        Log.i(TAG, "Database created with all tables")
    }

    private fun createV1Tables(db: SQLiteDatabase) {
        db.execSQL("""
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY,
                server_id INTEGER,
                sku TEXT,
                barcode TEXT,
                name TEXT NOT NULL,
                price REAL NOT NULL DEFAULT 0,
                cost REAL DEFAULT 0,
                category_id INTEGER DEFAULT 0,
                category TEXT,
                unit TEXT,
                stock REAL DEFAULT 0,
                image_url TEXT,
                tax_rate REAL DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                data_json TEXT,
                synced_at TEXT,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        """)
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_products_sku ON products(sku)")
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode)")
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_products_server_id ON products(server_id)")
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_products_category_id ON products(category_id)")

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS customers (
                id INTEGER PRIMARY KEY,
                server_id INTEGER,
                name TEXT NOT NULL,
                phone TEXT,
                email TEXT,
                address TEXT,
                data_json TEXT,
                synced_at TEXT,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        """)
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_customers_server_id ON customers(server_id)")

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS transactions_local (
                id INTEGER PRIMARY KEY,
                local_id TEXT NOT NULL UNIQUE,
                server_id INTEGER,
                type TEXT DEFAULT 'sale',
                status TEXT DEFAULT 'completed',
                customer_id INTEGER,
                items_json TEXT NOT NULL,
                subtotal REAL DEFAULT 0,
                discount REAL DEFAULT 0,
                tax REAL DEFAULT 0,
                total REAL NOT NULL DEFAULT 0,
                payment_method TEXT DEFAULT 'cash',
                amount_tendered REAL DEFAULT 0,
                change_amount REAL DEFAULT 0,
                cashier_name TEXT,
                notes TEXT,
                receipt_json TEXT,
                branch_id INTEGER DEFAULT 0,
                cashier_id INTEGER DEFAULT 0,
                shift_id INTEGER DEFAULT 0,
                member_id INTEGER DEFAULT 0,
                synced INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                synced_at TEXT
            )
        """)
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_txn_local_id ON transactions_local(local_id)")
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_txn_synced ON transactions_local(synced)")

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS cart (
                id INTEGER PRIMARY KEY,
                session_id TEXT NOT NULL,
                product_id INTEGER,
                product_name TEXT,
                quantity REAL DEFAULT 1,
                price REAL DEFAULT 0,
                discount REAL DEFAULT 0,
                notes TEXT,
                data_json TEXT,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        """)

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS sync_queue (
                id INTEGER PRIMARY KEY,
                action TEXT NOT NULL,
                table_name TEXT NOT NULL,
                record_id TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER DEFAULT 0,
                max_attempts INTEGER DEFAULT 5,
                status TEXT DEFAULT 'pending',
                error TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                next_retry_at TEXT
            )
        """)
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_sync_status ON sync_queue(status)")

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS receipts (
                id INTEGER PRIMARY KEY,
                transaction_local_id TEXT NOT NULL,
                receipt_text TEXT,
                receipt_html TEXT,
                receipt_json TEXT,
                printed INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        """)

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )
        """)

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS sync_log (
                id INTEGER PRIMARY KEY,
                direction TEXT NOT NULL,
                table_name TEXT,
                records_count INTEGER DEFAULT 0,
                status TEXT,
                error TEXT,
                started_at TEXT DEFAULT CURRENT_TIMESTAMP,
                completed_at TEXT
            )
        """)

    }

    private fun createV2Tables(db: SQLiteDatabase) {
        db.execSQL("""
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY,
                server_id INTEGER,
                name TEXT NOT NULL,
                data_json TEXT,
                synced_at TEXT,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        """)

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS inventory_batches (
                id INTEGER PRIMARY KEY,
                server_id INTEGER,
                product_id INTEGER NOT NULL,
                branch_id INTEGER,
                batch_code TEXT,
                expiry_date TEXT,
                qty REAL DEFAULT 0,
                cost REAL DEFAULT 0,
                data_json TEXT,
                synced_at TEXT
            )
        """)
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_batches_product ON inventory_batches(product_id)")

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS stock_movements (
                id INTEGER PRIMARY KEY,
                local_id TEXT,
                product_id INTEGER NOT NULL,
                qty REAL NOT NULL,
                movement_type TEXT NOT NULL,
                reference TEXT,
                synced INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        """)

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS pos_sales (
                id INTEGER PRIMARY KEY,
                local_id TEXT NOT NULL UNIQUE,
                server_id INTEGER,
                shift_id INTEGER,
                branch_id INTEGER,
                cashier_id INTEGER,
                customer_id INTEGER,
                subtotal REAL DEFAULT 0,
                discount REAL DEFAULT 0,
                tax REAL DEFAULT 0,
                total REAL NOT NULL DEFAULT 0,
                payment_method TEXT DEFAULT 'cash',
                amount_tendered REAL DEFAULT 0,
                change_amount REAL DEFAULT 0,
                status TEXT DEFAULT 'completed',
                synced INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                synced_at TEXT
            )
        """)
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_pos_sales_local ON pos_sales(local_id)")

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS pos_sale_items (
                id INTEGER PRIMARY KEY,
                sale_local_id TEXT NOT NULL,
                product_id INTEGER NOT NULL,
                product_name TEXT,
                qty REAL DEFAULT 1,
                price REAL DEFAULT 0,
                discount REAL DEFAULT 0,
                data_json TEXT
            )
        """)

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS shifts (
                id INTEGER PRIMARY KEY,
                local_id TEXT NOT NULL UNIQUE,
                server_id INTEGER,
                branch_id INTEGER NOT NULL,
                cashier_id INTEGER,
                opening_cash REAL DEFAULT 0,
                closing_cash REAL,
                status TEXT DEFAULT 'open',
                opened_at TEXT,
                closed_at TEXT,
                synced INTEGER DEFAULT 0
            )
        """)

        db.execSQL("""
            CREATE TABLE IF NOT EXISTS expiry_alerts (
                id INTEGER PRIMARY KEY,
                server_id INTEGER,
                product_id INTEGER,
                batch_id INTEGER,
                alert_type TEXT,
                message TEXT,
                data_json TEXT,
                synced_at TEXT
            )
        """)
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        Log.i(TAG, "Upgrading DB from v$oldVersion to v$newVersion")
        if (oldVersion < 2) {
            createV2Tables(db)
        }
        if (oldVersion < 3) {
            migrateToV3(db)
        }
        if (oldVersion < 4) {
            migrateToV4(db)
        }
        if (oldVersion < 5) {
            migrateToV5(db)
        }
        if (oldVersion < 6) {
            migrateToV6(db)
        }
    }

    private fun migrateToV5(db: SQLiteDatabase) {
        if (!hasColumn(db, "products", "sku")) {
            db.execSQL("ALTER TABLE products ADD COLUMN sku TEXT")
        }
        if (!hasColumn(db, "products", "category_id")) {
            db.execSQL("ALTER TABLE products ADD COLUMN category_id INTEGER DEFAULT 0")
        }
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_products_sku ON products(sku)")
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_products_category_id ON products(category_id)")
        backfillProductCategoryIdsFromCategoryNames(db)
        Log.i(TAG, "Migrated to v5: product sku/category_id columns")
    }

    private fun migrateToV6(db: SQLiteDatabase) {
        if (!hasColumn(db, "products", "sku")) {
            db.execSQL("ALTER TABLE products ADD COLUMN sku TEXT")
        }
        if (!hasColumn(db, "products", "category_id")) {
            db.execSQL("ALTER TABLE products ADD COLUMN category_id INTEGER DEFAULT 0")
        }
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_products_sku ON products(sku)")
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_products_category_id ON products(category_id)")
        backfillProductCategoryIdsFromCategoryNames(db)
        Log.i(TAG, "Migrated to v6: slim product query columns")
    }

    private fun migrateToV4(db: SQLiteDatabase) {
        purgePoisonSyncQueueItemsInTransaction(db)
        db.execSQL(
            """UPDATE transactions_local SET synced = 2, notes = 'abandoned_poison'
               WHERE local_id LIKE 'test-print%' OR local_id = 'test-print-001'"""
        )
        db.execSQL(
            """UPDATE pos_sales SET synced = 1
               WHERE local_id LIKE 'test-print%' OR local_id = 'test-print-001'"""
        )
        Log.i(TAG, "Migrated to v4: poison sync_queue purge")
    }

    private fun migrateToV3(db: SQLiteDatabase) {
        db.execSQL("ALTER TABLE transactions_local ADD COLUMN branch_id INTEGER DEFAULT 0")
        db.execSQL("ALTER TABLE transactions_local ADD COLUMN cashier_id INTEGER DEFAULT 0")
        db.execSQL("ALTER TABLE transactions_local ADD COLUMN shift_id INTEGER DEFAULT 0")
        db.execSQL("ALTER TABLE transactions_local ADD COLUMN member_id INTEGER DEFAULT 0")
        backfillTransactionContextFromQueue(db)
        skipStuckPriceConflictQueueItems(db)
        Log.i(TAG, "Migrated to v3: transaction context columns + queue cleanup")
    }

    /** Copy branch/cashier/shift from sync_queue JSON into legacy transaction rows. */
    private fun backfillTransactionContextFromQueue(db: SQLiteDatabase) {
        val cursor = db.rawQuery(
            """SELECT t.local_id, q.payload
               FROM transactions_local t
               INNER JOIN sync_queue q ON q.record_id = t.local_id
               WHERE (t.branch_id IS NULL OR t.branch_id = 0
                  OR t.cashier_id IS NULL OR t.cashier_id = 0)
               GROUP BY t.local_id""",
            null
        )
        var updated = 0
        while (cursor.moveToNext()) {
            val localId = cursor.getString(0)
            try {
                val payload = JSONObject(cursor.getString(1))
                val cv = ContentValues()
                payload.optInt("branch_id", 0).takeIf { it > 0 }?.let { cv.put("branch_id", it) }
                payload.optInt("cashier_id", 0).takeIf { it > 0 }?.let { cv.put("cashier_id", it) }
                payload.optInt("shift_id", 0).takeIf { it > 0 }?.let { cv.put("shift_id", it) }
                payload.optInt("member_id", 0).takeIf { it > 0 }?.let { cv.put("member_id", it) }
                if (cv.size() > 0) {
                    db.update("transactions_local", cv, "local_id = ?", arrayOf(localId))
                    updated++
                }
            } catch (_: Exception) {
            }
        }
        cursor.close()

        val posCursor = db.rawQuery(
            """SELECT t.local_id, p.branch_id, p.cashier_id, p.shift_id
               FROM transactions_local t
               INNER JOIN pos_sales p ON p.local_id = t.local_id
               WHERE t.branch_id = 0 OR t.cashier_id = 0""",
            null
        )
        while (posCursor.moveToNext()) {
            val cv = ContentValues()
            val branchId = posCursor.getInt(1)
            val cashierId = posCursor.getInt(2)
            val shiftId = posCursor.getInt(3)
            if (branchId > 0) cv.put("branch_id", branchId)
            if (cashierId > 0) cv.put("cashier_id", cashierId)
            if (shiftId > 0) cv.put("shift_id", shiftId)
            if (cv.size() > 0) {
                db.update("transactions_local", cv, "local_id = ?", arrayOf(posCursor.getString(0)))
                updated++
            }
        }
        posCursor.close()
        Log.i(TAG, "Backfilled transaction context for $updated rows")
    }

    /** Unblock queue tail: permanent price-conflict rows at max attempts. */
    private fun skipStuckPriceConflictQueueItems(db: SQLiteDatabase) {
        db.execSQL(
            """UPDATE sync_queue SET status = 'failed',
               error = COALESCE(error, 'skipped_price_conflict')
               WHERE status = 'pending'
               AND attempts >= 4
               AND (error LIKE '%Price conflict%' OR error LIKE '%price conflict%' OR error LIKE '%price_mismatch%')"""
        )
    }

    // --- Products ---

    /** Upsert products inside an existing transaction — do not call [withDb] from here. */
    internal fun upsertProductsInTransaction(
        db: SQLiteDatabase,
        products: JSONArray,
        start: Int,
        end: Int,
    ): Int {
        var count = 0
        for (j in start until end) {
            val p = products.getJSONObject(j)
            val cv = ContentValues().apply {
                put("server_id", p.optInt("id"))
                put("sku", p.optString("sku", ""))
                put("barcode", p.optString("barcode", ""))
                put("name", p.optString("name"))
                put("price", p.optDouble("price", 0.0))
                put("cost", p.optDouble("cost", 0.0))
                put("category_id", parseCategoryId(p))
                put("category", p.optString("category", ""))
                put("unit", p.optString("unit", "pc"))
                put("stock", p.optDouble("stock", 0.0))
                put("image_url", p.optString("image_url", ""))
                put("tax_rate", p.optDouble("tax_rate", 0.0))
                put("is_active", if (p.optBoolean("is_active", true)) 1 else 0)
                put("data_json", p.toString())
                put("synced_at", now())
                put("updated_at", now())
            }
            val existing = db.rawQuery(
                "SELECT id FROM products WHERE server_id = ?",
                arrayOf(p.optInt("id").toString())
            )
            try {
                if (existing.moveToFirst()) {
                    db.update("products", cv, "server_id = ?", arrayOf(p.optInt("id").toString()))
                } else {
                    db.insert("products", null, cv)
                }
                count++
            } finally {
                existing.close()
            }
        }
        return count
    }

    fun upsertProducts(products: JSONArray): Int {
        var count = 0
        val batchSize = 500
        var i = 0
        while (i < products.length()) {
            val end = minOf(i + batchSize, products.length())
            count += upsertProductsBatch(products, i, end)
            i += batchSize
            if (i < products.length()) Thread.yield()
        }
        return count
    }

    private fun upsertProductsBatch(products: JSONArray, start: Int, end: Int): Int = withDb {
        val db = writableDatabase
        db.beginTransaction()
        try {
            val count = upsertProductsInTransaction(db, products, start, end)
            db.setTransactionSuccessful()
            count
        } finally {
            db.endTransaction()
        }
    }

    fun getCustomerCount(): Int = dbOp {
        val cursor = readableDatabase.rawQuery("SELECT COUNT(*) FROM customers", null)
        try {
            if (cursor.moveToFirst()) cursor.getInt(0) else 0
        } finally {
            cursor.close()
        }
    }

    fun getProductCount(): Int = dbOp {
        val cursor = readableDatabase.rawQuery(
            "SELECT COUNT(*) FROM products WHERE is_active = 1", null
        )
        try {
            if (cursor.moveToFirst()) cursor.getInt(0) else 0
        } finally {
            cursor.close()
        }
    }

    fun getCategoryCount(): Int = dbOp { countTable("categories") }

    fun getProductCountForCategory(categoryId: Int): Int = dbOp {
        val patterns = categoryIdJsonLikePatterns(categoryId)
        val cursor = readableDatabase.rawQuery(
            """SELECT COUNT(*) FROM products WHERE is_active = 1
               AND (category_id = ? OR data_json LIKE ? OR data_json LIKE ? OR data_json LIKE ?)""",
            arrayOf(categoryId.toString(), *patterns)
        )
        try {
            if (cursor.moveToFirst()) cursor.getInt(0) else 0
        } finally {
            cursor.close()
        }
    }

    fun getProductsPage(
        offset: Int,
        limit: Int,
        categoryId: Int? = null,
    ): JSONArray = dbOp {
        val safeLimit = limit.coerceIn(1, MAX_PRODUCT_PAGE_SIZE)
        val safeOffset = offset.coerceAtLeast(0)
        val where = StringBuilder("is_active = 1")
        val args = mutableListOf<String>()
        if (categoryId != null && categoryId > 0) {
            appendCategoryIdFilter(where, args, categoryId)
        }
        args.add(safeLimit.toString())
        args.add(safeOffset.toString())
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            """SELECT server_id, id, sku, barcode, name, price, stock, category_id, category
               FROM products WHERE $where ORDER BY name LIMIT ? OFFSET ?""",
            args.toTypedArray()
        )
        try {
            while (cursor.moveToNext()) {
                arr.put(cursorToListProductJson(cursor))
            }
        } finally {
            cursor.close()
        }
        arr
    }

    /** Full catalog — prefer [getProductsPage] for API/bridge responses to avoid OOM. */
    fun getProducts(): JSONArray = getProductsPage(0, MAX_PRODUCT_PAGE_SIZE)

    fun searchProducts(query: String, limit: Int = MAX_SEARCH_RESULTS): JSONArray = dbOp {
        val safeLimit = limit.coerceIn(1, MAX_SEARCH_RESULTS)
        val q = "%$query%"
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            """SELECT server_id, id, sku, barcode, name, price, stock, category_id, category
               FROM products WHERE is_active = 1
               AND (name LIKE ? OR barcode LIKE ? OR sku LIKE ?)
               ORDER BY name LIMIT ?""",
            arrayOf(q, q, q, safeLimit.toString())
        )
        try {
            while (cursor.moveToNext()) {
                arr.put(cursorToListProductJson(cursor))
            }
        } finally {
            cursor.close()
        }
        arr
    }

    fun getCategories(): JSONArray = dbOp {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            "SELECT server_id, name FROM categories ORDER BY name",
            null
        )
        try {
            while (cursor.moveToNext()) {
                arr.put(JSONObject().apply {
                    put("id", cursor.getInt(0))
                    put("name", cursor.getString(1))
                })
            }
        } finally {
            cursor.close()
        }
        arr
    }

    fun getProductStock(productId: Int): Double = dbOp {
        val cursor = readableDatabase.rawQuery(
            "SELECT stock FROM products WHERE server_id = ? OR id = ? LIMIT 1",
            arrayOf(productId.toString(), productId.toString())
        )
        try {
            if (cursor.moveToFirst()) cursor.getDouble(0) else 0.0
        } finally {
            cursor.close()
        }
    }

    fun adjustProductStock(productId: Int, delta: Double) = withDb {
        writableDatabase.execSQL(
            "UPDATE products SET stock = MAX(0, stock + ?), updated_at = ? WHERE server_id = ? OR id = ?",
            arrayOf(delta, now(), productId.toString(), productId.toString())
        )
    }

    fun recordStockMovement(productId: Int, qty: Double, movementType: String, reference: String) = withDb {
        val cv = ContentValues().apply {
            put("local_id", java.util.UUID.randomUUID().toString())
            put("product_id", productId)
            put("qty", qty)
            put("movement_type", movementType)
            put("reference", reference)
            put("synced", 0)
            put("created_at", now())
        }
        writableDatabase.insert("stock_movements", null, cv)
    }

    fun getInventorySummary(): JSONArray = dbOp {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            """SELECT server_id, name, barcode, stock, category, updated_at
               FROM products WHERE is_active = 1 ORDER BY name""", null
        )
        try {
            while (cursor.moveToNext()) {
                arr.put(JSONObject().apply {
                    put("product_id", cursor.getLong(cursor.getColumnIndexOrThrow("server_id")))
                    put("name", cursor.getString(cursor.getColumnIndexOrThrow("name")))
                    put("barcode", cursor.getString(cursor.getColumnIndexOrThrow("barcode")))
                    put("stock", cursor.getDouble(cursor.getColumnIndexOrThrow("stock")))
                    put("category", cursor.getString(cursor.getColumnIndexOrThrow("category")))
                })
            }
        } finally {
            cursor.close()
        }
        arr
    }

    /** Single transaction for sale row + legacy txn + sync queue + receipt. */
    fun persistSale(txn: JSONObject, localId: String, receipt: JSONObject) = withDb {
        val db = writableDatabase
        db.beginTransaction()
        try {
            writePosSaleLocked(db, txn)
            writeTransactionLocked(db, txn)
            writeSyncQueueLocked(db, "push-transaction", "transactions_local", localId, txn)
            writeReceiptLocked(
                db, localId,
                receipt.optString("json", txn.toString()),
                receipt.optString("text", ""),
                receipt.optString("html", ""),
            )
            db.setTransactionSuccessful()
        } finally {
            db.endTransaction()
        }
    }

    fun savePosSale(txn: JSONObject) = withDb {
        writePosSaleLocked(writableDatabase, txn)
    }

    private fun writePosSaleLocked(db: SQLiteDatabase, txn: JSONObject) {
        val localId = txn.getString("local_id")
        val cv = ContentValues().apply {
            put("local_id", localId)
            put("shift_id", txn.optInt("shift_id", 0))
            put("branch_id", txn.optInt("branch_id", 0))
            put("cashier_id", txn.optInt("cashier_id", 0))
            put("customer_id", txn.optInt("member_id", txn.optInt("customer_id", 0)))
            put("subtotal", txn.optDouble("subtotal", 0.0))
            put("discount", txn.optDouble("discount", txn.optDouble("discount_total", 0.0)))
            put("tax", txn.optDouble("tax", 0.0))
            put("total", txn.optDouble("total", 0.0))
            put("payment_method", txn.optString("payment_method", "cash"))
            put("amount_tendered", txn.optDouble("amount_tendered", 0.0))
            put("change_amount", txn.optDouble("change_amount", txn.optDouble("change_due", 0.0)))
            put("status", txn.optString("status", "completed"))
            put("synced", 0)
            put("created_at", txn.optString("created_at", now()))
        }
        db.insertWithOnConflict("pos_sales", null, cv, SQLiteDatabase.CONFLICT_REPLACE)

        db.delete("pos_sale_items", "sale_local_id = ?", arrayOf(localId))
        val items = try {
            JSONArray(txn.optString("items_json", "[]"))
        } catch (_: Exception) {
            txn.optJSONArray("items") ?: JSONArray()
        }
        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            val itemCv = ContentValues().apply {
                put("sale_local_id", localId)
                put("product_id", item.optInt("product_id", item.optInt("id", 0)))
                put("product_name", item.optString("product_name", item.optString("name", "")))
                put("qty", item.optDouble("qty", item.optDouble("quantity", 1.0)))
                put("price", item.optDouble("price", 0.0))
                put("discount", item.optDouble("discount", 0.0))
                put("data_json", item.toString())
            }
            db.insert("pos_sale_items", null, itemCv)
        }
    }

    fun getReceipt(localId: String): JSONObject? = dbOp {
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM receipts WHERE transaction_local_id = ? ORDER BY id DESC LIMIT 1",
            arrayOf(localId)
        )
        try {
            if (cursor.moveToFirst()) cursorToJson(cursor) else null
        } finally {
            cursor.close()
        }
    }

    fun getActiveShift(): JSONObject? = dbOp {
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM shifts WHERE status = 'open' ORDER BY id DESC LIMIT 1", null
        )
        try {
            if (cursor.moveToFirst()) cursorToJson(cursor) else null
        } finally {
            cursor.close()
        }
    }

    fun openLocalShift(localId: String, branchId: Int, cashierId: Int, openingCash: Double): JSONObject = withDb {
        val cv = ContentValues().apply {
            put("local_id", localId)
            put("branch_id", branchId)
            put("cashier_id", cashierId)
            put("opening_cash", openingCash)
            put("status", "open")
            put("opened_at", now())
            put("synced", 0)
        }
        val rowId = writableDatabase.insert("shifts", null, cv)
        return JSONObject().apply {
            put("id", rowId)
            put("local_id", localId)
            put("branch_id", branchId)
            put("cashier_id", cashierId)
            put("opening_cash", openingCash)
            put("status", "open")
            put("opened_at", now())
        }
    }

    fun closeLocalShift(localId: String, closingCash: Double): JSONObject = withDb {
        val openCursor = readableDatabase.rawQuery(
            "SELECT * FROM shifts WHERE local_id = ? AND status = 'open' LIMIT 1",
            arrayOf(localId),
        )
        val openShift = try {
            if (openCursor.moveToFirst()) cursorToJson(openCursor) else null
        } finally {
            openCursor.close()
        }
        val totals = openShift?.let { aggregateShiftSales(it) }
            ?: JSONObject().put("total_sales", 0.0).put("transaction_count", 0)

        val cv = ContentValues().apply {
            put("closing_cash", closingCash)
            put("status", "closed")
            put("closed_at", now())
            put("synced", 0)
        }
        writableDatabase.update("shifts", cv, "local_id = ?", arrayOf(localId))
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM shifts WHERE local_id = ? LIMIT 1", arrayOf(localId)
        )
        try {
            val closed = if (cursor.moveToFirst()) cursorToJson(cursor) else JSONObject()
            closed.put("system_sales_total", totals.optDouble("total_sales", 0.0))
            closed.put("transaction_count", totals.optInt("transaction_count", 0))
            closed
        } finally {
            cursor.close()
        }
    }

    /** Map local SQLite shift row id or server shift id to server shift id for sync. */
    fun resolveServerShiftId(shiftIdOrRowId: Int): Int {
        if (shiftIdOrRowId <= 0) {
            return getActiveShift()?.optInt("server_id", 0)?.takeIf { it > 0 } ?: 0
        }
        return dbOp {
            val cursor = readableDatabase.rawQuery(
                "SELECT server_id, id FROM shifts WHERE id = ? OR server_id = ? ORDER BY id DESC LIMIT 1",
                arrayOf(shiftIdOrRowId.toString(), shiftIdOrRowId.toString()),
            )
            try {
                if (cursor.moveToFirst()) {
                    val serverId = cursor.getInt(cursor.getColumnIndexOrThrow("server_id"))
                    if (serverId > 0) serverId else cursor.getInt(cursor.getColumnIndexOrThrow("id"))
                } else {
                    shiftIdOrRowId
                }
            } finally {
                cursor.close()
            }
        }
    }

    fun aggregateShiftSales(shift: JSONObject): JSONObject = dbOp {
        val rowId = shift.optLong("id", 0L)
        val serverId = shift.optInt("server_id", 0)
        val cashierId = shift.optInt("cashier_id", 0)
        val openedAt = shift.optString("opened_at", "")
        aggregateShiftSalesInternal(rowId, serverId, cashierId, openedAt)
    }

    fun aggregateShiftSales(
        shiftRowId: Long,
        serverShiftId: Int,
        cashierId: Int,
        openedAt: String?,
    ): JSONObject = dbOp {
        aggregateShiftSalesInternal(shiftRowId, serverShiftId, cashierId, openedAt)
    }

    private fun aggregateShiftSalesInternal(
        shiftRowId: Long,
        serverShiftId: Int,
        cashierId: Int,
        openedAt: String?,
    ): JSONObject {
        val shiftIds = mutableListOf<Long>()
        if (shiftRowId > 0L) shiftIds.add(shiftRowId)
        if (serverShiftId > 0 && serverShiftId.toLong() !in shiftIds) shiftIds.add(serverShiftId.toLong())

        var totalSales = 0.0
        var txnCount = 0
        var discountTotal = 0.0
        var voidTotal = 0.0

        if (shiftIds.isNotEmpty()) {
            val placeholders = shiftIds.joinToString(",") { "?" }
            val args = shiftIds.map { it.toString() }.toTypedArray()
            val cursor = readableDatabase.rawQuery(
                """SELECT COALESCE(SUM(total), 0), COUNT(*), COALESCE(SUM(discount), 0)
                   FROM pos_sales WHERE status = 'completed' AND shift_id IN ($placeholders)""",
                args,
            )
            try {
                if (cursor.moveToFirst()) {
                    totalSales = cursor.getDouble(0)
                    txnCount = cursor.getInt(1)
                    discountTotal = cursor.getDouble(2)
                }
            } finally {
                cursor.close()
            }

            val voidCursor = readableDatabase.rawQuery(
                """SELECT COALESCE(SUM(total), 0)
                   FROM pos_sales WHERE status = 'voided' AND shift_id IN ($placeholders)""",
                args,
            )
            try {
                if (voidCursor.moveToFirst()) voidTotal = voidCursor.getDouble(0)
            } finally {
                voidCursor.close()
            }
        }

        if (cashierId > 0 && !openedAt.isNullOrBlank()) {
            val orphanCursor = readableDatabase.rawQuery(
                """SELECT COALESCE(SUM(total), 0), COUNT(*), COALESCE(SUM(discount), 0)
                   FROM pos_sales
                   WHERE status = 'completed' AND shift_id = 0 AND cashier_id = ?
                     AND created_at >= ?""",
                arrayOf(cashierId.toString(), openedAt),
            )
            try {
                if (orphanCursor.moveToFirst()) {
                    totalSales += orphanCursor.getDouble(0)
                    txnCount += orphanCursor.getInt(1)
                    discountTotal += orphanCursor.getDouble(2)
                }
            } finally {
                orphanCursor.close()
            }
        }

        return JSONObject().apply {
            put("total_sales", totalSales)
            put("transaction_count", txnCount)
            put("discount_total", discountTotal)
            put("void_total", voidTotal)
        }
    }

    fun getCashierTodaySalesStats(cashierId: Int): JSONObject = dbOp {
        val cursor = readableDatabase.rawQuery(
            """SELECT COALESCE(SUM(total), 0), COUNT(*)
               FROM pos_sales
               WHERE status = 'completed' AND cashier_id = ?
                 AND date(created_at) = date('now', 'localtime')""",
            arrayOf(cashierId.toString()),
        )
        try {
            JSONObject().apply {
                if (cursor.moveToFirst()) {
                    put("total_sales", cursor.getDouble(0))
                    put("transaction_count", cursor.getInt(1))
                } else {
                    put("total_sales", 0.0)
                    put("transaction_count", 0)
                }
            }
        } finally {
            cursor.close()
        }
    }

    fun getCashierTodayReadingStats(cashierId: Int): JSONObject = dbOp {
        val completed = readableDatabase.rawQuery(
            """SELECT COALESCE(SUM(total), 0), COUNT(*), COALESCE(SUM(discount), 0)
               FROM pos_sales
               WHERE status = 'completed' AND cashier_id = ?
                 AND date(created_at) = date('now', 'localtime')""",
            arrayOf(cashierId.toString()),
        )
        val voided = readableDatabase.rawQuery(
            """SELECT COALESCE(SUM(total), 0)
               FROM pos_sales
               WHERE status = 'voided' AND cashier_id = ?
                 AND date(created_at) = date('now', 'localtime')""",
            arrayOf(cashierId.toString()),
        )
        try {
            val totals = JSONObject()
            if (completed.moveToFirst()) {
                totals.put("total_sales", completed.getDouble(0))
                totals.put("transaction_count", completed.getInt(1))
                totals.put("discount_total", completed.getDouble(2))
            } else {
                totals.put("total_sales", 0.0)
                totals.put("transaction_count", 0)
                totals.put("discount_total", 0.0)
            }
            totals.put("void_total", if (voided.moveToFirst()) voided.getDouble(0) else 0.0)
            totals
        } finally {
            completed.close()
            voided.close()
        }
    }

    internal fun upsertCategoriesInTransaction(db: SQLiteDatabase, categories: JSONArray): Int {
        var count = 0
        for (i in 0 until categories.length()) {
            val c = categories.getJSONObject(i)
            val cv = ContentValues().apply {
                put("server_id", c.optInt("id"))
                put("name", c.optString("name"))
                put("data_json", c.toString())
                put("synced_at", now())
            }
            val existing = db.rawQuery(
                "SELECT id FROM categories WHERE server_id = ?",
                arrayOf(c.optInt("id").toString())
            )
            try {
                if (existing.moveToFirst()) {
                    db.update("categories", cv, "server_id = ?", arrayOf(c.optInt("id").toString()))
                } else {
                    db.insert("categories", null, cv)
                }
                count++
            } finally {
                existing.close()
            }
        }
        return count
    }

    fun upsertCategories(categories: JSONArray): Int = withDb {
        val db = writableDatabase
        db.beginTransaction()
        try {
            val count = upsertCategoriesInTransaction(db, categories)
            db.setTransactionSuccessful()
            count
        } finally {
            db.endTransaction()
        }
    }

    fun getProductByBarcode(barcode: String): JSONObject? = dbOp {
        val cursor = readableDatabase.rawQuery(
            """SELECT server_id, id, sku, barcode, name, price, stock, category_id, category
               FROM products WHERE barcode = ? AND is_active = 1 LIMIT 1""",
            arrayOf(barcode)
        )
        try {
            if (cursor.moveToFirst()) cursorToListProductJson(cursor) else null
        } finally {
            cursor.close()
        }
    }

    fun getProductByServerId(serverId: Int): JSONObject? = dbOp {
        val cursor = readableDatabase.rawQuery(
            """SELECT server_id, id, sku, barcode, name, price, stock, category_id, category
               FROM products WHERE server_id = ? AND is_active = 1 LIMIT 1""",
            arrayOf(serverId.toString())
        )
        try {
            if (cursor.moveToFirst()) cursorToListProductJson(cursor) else null
        } finally {
            cursor.close()
        }
    }

    // --- Customers ---

    internal fun upsertCustomersInTransaction(
        db: SQLiteDatabase,
        customers: JSONArray,
        start: Int,
        end: Int,
    ): Int {
        var count = 0
        for (j in start until end) {
            val c = customers.getJSONObject(j)
            val cv = ContentValues().apply {
                put("server_id", c.optInt("id"))
                put("name", c.optString("name"))
                put("phone", c.optString("phone", ""))
                put("email", c.optString("email", ""))
                put("address", c.optString("address", ""))
                put("data_json", c.toString())
                put("synced_at", now())
                put("updated_at", now())
            }
            val existing = db.rawQuery(
                "SELECT id FROM customers WHERE server_id = ?",
                arrayOf(c.optInt("id").toString())
            )
            try {
                if (existing.moveToFirst()) {
                    db.update("customers", cv, "server_id = ?", arrayOf(c.optInt("id").toString()))
                } else {
                    db.insert("customers", null, cv)
                }
                count++
            } finally {
                existing.close()
            }
        }
        return count
    }

    fun upsertCustomers(customers: JSONArray): Int {
        var count = 0
        val batchSize = 50
        var i = 0
        while (i < customers.length()) {
            val end = minOf(i + batchSize, customers.length())
            count += upsertCustomersBatch(customers, i, end)
            i += batchSize
            if (i < customers.length()) Thread.yield()
        }
        return count
    }

    private fun upsertCustomersBatch(customers: JSONArray, start: Int, end: Int): Int = withDb {
        val db = writableDatabase
        db.beginTransaction()
        try {
            val count = upsertCustomersInTransaction(db, customers, start, end)
            db.setTransactionSuccessful()
            count
        } finally {
            db.endTransaction()
        }
    }

    fun getCustomers(): JSONArray = dbOp {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery("SELECT * FROM customers ORDER BY name", null)
        try {
            while (cursor.moveToNext()) {
                arr.put(cursorToJson(cursor))
            }
        } finally {
            cursor.close()
        }
        arr
    }

    // --- Transactions ---

    fun saveTransaction(txn: JSONObject): Long = withDb {
        writeTransactionLocked(writableDatabase, txn)
    }

    private fun writeTransactionLocked(db: SQLiteDatabase, txn: JSONObject): Long {
        val cv = ContentValues().apply {
            put("local_id", txn.getString("local_id"))
            put("type", txn.optString("type", "sale"))
            put("status", txn.optString("status", "completed"))
            put("customer_id", txn.optInt("customer_id", txn.optInt("member_id", 0)))
            put("branch_id", txn.optInt("branch_id", 0))
            put("cashier_id", txn.optInt("cashier_id", 0))
            put("shift_id", txn.optInt("shift_id", 0))
            put("member_id", txn.optInt("member_id", 0))
            put("items_json", txn.optString("items_json", "[]"))
            put("subtotal", txn.optDouble("subtotal", 0.0))
            put("discount", txn.optDouble("discount", txn.optDouble("discount_total", 0.0)))
            put("tax", txn.optDouble("tax", 0.0))
            put("total", txn.optDouble("total", 0.0))
            put("payment_method", txn.optString("payment_method", "cash"))
            put("amount_tendered", txn.optDouble("amount_tendered", 0.0))
            put("change_amount", txn.optDouble("change_amount", 0.0))
            put("cashier_name", txn.optString("cashier_name", ""))
            put("notes", txn.optString("notes", ""))
            put("receipt_json", txn.optString("receipt_json", ""))
            put("synced", 0)
            put("created_at", now())
        }
        return db.insertWithOnConflict(
            "transactions_local", null, cv, SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    private fun writeSyncQueueLocked(
        db: SQLiteDatabase,
        action: String,
        tableName: String,
        recordId: String,
        payload: JSONObject,
    ) {
        val cv = ContentValues().apply {
            put("action", action)
            put("table_name", tableName)
            put("record_id", recordId)
            put("payload", payload.toString())
            put("status", "pending")
            put("created_at", now())
        }
        db.insert("sync_queue", null, cv)
    }

    private fun writeReceiptLocked(db: SQLiteDatabase, localId: String, json: String, text: String, html: String) {
        val cv = ContentValues().apply {
            put("transaction_local_id", localId)
            put("receipt_json", json)
            put("receipt_text", text)
            put("receipt_html", html)
            put("created_at", now())
        }
        db.insert("receipts", null, cv)
    }

    fun getUnsyncedTransactions(): JSONArray = dbOp {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM transactions_local WHERE synced = 0 ORDER BY created_at", null
        )
        try {
            while (cursor.moveToNext()) {
                arr.put(cursorToJson(cursor))
            }
        } finally {
            cursor.close()
        }
        arr
    }

    fun markTransactionSynced(localId: String, serverId: Int) = withDb {
        val cv = ContentValues().apply {
            put("synced", 1)
            put("server_id", serverId)
            put("synced_at", now())
        }
        writableDatabase.update("transactions_local", cv, "local_id = ?", arrayOf(localId))
    }

    /** Stop retrying invalid/test transactions without deleting sale history. */
    fun abandonPoisonTransaction(localId: String, reason: String) = withDb {
        val cv = ContentValues().apply {
            put("synced", 2)
            put("notes", reason)
        }
        writableDatabase.update("transactions_local", cv, "local_id = ?", arrayOf(localId))
        writableDatabase.update(
            "pos_sales",
            ContentValues().apply { put("synced", 1) },
            "local_id = ?",
            arrayOf(localId),
        )
    }

    fun isPoisonTransaction(localId: String, payload: JSONObject?): Boolean {
        if (localId.startsWith("test-print")) return true
        payload ?: return false
        val items = payload.optJSONArray("items")
            ?: try { JSONArray(payload.optString("items_json", "[]")) } catch (_: Exception) { null }
            ?: return false
        if (items.length() == 0) return false
        for (i in 0 until items.length()) {
            val item = items.getJSONObject(i)
            val productId = item.optInt("product_id", item.optInt("id", -1))
            if (productId <= 0) return true
        }
        return false
    }

    fun markShiftSynced(localId: String, serverId: Int) = withDb {
        val cv = ContentValues().apply {
            put("synced", 1)
            put("server_id", serverId)
        }
        writableDatabase.update("shifts", cv, "local_id = ?", arrayOf(localId))
    }

    fun getTransactionCount(): Int = dbOp {
        val cursor = readableDatabase.rawQuery("SELECT COUNT(*) FROM transactions_local", null)
        try {
            if (cursor.moveToFirst()) cursor.getInt(0) else 0
        } finally {
            cursor.close()
        }
    }

    fun getUnsyncedCount(): Int = dbOp {
        val cursor = readableDatabase.rawQuery(
            "SELECT COUNT(*) FROM transactions_local WHERE synced = 0", null
        )
        try {
            if (cursor.moveToFirst()) cursor.getInt(0) else 0
        } finally {
            cursor.close()
        }
    }

    // --- Sync Queue ---

    fun enqueueSyncAction(action: String, tableName: String, recordId: String, payload: JSONObject) = withDb {
        writeSyncQueueLocked(writableDatabase, action, tableName, recordId, payload)
    }

    fun getSyncQueuePayloadForLocalId(localId: String): JSONObject? = dbOp {
        val cursor = readableDatabase.rawQuery(
            """SELECT payload FROM sync_queue
               WHERE record_id = ? AND status IN ('pending', 'failed')
               ORDER BY id DESC LIMIT 1""",
            arrayOf(localId)
        )
        try {
            if (cursor.moveToFirst()) {
                try {
                    JSONObject(cursor.getString(cursor.getColumnIndexOrThrow("payload")))
                } catch (_: Exception) {
                    null
                }
            } else null
        } finally {
            cursor.close()
        }
    }

    fun getPosSaleContext(localId: String): JSONObject? = dbOp {
        val cursor = readableDatabase.rawQuery(
            "SELECT branch_id, cashier_id, shift_id FROM pos_sales WHERE local_id = ? LIMIT 1",
            arrayOf(localId)
        )
        try {
            if (cursor.moveToFirst()) {
                JSONObject().apply {
                    put("branch_id", cursor.getInt(0))
                    put("cashier_id", cursor.getInt(1))
                    put("shift_id", cursor.getInt(2))
                }
            } else null
        } finally {
            cursor.close()
        }
    }

    fun getPendingSyncItems(limit: Int = 50): JSONArray = dbOp {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            """SELECT * FROM sync_queue
               WHERE status = 'pending' AND attempts < max_attempts
               ORDER BY attempts ASC, created_at ASC
               LIMIT ?""",
            arrayOf(limit.toString())
        )
        try {
            while (cursor.moveToNext()) {
                arr.put(cursorToJson(cursor))
            }
        } finally {
            cursor.close()
        }
        arr
    }

    fun markSyncItemDone(id: Long) = withDb {
        writableDatabase.delete("sync_queue", "id = ?", arrayOf(id.toString()))
    }

    fun markSyncItemFailed(id: Long, error: String, httpStatus: Int = 0) = withDb {
        if (isPermanentSyncError(error, httpStatus)) {
            writableDatabase.execSQL(
                "UPDATE sync_queue SET attempts = attempts + 1, error = ?, status = 'failed' WHERE id = ?",
                arrayOf(error, id.toString())
            )
        } else {
            writableDatabase.execSQL(
                "UPDATE sync_queue SET attempts = attempts + 1, error = ?, status = CASE WHEN attempts + 1 >= max_attempts THEN 'failed' ELSE 'pending' END WHERE id = ?",
                arrayOf(error, id.toString())
            )
        }
    }

    fun abandonSyncItem(id: Long, reason: String) = withDb {
        writableDatabase.execSQL(
            "UPDATE sync_queue SET status = 'failed', error = ?, attempts = max_attempts WHERE id = ?",
            arrayOf(reason, id.toString())
        )
    }

    /** Drop known-bad queue rows (test prints, invalid product ids, stuck duplicates). */
    fun purgePoisonSyncQueueItems(): Int = withDb {
        purgePoisonSyncQueueItemsInTransaction(writableDatabase)
    }

    fun isPoisonSyncItem(action: String, recordId: String, payload: JSONObject): Boolean {
        if (recordId == "test-print-001" || recordId.startsWith("test-print")) return true
        if (action.contains("print", ignoreCase = true)) {
            val productId = payload.optInt("product_id", payload.optInt("id", -1))
            if (productId <= 0) return true
        }
        if (action == "shift_open" && getActiveShift()?.optString("local_id") != recordId) {
            val err = payload.optString("_last_error", "")
            if (err.contains("duplicate", ignoreCase = true) || err.contains("already open", ignoreCase = true)) {
                return true
            }
        }
        return false
    }

    private fun purgePoisonSyncQueueItemsInTransaction(db: SQLiteDatabase): Int {
        var purged = 0
        purged += db.delete(
            "sync_queue",
            "record_id LIKE 'test-print%' OR record_id = 'test-print-001'",
            null
        )
        db.execSQL(
            """UPDATE sync_queue SET status = 'failed',
               error = COALESCE(error, 'skipped_invalid_product')
               WHERE status = 'pending'
               AND (action LIKE '%print%' OR action = 'product_print')
               AND (payload LIKE '%"product_id":0%' OR payload LIKE '%"product_id": 0%')"""
        )
        purged += db.delete(
            "sync_queue",
            """status = 'failed' AND attempts >= max_attempts
               AND (record_id LIKE 'test-print%' OR error LIKE '%422%' OR error LIKE '%validation%')""",
            null
        )
        return purged
    }

    private fun isPermanentSyncError(error: String, httpStatus: Int): Boolean {
        if (httpStatus == 422 || httpStatus == 400) return true
        val lower = error.lowercase()
        return isPermanentPriceConflict(lower) ||
            lower.contains("validation") ||
            lower.contains("unprocessable") ||
            lower.contains("invalid product") ||
            lower.contains("product_id") && lower.contains("required") ||
            (lower.contains("shift") && (lower.contains("already open") || lower.contains("duplicate")))
    }

    private fun isPermanentPriceConflict(lower: String): Boolean {
        return lower.contains("price conflict") ||
            lower.contains("price_mismatch") ||
            lower.contains("price conflicts detected")
    }

    fun getSyncQueueCount(): Int = dbOp {
        val cursor = readableDatabase.rawQuery(
            "SELECT COUNT(*) FROM sync_queue WHERE status = 'pending'", null
        )
        try {
            if (cursor.moveToFirst()) cursor.getInt(0) else 0
        } finally {
            cursor.close()
        }
    }

    // --- Receipts ---

    fun saveReceipt(transactionLocalId: String, json: String, text: String = "", html: String = "") = withDb {
        writeReceiptLocked(writableDatabase, transactionLocalId, json, text, html)
    }

    // --- Settings ---

    internal fun setSettingInTransaction(db: SQLiteDatabase, key: String, value: String) {
        val cv = ContentValues().apply {
            put("key", key)
            put("value", value)
        }
        db.insertWithOnConflict("settings", null, cv, SQLiteDatabase.CONFLICT_REPLACE)
    }

    fun setSetting(key: String, value: String) = withDb {
        setSettingInTransaction(writableDatabase, key, value)
    }

    fun getSetting(key: String): String? = dbOp {
        val cursor = readableDatabase.rawQuery(
            "SELECT value FROM settings WHERE key = ?", arrayOf(key)
        )
        try {
            if (cursor.moveToFirst()) cursor.getString(0) else null
        } finally {
            cursor.close()
        }
    }

    // --- Sync Log ---

    fun logSync(direction: String, table: String, count: Int, status: String, error: String? = null) = withDb {
        val cv = ContentValues().apply {
            put("direction", direction)
            put("table_name", table)
            put("records_count", count)
            put("status", status)
            put("error", error)
            put("completed_at", now())
        }
        writableDatabase.insert("sync_log", null, cv)
    }

    // --- Stats ---

    fun getOfflineStats(): JSONObject = dbOp {
        val active = getActiveShift()
        val shiftTotals = active?.let { aggregateShiftSales(it) }
        JSONObject().apply {
            put("products", countTable("products"))
            put("categories", countTable("categories"))
            put("customers", countTable("customers"))
            put("transactions", getTransactionCount())
            put("unsynced_transactions", getUnsyncedCount())
            put("sync_queue", getSyncQueueCount())
            put("receipts", countTable("receipts"))
            put("pos_sales", countTable("pos_sales"))
            if (shiftTotals != null) {
                put("shift_total_sales", shiftTotals.optDouble("total_sales", 0.0))
                put("shift_transaction_count", shiftTotals.optInt("transaction_count", 0))
            }
        }
    }

    fun getFefoBatches(productId: Int): JSONArray = dbOp {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            """SELECT id, product_id, qty, expiry_date, batch_code
               FROM inventory_batches
               WHERE product_id = ? AND qty > 0
               ORDER BY CASE WHEN expiry_date IS NULL OR expiry_date = '' THEN 1 ELSE 0 END,
                        expiry_date ASC, id ASC""",
            arrayOf(productId.toString())
        )
        try {
            while (cursor.moveToNext()) {
                arr.put(JSONObject().apply {
                    put("id", cursor.getLong(0))
                    put("product_id", cursor.getInt(1))
                    put("qty", cursor.getDouble(2))
                    put("expiry_date", cursor.getString(3))
                    put("batch_code", cursor.getString(4))
                })
            }
        } finally {
            cursor.close()
        }
        arr
    }

    fun deductBatchQty(batchRowId: Long, qty: Double) = withDb {
        writableDatabase.execSQL(
            "UPDATE inventory_batches SET qty = MAX(0, qty - ?) WHERE id = ?",
            arrayOf(qty, batchRowId.toString())
        )
    }

    internal fun upsertInventoryBatchesInTransaction(db: SQLiteDatabase, batches: JSONArray): Int {
        var count = 0
        for (i in 0 until batches.length()) {
            val b = batches.getJSONObject(i)
            val serverId = b.optInt("id", 0)
            val productId = b.optInt("product_id", 0)
            if (productId <= 0) continue
            val cv = ContentValues().apply {
                put("server_id", serverId)
                put("product_id", productId)
                put("branch_id", b.optInt("branch_id", 0))
                put("batch_code", b.optString("batch_code", ""))
                put("expiry_date", b.optString("expiry_date", ""))
                put("qty", b.optDouble("quantity", b.optDouble("qty", 0.0)))
                put("cost", b.optDouble("cost_price", 0.0))
                put("data_json", b.toString())
                put("synced_at", now())
            }
            val existing = db.rawQuery(
                "SELECT id FROM inventory_batches WHERE server_id = ?",
                arrayOf(serverId.toString())
            )
            try {
                if (existing.moveToFirst()) {
                    db.update("inventory_batches", cv, "server_id = ?", arrayOf(serverId.toString()))
                } else {
                    db.insert("inventory_batches", null, cv)
                }
                count++
            } finally {
                existing.close()
            }
        }
        return count
    }

    fun upsertInventoryBatches(batches: JSONArray): Int = withDb {
        val db = writableDatabase
        db.beginTransaction()
        try {
            val count = upsertInventoryBatchesInTransaction(db, batches)
            db.setTransactionSuccessful()
            count
        } finally {
            db.endTransaction()
        }
    }

    internal fun upsertExpiryAlertsInTransaction(db: SQLiteDatabase, alerts: JSONArray): Int {
        var count = 0
        for (i in 0 until alerts.length()) {
            val a = alerts.getJSONObject(i)
            val serverId = a.optInt("id", 0)
            val cv = ContentValues().apply {
                put("server_id", serverId)
                put("product_id", a.optInt("product_id", 0))
                put("batch_id", a.optInt("inventory_batch_id", a.optInt("batch_id", 0)))
                put("alert_type", a.optString("alert_type", ""))
                put("message", a.optString("message", a.optString("alert_type", "")))
                put("data_json", a.toString())
                put("synced_at", now())
            }
            val existing = db.rawQuery(
                "SELECT id FROM expiry_alerts WHERE server_id = ?",
                arrayOf(serverId.toString())
            )
            try {
                if (serverId > 0 && existing.moveToFirst()) {
                    db.update("expiry_alerts", cv, "server_id = ?", arrayOf(serverId.toString()))
                } else {
                    db.insert("expiry_alerts", null, cv)
                }
                count++
            } finally {
                existing.close()
            }
        }
        return count
    }

    fun upsertExpiryAlerts(alerts: JSONArray): Int = withDb {
        val db = writableDatabase
        db.beginTransaction()
        try {
            val count = upsertExpiryAlertsInTransaction(db, alerts)
            db.setTransactionSuccessful()
            count
        } finally {
            db.endTransaction()
        }
    }

    // --- Helpers ---

    private fun countTable(table: String): Int {
        val cursor = readableDatabase.rawQuery("SELECT COUNT(*) FROM $table", null)
        return try {
            if (cursor.moveToFirst()) cursor.getInt(0) else 0
        } finally {
            cursor.close()
        }
    }

    private fun cursorToJson(cursor: Cursor): JSONObject {
        val obj = JSONObject()
        for (i in 0 until cursor.columnCount) {
            val name = cursor.getColumnName(i)
            when (cursor.getType(i)) {
                Cursor.FIELD_TYPE_NULL -> obj.put(name, JSONObject.NULL)
                Cursor.FIELD_TYPE_INTEGER -> obj.put(name, cursor.getLong(i))
                Cursor.FIELD_TYPE_FLOAT -> obj.put(name, cursor.getDouble(i))
                Cursor.FIELD_TYPE_STRING -> obj.put(name, cursor.getString(i))
                Cursor.FIELD_TYPE_BLOB -> obj.put(name, "[blob]")
            }
        }
        return obj
    }

    private fun hasColumn(db: SQLiteDatabase, table: String, column: String): Boolean {
        val cursor = db.rawQuery("PRAGMA table_info($table)", null)
        return try {
            while (cursor.moveToNext()) {
                if (cursor.getString(cursor.getColumnIndexOrThrow("name")) == column) return true
            }
            false
        } finally {
            cursor.close()
        }
    }

    private fun parseCategoryId(product: JSONObject): Int {
        val direct = product.optInt("category_id", 0)
        if (direct > 0) return direct
        val nested = product.optJSONObject("category")?.optInt("id", 0) ?: 0
        return if (nested > 0) nested else 0
    }

    private fun backfillProductCategoryIdsFromCategoryNames(db: SQLiteDatabase) {
        db.execSQL(
            """UPDATE products
               SET category_id = COALESCE((
                   SELECT c.server_id FROM categories c
                   WHERE c.name = products.category
                   ORDER BY c.id DESC LIMIT 1
               ), category_id)
               WHERE COALESCE(category_id, 0) = 0
                 AND category IS NOT NULL
                 AND category != ''"""
        )
    }

    /** JSON1 ([json_extract]) is unavailable on some Android SQLite builds — use LIKE + parse instead. */
    private fun categoryIdJsonLikePatterns(categoryId: Int): Array<String> = arrayOf(
        "%\"category_id\":$categoryId%",
        "%\"category_id\": $categoryId%",
        "%\"category_id\":\"$categoryId\"%",
    )

    private fun appendCategoryIdFilter(where: StringBuilder, args: MutableList<String>, categoryId: Int) {
        where.append(" AND (category_id = ? OR data_json LIKE ? OR data_json LIKE ? OR data_json LIKE ?)")
        args.add(categoryId.toString())
        categoryIdJsonLikePatterns(categoryId).forEach { args.add(it) }
    }

    private fun parseSkuAndCategoryFromDataJson(dataJson: String): Pair<String, Int> {
        if (dataJson.isBlank()) return "" to 0
        return try {
            val dj = JSONObject(dataJson)
            dj.optString("sku", "") to dj.optInt("category_id", 0)
        } catch (_: Exception) {
            "" to 0
        }
    }

    /** Slim product row for grid/search — omits data_json unless [includeDataJson]. */
    private fun cursorToListProductJson(cursor: Cursor, includeDataJson: Boolean = false): JSONObject {
        if (includeDataJson) return cursorToJson(cursor)
        fun col(name: String): Int = cursor.getColumnIndex(name)
        fun str(name: String): String {
            val idx = col(name)
            return if (idx >= 0 && !cursor.isNull(idx)) cursor.getString(idx) else ""
        }
        fun dbl(name: String): Double {
            val idx = col(name)
            return if (idx >= 0 && !cursor.isNull(idx)) cursor.getDouble(idx) else 0.0
        }
        fun lng(name: String): Long {
            val idx = col(name)
            return if (idx >= 0 && !cursor.isNull(idx)) cursor.getLong(idx) else 0L
        }
        val skuCol = str("sku")
        val dataJson = str("data_json")
        val (parsedSku, parsedCategoryId) = parseSkuAndCategoryFromDataJson(dataJson)
        val obj = JSONObject().apply {
            put("server_id", lng("server_id"))
            put("id", lng("id"))
            put("barcode", str("barcode"))
            put("name", str("name"))
            put("price", dbl("price"))
            put("stock", dbl("stock"))
            put("category", str("category"))
            put("sku", skuCol.ifBlank { parsedSku })
            val catIdx = col("category_id")
            if (catIdx >= 0 && !cursor.isNull(catIdx)) {
                put("category_id", cursor.getLong(catIdx).toInt())
            } else if (parsedCategoryId > 0) {
                put("category_id", parsedCategoryId)
            }
        }
        return obj
    }

    private fun now(): String {
        return java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", java.util.Locale.US)
            .apply { timeZone = java.util.TimeZone.getTimeZone("UTC") }
            .format(java.util.Date())
    }
}
