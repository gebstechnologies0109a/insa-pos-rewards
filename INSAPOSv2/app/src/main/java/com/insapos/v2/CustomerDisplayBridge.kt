package com.insapos.v2

import android.webkit.JavascriptInterface
import com.insapos.v2.db.OfflineDatabase
import org.json.JSONObject

class CustomerDisplayBridge(
    private val manager: CustomerDisplayManager,
    private val dbProvider: () -> OfflineDatabase?,
    private val storeNameProvider: () -> String = { "INSAPOS" },
) {

    @JavascriptInterface
    fun getCustomerDisplaySettings(): String {
        return CustomerDisplaySettings.toJson(dbProvider(), storeNameProvider()).toString()
    }

    @JavascriptInterface
    fun updateCustomerDisplayCart(cartJson: String) {
        try {
            val payload = JSONObject(cartJson)
            manager.updatePayload(payload)
        } catch (_: Exception) {
        }
    }
}
