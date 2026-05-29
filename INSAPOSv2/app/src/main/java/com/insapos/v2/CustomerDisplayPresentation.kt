package com.insapos.v2

import android.app.Presentation
import android.content.Context
import android.graphics.Typeface
import android.os.Bundle
import android.view.Display
import android.view.Gravity
import android.view.View
import android.widget.LinearLayout
import android.widget.TextView
import org.json.JSONArray
import org.json.JSONObject
import java.text.NumberFormat
import java.util.Locale

class CustomerDisplayPresentation(
    context: Context,
    display: Display,
) : Presentation(context, display) {

    private lateinit var titleView: TextView
    private lateinit var subtitleView: TextView
    private lateinit var itemsContainer: LinearLayout
    private lateinit var scrollItems: View
    private lateinit var totalsPanel: View
    private lateinit var discountRow: View
    private lateinit var subtotalView: TextView
    private lateinit var discountView: TextView
    private lateinit var totalView: TextView
    private lateinit var paymentInfoView: TextView
    private lateinit var footerMessageView: TextView

    private val currency = NumberFormat.getCurrencyInstance(Locale("en", "PH")).apply {
        currency = java.util.Currency.getInstance("PHP")
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.presentation_customer_display)
        titleView = findViewById(R.id.tvDisplayTitle)
        subtitleView = findViewById(R.id.tvDisplaySubtitle)
        itemsContainer = findViewById(R.id.itemsContainer)
        scrollItems = findViewById(R.id.scrollItems)
        totalsPanel = findViewById(R.id.totalsPanel)
        discountRow = findViewById(R.id.discountRow)
        subtotalView = findViewById(R.id.tvSubtotal)
        discountView = findViewById(R.id.tvDiscount)
        totalView = findViewById(R.id.tvTotal)
        paymentInfoView = findViewById(R.id.tvPaymentInfo)
        footerMessageView = findViewById(R.id.tvFooterMessage)
        renderWelcome()
    }

    fun render(payload: JSONObject) {
        if (!::titleView.isInitialized) return
        when (payload.optString("mode", "cart")) {
            "welcome" -> renderWelcome(payload)
            "thank_you" -> renderThankYou(payload)
            else -> renderCart(payload)
        }
    }

    private fun renderWelcome(payload: JSONObject = JSONObject()) {
        titleView.text = payload.optString("store_name", "INSAPOS")
        subtitleView.text = payload.optString("message", "Welcome to INSAPOS — your order will appear here")
        scrollItems.visibility = View.GONE
        totalsPanel.visibility = View.GONE
        footerMessageView.visibility = View.GONE
    }

    private fun renderCart(payload: JSONObject) {
        titleView.text = payload.optString("store_name", "INSAPOS")
        subtitleView.text = payload.optString("subtitle", "Your order")
        scrollItems.visibility = View.VISIBLE
        totalsPanel.visibility = View.VISIBLE
        footerMessageView.visibility = View.GONE

        itemsContainer.removeAllViews()
        val items = payload.optJSONArray("items") ?: JSONArray()
        if (items.length() == 0) {
            itemsContainer.addView(emptyLine("No items yet"))
        } else {
            for (i in 0 until items.length()) {
                val item = items.optJSONObject(i) ?: continue
                itemsContainer.addView(cartLine(item))
            }
        }

        val subtotal = payload.optDouble("subtotal", 0.0)
        val discount = payload.optDouble("discount", 0.0)
        val total = payload.optDouble("total", subtotal - discount)
        subtotalView.text = formatMoney(subtotal)
        if (discount > 0) {
            discountRow.visibility = View.VISIBLE
            discountView.text = "-${formatMoney(discount)}"
        } else {
            discountRow.visibility = View.GONE
        }
        totalView.text = formatMoney(total)
        paymentInfoView.visibility = View.GONE
    }

    private fun renderThankYou(payload: JSONObject) {
        titleView.text = payload.optString("store_name", "INSAPOS")
        subtitleView.text = payload.optString("subtitle", "Payment complete")
        scrollItems.visibility = View.GONE
        totalsPanel.visibility = View.VISIBLE
        footerMessageView.visibility = View.VISIBLE
        footerMessageView.text = payload.optString("message", "Thank you — see you again at INSAPOS!")

        val total = payload.optDouble("total", 0.0)
        val change = payload.optDouble("change", 0.0)
        val method = payload.optString("payment_method", "Cash")
        subtotalView.text = formatMoney(total)
        discountRow.visibility = View.GONE
        totalView.text = formatMoney(total)

        val info = buildString {
            append("Paid via $method")
            if (change > 0) append(" · Change: ${formatMoney(change)}")
        }
        paymentInfoView.text = info
        paymentInfoView.visibility = View.VISIBLE
    }

    private fun cartLine(item: JSONObject): View {
        val row = LinearLayout(context).apply {
            orientation = LinearLayout.HORIZONTAL
            setPadding(0, 8, 0, 8)
        }
        val qty = item.optInt("qty", 1)
        val name = item.optString("name", item.optString("product_name", "Item"))
        val price = item.optDouble("price", 0.0)
        val lineTotal = qty * price

        val nameView = TextView(context).apply {
            layoutParams = LinearLayout.LayoutParams(0, LinearLayout.LayoutParams.WRAP_CONTENT, 1f)
            text = "${qty}x $name"
            textSize = 18f
            setTextColor(0xFF111827.toInt())
        }
        val priceView = TextView(context).apply {
            layoutParams = LinearLayout.LayoutParams(
                LinearLayout.LayoutParams.WRAP_CONTENT,
                LinearLayout.LayoutParams.WRAP_CONTENT
            )
            text = formatMoney(lineTotal)
            textSize = 18f
            setTypeface(typeface, Typeface.BOLD)
            setTextColor(0xFF065F46.toInt())
            gravity = Gravity.END
        }
        row.addView(nameView)
        row.addView(priceView)
        return row
    }

    private fun emptyLine(text: String): View {
        return TextView(context).apply {
            this.text = text
            textSize = 16f
            setTextColor(0xFF6B7280.toInt())
            gravity = Gravity.CENTER
            setPadding(0, 24, 0, 24)
        }
    }

    private fun formatMoney(value: Double): String {
        return try {
            currency.format(value)
        } catch (_: Exception) {
            "₱${"%.2f".format(Locale.US, value)}"
        }
    }
}
