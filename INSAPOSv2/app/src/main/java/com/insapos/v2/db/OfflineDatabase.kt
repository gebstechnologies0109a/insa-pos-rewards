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
        private const val DB_VERSION = 1
    }

    override fun onCreate(db: SQLiteDatabase) {
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

        Log.i(TAG, "Database created with all tables")
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        Log.i(TAG, "Upgrading DB from v$oldVersion to v$newVersion")
    }

    // --- Products ---

    fun upsertProducts(products: JSONArray): Int {
        val db = writableDatabase
        var count = 0
        db.beginTransaction()
        try {
            for (i in 0 until products.length()) {
                val p = products.getJSONObject(i)
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
                if (existing.moveToFirst()) {
                    db.update("products", cv, "server_id = ?", arrayOf(p.optInt("id").toString()))
                } else {
                    db.insert("products", null, cv)
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

    fun getProducts(): JSONArray {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM products WHERE is_active = 1 ORDER BY name", null
        )
        while (cursor.moveToNext()) {
            arr.put(cursorToJson(cursor))
        }
        cursor.close()
        return arr
    }

    fun searchProducts(query: String): JSONArray {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM products WHERE is_active = 1 AND (name LIKE ? OR barcode LIKE ?) ORDER BY name",
            arrayOf("%$query%", "%$query%")
        )
        while (cursor.moveToNext()) {
            arr.put(cursorToJson(cursor))
        }
        cursor.close()
        return arr
    }

    fun getProductByBarcode(barcode: String): JSONObject? {
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM products WHERE barcode = ? AND is_active = 1 LIMIT 1",
            arrayOf(barcode)
        )
        val result = if (cursor.moveToFirst()) cursorToJson(cursor) else null
        cursor.close()
        return result
    }

    // --- Customers ---

    fun upsertCustomers(customers: JSONArray): Int {
        val db = writableDatabase
        var count = 0
        db.beginTransaction()
        try {
            for (i in 0 until customers.length()) {
                val c = customers.getJSONObject(i)
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
                if (existing.moveToFirst()) {
                    db.update("customers", cv, "server_id = ?", arrayOf(c.optInt("id").toString()))
                } else {
                    db.insert("customers", null, cv)
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

    fun getCustomers(): JSONArray {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery("SELECT * FROM customers ORDER BY name", null)
        while (cursor.moveToNext()) {
            arr.put(cursorToJson(cursor))
        }
        cursor.close()
        return arr
    }

    // --- Transactions ---

    fun saveTransaction(txn: JSONObject): Long {
        val cv = ContentValues().apply {
            put("local_id", txn.getString("local_id"))
            put("type", txn.optString("type", "sale"))
            put("status", txn.optString("status", "completed"))
            put("customer_id", txn.optInt("customer_id", 0))
            put("items_json", txn.optString("items_json", "[]"))
            put("subtotal", txn.optDouble("subtotal", 0.0))
            put("discount", txn.optDouble("discount", 0.0))
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
        return writableDatabase.insertWithOnConflict(
            "transactions_local", null, cv, SQLiteDatabase.CONFLICT_REPLACE
        )
    }

    fun getUnsyncedTransactions(): JSONArray {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM transactions_local WHERE synced = 0 ORDER BY created_at", null
        )
        while (cursor.moveToNext()) {
            arr.put(cursorToJson(cursor))
        }
        cursor.close()
        return arr
    }

    fun markTransactionSynced(localId: String, serverId: Int) {
        val cv = ContentValues().apply {
            put("synced", 1)
            put("server_id", serverId)
            put("synced_at", now())
        }
        writableDatabase.update("transactions_local", cv, "local_id = ?", arrayOf(localId))
    }

    fun getTransactionCount(): Int {
        val cursor = readableDatabase.rawQuery("SELECT COUNT(*) FROM transactions_local", null)
        val count = if (cursor.moveToFirst()) cursor.getInt(0) else 0
        cursor.close()
        return count
    }

    fun getUnsyncedCount(): Int {
        val cursor = readableDatabase.rawQuery(
            "SELECT COUNT(*) FROM transactions_local WHERE synced = 0", null
        )
        val count = if (cursor.moveToFirst()) cursor.getInt(0) else 0
        cursor.close()
        return count
    }

    // --- Sync Queue ---

    fun enqueueSyncAction(action: String, tableName: String, recordId: String, payload: JSONObject) {
        val cv = ContentValues().apply {
            put("action", action)
            put("table_name", tableName)
            put("record_id", recordId)
            put("payload", payload.toString())
            put("status", "pending")
            put("created_at", now())
        }
        writableDatabase.insert("sync_queue", null, cv)
    }

    fun getPendingSyncItems(limit: Int = 50): JSONArray {
        val arr = JSONArray()
        val cursor = readableDatabase.rawQuery(
            "SELECT * FROM sync_queue WHERE status = 'pending' AND attempts < max_attempts ORDER BY created_at LIMIT ?",
            arrayOf(limit.toString())
        )
        while (cursor.moveToNext()) {
            arr.put(cursorToJson(cursor))
        }
        cursor.close()
        return arr
    }

    fun markSyncItemDone(id: Long) {
        writableDatabase.delete("sync_queue", "id = ?", arrayOf(id.toString()))
    }

    fun markSyncItemFailed(id: Long, error: String) {
        writableDatabase.execSQL(
            "UPDATE sync_queue SET attempts = attempts + 1, error = ?, status = CASE WHEN attempts + 1 >= max_attempts THEN 'failed' ELSE 'pending' END WHERE id = ?",
            arrayOf(error, id.toString())
        )
    }

    fun getSyncQueueCount(): Int {
        val cursor = readableDatabase.rawQuery(
            "SELECT COUNT(*) FROM sync_queue WHERE status = 'pending'", null
        )
        val count = if (cursor.moveToFirst()) cursor.getInt(0) else 0
        cursor.close()
        return count
    }

    // --- Receipts ---

    fun saveReceipt(transactionLocalId: String, json: String, text: String = "", html: String = "") {
        val cv = ContentValues().apply {
            put("transaction_local_id", transactionLocalId)
            put("receipt_json", json)
            put("receipt_text", text)
            put("receipt_html", html)
            put("created_at", now())
        }
        writableDatabase.insert("receipts", null, cv)
    }

    // --- Settings ---

    fun setSetting(key: String, value: String) {
        val cv = ContentValues().apply {
            put("key", key)
            put("value", value)
        }
        writableDatabase.insertWithOnConflict("settings", null, cv, SQLiteDatabase.CONFLICT_REPLACE)
    }

    fun getSetting(key: String): String? {
        val cursor = readableDatabase.rawQuery(
            "SELECT value FROM settings WHERE key = ?", arrayOf(key)
        )
        val value = if (cursor.moveToFirst()) cursor.getString(0) else null
        cursor.close()
        return value
    }

    // --- Sync Log ---

    fun logSync(direction: String, table: String, count: Int, status: String, error: String? = null) {
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

    fun getOfflineStats(): JSONObject {
        return JSONObject().apply {
            put("products", countTable("products"))
            put("customers", countTable("customers"))
            put("transactions", getTransactionCount())
            put("unsynced_transactions", getUnsyncedCount())
            put("sync_queue", getSyncQueueCount())
            put("receipts", countTable("receipts"))
        }
    }

    // --- Helpers ---

    private fun countTable(table: String): Int {
        val cursor = readableDatabase.rawQuery("SELECT COUNT(*) FROM $table", null)
        val count = if (cursor.moveToFirst()) cursor.getInt(0) else 0
        cursor.close()
        return count
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
