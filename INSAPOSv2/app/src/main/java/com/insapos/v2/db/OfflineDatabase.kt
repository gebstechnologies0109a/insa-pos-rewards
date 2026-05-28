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
        private const val DB_VERSION = 3
        const val DEFAULT_PRODUCT_PAGE_SIZE = 500
        const val MAX_PRODUCT_PAGE_SIZE = 2000
        const val MAX_SEARCH_RESULTS = 500
    }

    private val dbLock = Any()

    /** Single-writer lock — all SQLite access must go through this to avoid pool exhaustion. */
    private inline fun <T> withDb(block: () -> T): T = synchronized(dbLock) { block() }

    private inline fun <T> dbOp(block: () -> T): T = withDb(block)

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
                barcode TEXT,
                name TEXT NOT NULL,
                price REAL NOT NULL DEFAULT 0,
                cost REAL DEFAULT 0,
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
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode)")
        db.execSQL("CREATE INDEX IF NOT EXISTS idx_products_server_id ON products(server_id)")

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

    fun upsertProducts(products: JSONArray): Int {
        var count = 0
        val batchSize = 80
        var i = 0
        while (i < products.length()) {
            val end = minOf(i + batchSize, products.length())
            count += upsertProductsBatch(products, i, end)
            i += batchSize
        }
        return count
    }

    private fun upsertProductsBatch(products: JSONArray, start: Int, end: Int): Int = withDb {
        val db = writableDatabase
        var count = 0
        db.beginTransaction()
        try {
            for (j in start until end) {
                val p = products.getJSONObject(j)
                val cv = ContentValues().apply {
                    put("server_id", p.optInt("id"))
                    put("barcode", p.optString("barcode", ""))
                    put("name", p.optString("name"))
                    put("price", p.optDouble("price", 0.0))
                    put("cost", p.optDouble("cost", 0.0))
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
            db.setTransactionSuccessful()
        } finally {
            db.endTransaction()
        }
        count
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

    fun getProductsPage(offset: Int, limit: Int): JSONArray = dbOp {
        val safeLimit = limit.coerceIn(1, MAX_PRODUCT_PAGE_SIZE)
        val safeOffset = offset.coerceAtLeast(0)
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM products WHERE is_active = 1 ORDER BY name LIMIT ? OFFSET ?",
            arrayOf(safeLimit.toString(), safeOffset.toString())
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

    /** Full catalog — prefer [getProductsPage] for API/bridge responses to avoid OOM. */
    fun getProducts(): JSONArray = getProductsPage(0, MAX_PRODUCT_PAGE_SIZE)

    fun searchProducts(query: String, limit: Int = MAX_SEARCH_RESULTS): JSONArray = dbOp {
        val safeLimit = limit.coerceIn(1, MAX_SEARCH_RESULTS)
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM products WHERE is_active = 1 AND (name LIKE ? OR barcode LIKE ?) ORDER BY name LIMIT ?",
            arrayOf("%$query%", "%$query%", safeLimit.toString())
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

    fun getProductStock(productId: Int): Double = withDb {
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

    fun getInventorySummary(): JSONArray = withDb {
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

    fun getReceipt(localId: String): JSONObject? = withDb {
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

    fun getActiveShift(): JSONObject? = withDb {
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
            if (cursor.moveToFirst()) cursorToJson(cursor) else JSONObject()
        } finally {
            cursor.close()
        }
    }

    fun upsertCategories(categories: JSONArray): Int = withDb {
        var count = 0
        val db = writableDatabase
        db.beginTransaction()
        try {
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
                if (existing.moveToFirst()) {
                    db.update("categories", cv, "server_id = ?", arrayOf(c.optInt("id").toString()))
                } else {
                    db.insert("categories", null, cv)
                }
                existing.close()
                count++
            }
            db.setTransactionSuccessful()
        } finally {
            db.endTransaction()
        }
        return count
    }

    fun getProductByBarcode(barcode: String): JSONObject? = withDb {
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM products WHERE barcode = ? AND is_active = 1 LIMIT 1",
            arrayOf(barcode)
        )
        try {
            if (cursor.moveToFirst()) cursorToJson(cursor) else null
        } finally {
            cursor.close()
        }
    }

    // --- Customers ---

    fun upsertCustomers(customers: JSONArray): Int {
        var count = 0
        val batchSize = 80
        var i = 0
        while (i < customers.length()) {
            val end = minOf(i + batchSize, customers.length())
            count += upsertCustomersBatch(customers, i, end)
            i += batchSize
        }
        return count
    }

    private fun upsertCustomersBatch(customers: JSONArray, start: Int, end: Int): Int = withDb {
        val db = writableDatabase
        var count = 0
        db.beginTransaction()
        try {
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
            db.setTransactionSuccessful()
        } finally {
            db.endTransaction()
        }
        count
    }

    fun getCustomers(): JSONArray = withDb {
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

    fun getUnsyncedTransactions(): JSONArray = withDb {
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

    fun getTransactionCount(): Int = withDb {
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

    fun getSyncQueuePayloadForLocalId(localId: String): JSONObject? = withDb {
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

    fun getPosSaleContext(localId: String): JSONObject? = withDb {
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

    fun getPendingSyncItems(limit: Int = 50): JSONArray = withDb {
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

    fun markSyncItemFailed(id: Long, error: String) = withDb {
        if (isPermanentPriceConflict(error)) {
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

    private fun isPermanentPriceConflict(error: String): Boolean {
        val lower = error.lowercase()
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

    fun setSetting(key: String, value: String) = withDb {
        val cv = ContentValues().apply {
            put("key", key)
            put("value", value)
        }
        writableDatabase.insertWithOnConflict("settings", null, cv, SQLiteDatabase.CONFLICT_REPLACE)
    }

    fun getSetting(key: String): String? = withDb {
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
        JSONObject().apply {
            put("products", countTable("products"))
            put("customers", countTable("customers"))
            put("transactions", getTransactionCount())
            put("unsynced_transactions", getUnsyncedCount())
            put("sync_queue", getSyncQueueCount())
            put("receipts", countTable("receipts"))
        }
    }

    fun getFefoBatches(productId: Int): JSONArray = withDb {
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

    fun upsertInventoryBatches(batches: JSONArray): Int = withDb {
        var count = 0
        val db = writableDatabase
        db.beginTransaction()
        try {
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
                if (existing.moveToFirst()) {
                    db.update("inventory_batches", cv, "server_id = ?", arrayOf(serverId.toString()))
                } else {
                    db.insert("inventory_batches", null, cv)
                }
                existing.close()
                count++
            }
            db.setTransactionSuccessful()
        } finally {
            db.endTransaction()
        }
        return count
    }

    fun upsertExpiryAlerts(alerts: JSONArray): Int = withDb {
        var count = 0
        val db = writableDatabase
        db.beginTransaction()
        try {
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
                if (serverId > 0 && existing.moveToFirst()) {
                    db.update("expiry_alerts", cv, "server_id = ?", arrayOf(serverId.toString()))
                } else {
                    db.insert("expiry_alerts", null, cv)
                }
                existing.close()
                count++
            }
            db.setTransactionSuccessful()
        } finally {
            db.endTransaction()
        }
        return count
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

    private fun now(): String {
        return java.text.SimpleDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSS'Z'", java.util.Locale.US)
            .apply { timeZone = java.util.TimeZone.getTimeZone("UTC") }
            .format(java.util.Date())
    }
}
