package com.insapos.v2.sync

import android.util.JsonReader
import android.util.JsonToken
import com.insapos.v2.db.OfflineDatabase
import org.json.JSONArray
import org.json.JSONObject
import java.io.InputStream
import java.io.InputStreamReader
import java.nio.charset.StandardCharsets

/**
 * Streams a catalog JSON file (`{"products":[...],"categories":[...]}`) into SQLite
 * without loading the full payload into heap.
 */
class CatalogStreamImporter(
    private val db: OfflineDatabase,
    private val batchSize: Int = DEFAULT_BATCH_SIZE,
) {

    data class ImportResult(
        val productsImported: Int,
        val categoriesImported: Int,
        val readyAfterFirstBatch: Boolean,
    )

    fun importFromStream(
        input: InputStream,
        onBatch: (imported: Int, phase: String) -> Unit = { _, _ -> },
    ): ImportResult {
        var productsImported = 0
        var categoriesImported = 0
        var readyAfterFirstBatch = false

        InputStreamReader(input, StandardCharsets.UTF_8).use { reader ->
            JsonReader(reader).use { jsonReader ->
                jsonReader.beginObject()
                while (jsonReader.hasNext()) {
                    when (jsonReader.nextName()) {
                        "products" -> {
                            jsonReader.beginArray()
                            var batch = JSONArray()
                            while (jsonReader.hasNext()) {
                                batch.put(readObject(jsonReader))
                                if (batch.length() >= batchSize) {
                                    productsImported += db.upsertProducts(batch)
                                    if (!readyAfterFirstBatch) {
                                        readyAfterFirstBatch = productsImported > 0
                                    }
                                    onBatch(productsImported, "importing")
                                    batch = JSONArray()
                                    Thread.yield()
                                }
                            }
                            jsonReader.endArray()
                            if (batch.length() > 0) {
                                productsImported += db.upsertProducts(batch)
                                if (!readyAfterFirstBatch) {
                                    readyAfterFirstBatch = productsImported > 0
                                }
                                onBatch(productsImported, "importing")
                            }
                        }
                        "categories" -> {
                            val categories = readArray(jsonReader)
                            if (categories.length() > 0) {
                                categoriesImported = db.upsertCategories(categories)
                            }
                        }
                        else -> jsonReader.skipValue()
                    }
                }
                jsonReader.endObject()
            }
        }

        return ImportResult(productsImported, categoriesImported, readyAfterFirstBatch)
    }

    companion object {
        const val DEFAULT_BATCH_SIZE = 500

        fun readObject(reader: JsonReader): JSONObject {
            val obj = JSONObject()
            reader.beginObject()
            while (reader.hasNext()) {
                val name = reader.nextName()
                when (reader.peek()) {
                    JsonToken.STRING -> obj.put(name, reader.nextString())
                    JsonToken.NUMBER -> {
                        val raw = reader.nextString()
                        obj.put(
                            name,
                            if (raw.contains('.') || raw.contains('e', ignoreCase = true)) {
                                raw.toDouble()
                            } else {
                                raw.toLong()
                            },
                        )
                    }
                    JsonToken.BOOLEAN -> obj.put(name, reader.nextBoolean())
                    JsonToken.NULL -> {
                        reader.nextNull()
                        obj.put(name, JSONObject.NULL)
                    }
                    JsonToken.BEGIN_OBJECT -> obj.put(name, readObject(reader))
                    JsonToken.BEGIN_ARRAY -> obj.put(name, readArray(reader))
                    else -> reader.skipValue()
                }
            }
            reader.endObject()
            return obj
        }

        fun readArray(reader: JsonReader): JSONArray {
            val arr = JSONArray()
            reader.beginArray()
            while (reader.hasNext()) {
                when (reader.peek()) {
                    JsonToken.BEGIN_OBJECT -> arr.put(readObject(reader))
                    JsonToken.STRING -> arr.put(reader.nextString())
                    JsonToken.NUMBER -> {
                        val raw = reader.nextString()
                        arr.put(
                            if (raw.contains('.') || raw.contains('e', ignoreCase = true)) {
                                raw.toDouble()
                            } else {
                                raw.toLong()
                            },
                        )
                    }
                    JsonToken.BOOLEAN -> arr.put(reader.nextBoolean())
                    JsonToken.NULL -> {
                        reader.nextNull()
                        arr.put(JSONObject.NULL)
                    }
                    JsonToken.BEGIN_ARRAY -> arr.put(readArray(reader))
                    else -> reader.skipValue()
                }
            }
            reader.endArray()
            return arr
        }
    }
}
