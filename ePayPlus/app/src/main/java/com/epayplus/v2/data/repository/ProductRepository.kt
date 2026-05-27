package com.epayplus.v2.data.repository

import com.epayplus.v2.data.local.dao.ProductDao
import com.epayplus.v2.data.local.entity.ProductEntity
import com.epayplus.v2.data.local.entity.SmsTemplateEntity
import com.epayplus.v2.data.remote.EPayApiService
import kotlinx.coroutines.flow.Flow
import javax.inject.Inject

class ProductRepository @Inject constructor(
    private val productDao: ProductDao,
    private val apiService: EPayApiService
) {
    fun getProductsByType(type: String): Flow<List<ProductEntity>> =
        productDao.getProductsByType(type)

    fun getProductsByProvider(providerCode: String): Flow<List<ProductEntity>> =
        productDao.getProductsByProvider(providerCode)

    fun getProvidersByType(type: String) = productDao.getProvidersByType(type)

    fun getCategoriesByType(type: String): Flow<List<String>> =
        productDao.getCategoriesByType(type)

    fun getProductsByCategory(category: String): Flow<List<ProductEntity>> =
        productDao.getProductsByCategory(category)

    fun getAllTemplates(): Flow<List<SmsTemplateEntity>> = productDao.getAllTemplates()

    suspend fun getProductCountByType(type: String): Int = productDao.getProductCountByType(type)

    suspend fun getProductById(id: Long): ProductEntity? = productDao.getProductById(id)

    suspend fun refreshProducts(type: String): Result<Int> {
        return try {
            val response = when (type) {
                "ELOAD" -> apiService.getEloadProducts()
                "BILLS" -> apiService.getBillsProducts()
                "ECASH" -> apiService.getEcashProducts()
                "RFID" -> apiService.getRfidProducts()
                else -> return Result.failure(Exception("Unknown product type"))
            }

            if (response.isSuccessful && response.body()?.success == true) {
                val products = response.body()!!.products.mapIndexed { index, product ->
                    val category = when {
                        product.category.isNotBlank() -> product.category
                        product.productKind == "promo" -> "Promo"
                        type == "ELOAD" -> "Prepaid Load"
                        else -> product.category
                    }
                    ProductEntity(
                        type = type,
                        providerCode = product.providerCode,
                        providerName = product.providerName,
                        productCode = product.code,
                        productName = product.name,
                        amount = product.amount,
                        fee = product.fee,
                        description = product.description,
                        keyword = product.keyword,
                        category = category,
                        sortOrder = index
                    )
                }
                if (products.isNotEmpty()) {
                    productDao.deleteByType(type)
                    productDao.insertAll(products)
                }
                Result.success(products.size)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch products"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun ensureProductsExist() {
        val types = listOf("ELOAD", "BILLS", "ECASH", "RFID")
        val missing = types.filter { productDao.getProductCountByType(it) == 0 }
        if (missing.isNotEmpty()) {
            insertDefaultProducts(missing)
        }
    }

    suspend fun insertDefaultProducts(onlyTypes: List<String>? = null) {
        val types = onlyTypes ?: listOf("ELOAD", "BILLS", "ECASH", "RFID")
        val toInsert = mutableListOf<ProductEntity>()
        if ("ELOAD" in types) {
            toInsert += buildDefaultEloadProducts()
        }
        if ("BILLS" in types) {
            toInsert += buildDefaultBillsProducts()
        }
        if ("ECASH" in types) {
            toInsert += buildDefaultEcashProducts()
        }
        if ("RFID" in types) {
            toInsert += buildDefaultRfidProducts()
        }
        if (toInsert.isNotEmpty()) {
            productDao.insertAll(toInsert)
        }
    }

    private fun buildDefaultEloadProducts(): List<ProductEntity> {
        val networks = listOf(
            "GLOBE" to "Globe",
            "SMART" to "Smart",
            "TNT" to "Talk N Text",
            "SUN" to "Sun Cellular",
            "TM" to "TM",
            "DITO" to "DITO",
            "GOMO" to "GOMO",
            "CIGNAL" to "Cignal Prepaid",
            "GSAT" to "GSAT",
            "SMARTBRO" to "Smart Bro",
            "CHERRYPREPAID" to "Cherry Prepaid",
            "GAMEPIN" to "Game Pin",
            "KURYENTELOAD" to "Kuryente Load",
        )
        val amounts = listOf(10, 20, 30, 50, 100, 150, 200, 300, 500, 1000)
        val products = mutableListOf<ProductEntity>()
        for ((code, name) in networks) {
            for (amount in amounts) {
                products += createProduct(
                    "ELOAD", code, name, "${code}_$amount", "$name $amount", amount.toDouble(),
                    "$name prepaid load", category = "Prepaid Load"
                )
            }
            products += createProduct(
                "ELOAD", code, name, "${code}_PROMO_GO50", "$name GO50", 50.0,
                "$name promo", category = "Promo"
            )
        }
        return products
    }

    private fun buildDefaultBillsProducts(): List<ProductEntity> = listOf(
            createProduct("BILLS", "MERALCO", "Meralco", "MERALCO_PAY", "Meralco Payment", 0.0, "Electric bill payment", category = "Electricity"),
            createProduct("BILLS", "VECO", "VECO", "VECO_PAY", "VECO Payment", 0.0, "Visayan Electric", category = "Electricity"),
            createProduct("BILLS", "MORE_POWER", "MORE Power", "MORE_POWER_PAY", "MORE Power Payment", 0.0, "MORE Electric Power", category = "Electricity"),
            createProduct("BILLS", "BOHOL_LIGHT", "Bohol Light", "BOHOL_LIGHT_PAY", "Bohol Light Payment", 0.0, "Bohol Light", category = "Electricity"),
            createProduct("BILLS", "MAYNILAD", "Maynilad", "MAYNILAD_PAY", "Maynilad Payment", 0.0, "Water bill payment", category = "Water"),
            createProduct("BILLS", "MANILA_WATER", "Manila Water", "MWATER_PAY", "Manila Water Payment", 0.0, "Manila Water", category = "Water"),
            createProduct("BILLS", "MCWD", "Metro Cebu Water", "MCWD_PAY", "MCWD Payment", 0.0, "Metro Cebu Water District", category = "Water"),
            createProduct("BILLS", "PRIMEWATER", "Prime Water", "PRIMEWATER_PAY", "Prime Water Payment", 0.0, "Prime Water", category = "Water"),
            createProduct("BILLS", "PLDT", "PLDT", "PLDT_PAY", "PLDT Payment", 0.0, "PLDT postpaid bill", category = "Telecommunications"),
            createProduct("BILLS", "GLOBE_BILL", "Globe Postpaid", "GLOBE_BILL_PAY", "Globe Bill Payment", 0.0, "Globe postpaid/broadband", category = "Telecommunications"),
            createProduct("BILLS", "SMART_BILL", "Smart Postpaid", "SMART_BILL_PAY", "Smart Postpaid", 0.0, "Smart postpaid bill", category = "Telecommunications"),
            createProduct("BILLS", "SUN_BILL", "Sun Postpaid", "SUN_BILL_PAY", "Sun Postpaid", 0.0, "Sun postpaid bill", category = "Telecommunications"),
            createProduct("BILLS", "DITO_BILL", "DITO Postpaid", "DITO_BILL_PAY", "DITO Postpaid", 0.0, "DITO postpaid bill", category = "Telecommunications"),
            createProduct("BILLS", "INNOVE", "Innove", "INNOVE_PAY", "Innove Payment", 0.0, "Globelines/Innove postpaid", category = "Telecommunications"),
            createProduct("BILLS", "CONVERGE", "Converge ICT", "CONVERGE_PAY", "Converge Payment", 0.0, "Converge Fiber", category = "Internet/Cable"),
            createProduct("BILLS", "SKY", "Sky Cable", "SKY_PAY", "Sky Cable Payment", 0.0, "Sky Cable/Broadband", category = "Internet/Cable"),
            createProduct("BILLS", "CIGNAL_BILL", "Cignal TV", "CIGNAL_BILL_PAY", "Cignal Payment", 0.0, "Cignal TV postpaid", category = "Internet/Cable"),
            createProduct("BILLS", "SSS", "SSS", "SSS_PAY", "SSS Contribution", 0.0, "SSS monthly contribution", category = "Government"),
            createProduct("BILLS", "PAGIBIG", "Pag-IBIG", "PAGIBIG_PAY", "Pag-IBIG Payment", 0.0, "Pag-IBIG Fund", category = "Government"),
            createProduct("BILLS", "PHILHEALTH", "PhilHealth", "PHILHEALTH_PAY", "PhilHealth Payment", 0.0, "PhilHealth contribution", category = "Government"),
            createProduct("BILLS", "NBI", "NBI Clearance", "NBI_PAY", "NBI Clearance Payment", 0.0, "NBI Clearance", category = "Government"),
            createProduct("BILLS", "SUNLIFE", "Sun Life", "SUNLIFE_PAY", "Sun Life Premium", 0.0, "Insurance premium", category = "Insurance"),
            createProduct("BILLS", "PRULIFE", "Pru Life UK", "PRULIFE_PAY", "Pru Life Payment", 0.0, "Insurance premium", category = "Insurance"),
            createProduct("BILLS", "AXA", "AXA Philippines", "AXA_PAY", "AXA Premium", 0.0, "Insurance premium", category = "Insurance"),
            createProduct("BILLS", "BPI_LOAN", "BPI", "BPI_LOAN_PAY", "BPI Loan Payment", 0.0, "BPI Loan", category = "Loans"),
            createProduct("BILLS", "BDO_LOAN", "BDO", "BDO_LOAN_PAY", "BDO Loan Payment", 0.0, "BDO Loan", category = "Loans"),
            createProduct("BILLS", "CEBUANA", "Cebuana Lhuillier", "CEBUANA_PAY", "Cebuana Loan", 0.0, "Cebuana Loan", category = "Loans"),
            createProduct("BILLS", "HOME_CREDIT", "Home Credit", "HCREDIT_PAY", "Home Credit Payment", 0.0, "Home Credit", category = "Loans"),
            createProduct("BILLS", "BPI_CC", "BPI Credit Card", "BPI_CC_PAY", "BPI Credit Card", 0.0, "BPI CC", category = "Credit Cards"),
            createProduct("BILLS", "BDO_CC", "BDO Credit Card", "BDO_CC_PAY", "BDO Credit Card", 0.0, "BDO CC", category = "Credit Cards"),
            createProduct("BILLS", "METROBANK_CC", "Metrobank CC", "MB_CC_PAY", "Metrobank Credit Card", 0.0, "Metrobank CC", category = "Credit Cards"),
            createProduct("BILLS", "CAMELLA", "Camella Homes", "CAMELLA_PAY", "Camella Payment", 0.0, "Camella Homes", category = "Real Estate"),
            createProduct("BILLS", "LUMINA", "Lumina Homes", "LUMINA_PAY", "Lumina Payment", 0.0, "Lumina Homes", category = "Real Estate"),
        )

    private fun buildDefaultEcashProducts(): List<ProductEntity> = listOf(
            createProduct("ECASH", "GCASH", "GCash", "GCASH_CASHIN", "GCash Cash-In", 0.0, "GCash wallet top-up"),
            createProduct("ECASH", "MAYA", "Maya", "MAYA_CASHIN", "Maya Cash-In", 0.0, "Maya (PayMaya) wallet top-up"),
            createProduct("ECASH", "SHOPEEPAY", "ShopeePay", "SHOPEE_CASHIN", "ShopeePay Cash-In", 0.0, "ShopeePay wallet top-up"),
            createProduct("ECASH", "GRABPAY", "GrabPay", "GRAB_CASHIN", "GrabPay Cash-In", 0.0, "GrabPay wallet top-up"),
            createProduct("ECASH", "COINS", "Coins.ph", "COINS_CASHIN", "Coins.ph Cash-In", 0.0, "Coins.ph wallet top-up"),
            createProduct("ECASH", "PAYPAL", "PayPal", "PAYPAL_CASHIN", "PayPal Cash-In", 0.0, "PayPal wallet top-up"),
            createProduct("ECASH", "LAZADA", "Lazada Wallet", "LAZADA_CASHIN", "Lazada Wallet Cash-In", 0.0, "Lazada Wallet top-up"),
        )

    private fun buildDefaultRfidProducts(): List<ProductEntity> = listOf(
            createProduct("RFID", "EASYTRIP", "EasyTrip", "EASYTRIP_RELOAD", "EasyTrip Reload", 0.0, "EasyTrip RFID reload", category = "RFID Services"),
            createProduct("RFID", "AUTOSWEEP", "Autosweep", "AUTOSWEEP_RELOAD", "Autosweep Reload", 0.0, "Autosweep RFID reload", category = "RFID Services"),
            createProduct("RFID", "TAPNGO", "Tap&Go", "TAPNGO_RELOAD", "Tap&Go Reload", 0.0, "Tap&Go RFID reload", category = "RFID Services"),
            createProduct("RFID", "CONNECT", "Connect RFID", "CONNECT_RELOAD", "Connect Reload", 0.0, "Connect RFID reload", category = "RFID Services"),
            createProduct("RFID", "ETC", "ETC RFID", "ETC_RELOAD", "ETC Reload", 0.0, "ETC RFID reload", category = "RFID Services"),
            createProduct("RFID", "CCLEX_RFID", "CCLEX RFID", "CCLEX_RELOAD", "CCLEX Reload", 0.0, "CCLEX RFID reload", category = "RFID Services"),
            createProduct("RFID", "RFID_ECARD", "RFID eCard", "RFID_ECARD_RELOAD", "RFID eCard Reload", 0.0, "RFID eCard reload", category = "RFID Services"),
            createProduct("RFID", "OTHER", "Other Toll RFID", "OTHER_RELOAD", "Other RFID Reload", 0.0, "Other toll RFID reload", category = "RFID Services"),
        )

    private fun createProduct(
        type: String,
        providerCode: String,
        providerName: String,
        productCode: String,
        productName: String,
        amount: Double,
        description: String,
        keyword: String = "",
        category: String = ""
    ) = ProductEntity(
        type = type,
        providerCode = providerCode,
        providerName = providerName,
        productCode = productCode,
        productName = productName,
        amount = amount,
        description = description,
        keyword = keyword,
        category = category
    )
}
