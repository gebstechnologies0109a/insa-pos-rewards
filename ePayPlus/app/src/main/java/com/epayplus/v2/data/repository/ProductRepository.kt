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
                        category = product.category,
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
        val total = productDao.getProductCountByType("ELOAD") +
                productDao.getProductCountByType("BILLS") +
                productDao.getProductCountByType("ECASH") +
                productDao.getProductCountByType("RFID")
        if (total == 0) {
            insertDefaultProducts()
        }
    }

    suspend fun insertDefaultProducts() {
        val eloadProviders = listOf(
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE5", "Globe 5", 5.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE10", "Globe 10", 10.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE15", "Globe 15", 15.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE20", "Globe 20", 20.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE30", "Globe 30", 30.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE50", "Globe 50", 50.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE100", "Globe 100", 100.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE150", "Globe 150", 150.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE200", "Globe 200", 200.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE300", "Globe 300", 300.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE500", "Globe 500", 500.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "GLOBE1000", "Globe 1000", 1000.0, "Globe prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART5", "Smart 5", 5.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART10", "Smart 10", 10.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART15", "Smart 15", 15.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART20", "Smart 20", 20.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART30", "Smart 30", 30.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART50", "Smart 50", 50.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART100", "Smart 100", 100.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART200", "Smart 200", 200.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART300", "Smart 300", 300.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART500", "Smart 500", 500.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "SMART1000", "Smart 1000", 1000.0, "Smart prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT5", "TNT 5", 5.0, "TNT prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT10", "TNT 10", 10.0, "TNT prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT15", "TNT 15", 15.0, "TNT prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT20", "TNT 20", 20.0, "TNT prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT30", "TNT 30", 30.0, "TNT prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT50", "TNT 50", 50.0, "TNT prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT100", "TNT 100", 100.0, "TNT prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT300", "TNT 300", 300.0, "TNT prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT500", "TNT 500", 500.0, "TNT prepaid load"),
            createProduct("ELOAD", "DITO", "DITO", "DITO5", "DITO 5", 5.0, "DITO prepaid load"),
            createProduct("ELOAD", "DITO", "DITO", "DITO10", "DITO 10", 10.0, "DITO prepaid load"),
            createProduct("ELOAD", "DITO", "DITO", "DITO20", "DITO 20", 20.0, "DITO prepaid load"),
            createProduct("ELOAD", "DITO", "DITO", "DITO50", "DITO 50", 50.0, "DITO prepaid load"),
            createProduct("ELOAD", "DITO", "DITO", "DITO100", "DITO 100", 100.0, "DITO prepaid load"),
            createProduct("ELOAD", "DITO", "DITO", "DITO300", "DITO 300", 300.0, "DITO prepaid load"),
            createProduct("ELOAD", "DITO", "DITO", "DITO500", "DITO 500", 500.0, "DITO prepaid load"),
            createProduct("ELOAD", "SUN", "Sun Cellular", "SUN5", "Sun 5", 5.0, "Sun prepaid load"),
            createProduct("ELOAD", "SUN", "Sun Cellular", "SUN10", "Sun 10", 10.0, "Sun prepaid load"),
            createProduct("ELOAD", "SUN", "Sun Cellular", "SUN20", "Sun 20", 20.0, "Sun prepaid load"),
            createProduct("ELOAD", "SUN", "Sun Cellular", "SUN50", "Sun 50", 50.0, "Sun prepaid load"),
            createProduct("ELOAD", "SUN", "Sun Cellular", "SUN100", "Sun 100", 100.0, "Sun prepaid load"),
            createProduct("ELOAD", "SUN", "Sun Cellular", "SUN300", "Sun 300", 300.0, "Sun prepaid load"),
            createProduct("ELOAD", "TM", "TM", "TM5", "TM 5", 5.0, "TM prepaid load"),
            createProduct("ELOAD", "TM", "TM", "TM10", "TM 10", 10.0, "TM prepaid load"),
            createProduct("ELOAD", "TM", "TM", "TM20", "TM 20", 20.0, "TM prepaid load"),
            createProduct("ELOAD", "TM", "TM", "TM50", "TM 50", 50.0, "TM prepaid load"),
            createProduct("ELOAD", "TM", "TM", "TM100", "TM 100", 100.0, "TM prepaid load"),
            createProduct("ELOAD", "TM", "TM", "TM300", "TM 300", 300.0, "TM prepaid load"),
            createProduct("ELOAD", "TM", "TM", "TM500", "TM 500", 500.0, "TM prepaid load"),
        )

        val billsProviders = listOf(
            createProduct("BILLS", "MERALCO", "Meralco", "MERALCO_PAY", "Meralco Payment", 0.0, "Electric bill payment", category = "Electricity"),
            createProduct("BILLS", "VECO", "VECO", "VECO_PAY", "VECO Payment", 0.0, "Visayan Electric", category = "Electricity"),
            createProduct("BILLS", "DAVLIGHT", "Davao Light", "DAVLIGHT_PAY", "Davao Light Payment", 0.0, "Davao Light", category = "Electricity"),
            createProduct("BILLS", "COTABATO_LIGHT", "Cotabato Light", "COTLIGHT_PAY", "Cotabato Light Payment", 0.0, "Cotabato Light", category = "Electricity"),
            createProduct("BILLS", "MAYNILAD", "Maynilad", "MAYNILAD_PAY", "Maynilad Payment", 0.0, "Water bill payment", category = "Water"),
            createProduct("BILLS", "MANILA_WATER", "Manila Water", "MWATER_PAY", "Manila Water Payment", 0.0, "Manila Water", category = "Water"),
            createProduct("BILLS", "PLDT", "PLDT", "PLDT_PAY", "PLDT Payment", 0.0, "PLDT Home/Telpad", category = "Internet/Cable"),
            createProduct("BILLS", "GLOBE_BILL", "Globe Telecom", "GLOBE_BILL_PAY", "Globe Bill Payment", 0.0, "Globe Postpaid/Broadband", category = "Internet/Cable"),
            createProduct("BILLS", "CONVERGE", "Converge ICT", "CONVERGE_PAY", "Converge Payment", 0.0, "Converge Fiber", category = "Internet/Cable"),
            createProduct("BILLS", "SKY", "Sky Cable", "SKY_PAY", "Sky Cable Payment", 0.0, "Sky Cable/Broadband", category = "Internet/Cable"),
            createProduct("BILLS", "CIGNAL", "Cignal TV", "CIGNAL_PAY", "Cignal Payment", 0.0, "Cignal TV", category = "Internet/Cable"),
            createProduct("BILLS", "SMART_BILL", "Smart", "SMART_BILL_PAY", "Smart Postpaid", 0.0, "Smart Postpaid bill", category = "Telecommunications"),
            createProduct("BILLS", "SUN_BILL", "Sun Postpaid", "SUN_BILL_PAY", "Sun Postpaid", 0.0, "Sun Postpaid bill", category = "Telecommunications"),
            createProduct("BILLS", "SSS", "SSS", "SSS_PAY", "SSS Contribution", 0.0, "SSS monthly contribution", category = "Government"),
            createProduct("BILLS", "PAGIBIG", "Pag-IBIG", "PAGIBIG_PAY", "Pag-IBIG Payment", 0.0, "Pag-IBIG Fund", category = "Government"),
            createProduct("BILLS", "PHILHEALTH", "PhilHealth", "PHILHEALTH_PAY", "PhilHealth Payment", 0.0, "PhilHealth contribution", category = "Government"),
            createProduct("BILLS", "NBI", "NBI Clearance", "NBI_PAY", "NBI Clearance Payment", 0.0, "NBI Clearance", category = "Government"),
            createProduct("BILLS", "BIR", "BIR", "BIR_PAY", "BIR Tax Payment", 0.0, "BIR Tax", category = "Government"),
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

        val ecashProviders = listOf(
            createProduct("ECASH", "GCASH", "GCash", "GCASH_CASHIN", "GCash Cash-In", 0.0, "GCash wallet top-up"),
            createProduct("ECASH", "MAYA", "Maya", "MAYA_CASHIN", "Maya Cash-In", 0.0, "Maya (PayMaya) wallet top-up"),
            createProduct("ECASH", "SHOPEEPAY", "ShopeePay", "SHOPEE_CASHIN", "ShopeePay Cash-In", 0.0, "ShopeePay wallet top-up"),
            createProduct("ECASH", "GRABPAY", "GrabPay", "GRAB_CASHIN", "GrabPay Cash-In", 0.0, "GrabPay wallet top-up"),
            createProduct("ECASH", "COINS", "Coins.ph", "COINS_CASHIN", "Coins.ph Cash-In", 0.0, "Coins.ph wallet top-up"),
            createProduct("ECASH", "PAYPAL", "PayPal", "PAYPAL_CASHIN", "PayPal Cash-In", 0.0, "PayPal wallet top-up"),
            createProduct("ECASH", "LAZADA", "Lazada Wallet", "LAZADA_CASHIN", "Lazada Wallet Cash-In", 0.0, "Lazada Wallet top-up"),
        )

        val rfidProviders = listOf(
            createProduct("RFID", "EASYTRIP", "EasyTrip", "EASYTRIP_RELOAD", "EasyTrip Reload", 0.0, "EasyTrip RFID reload", category = "RFID Services"),
            createProduct("RFID", "AUTOSWEEP", "Autosweep", "AUTOSWEEP_RELOAD", "Autosweep Reload", 0.0, "Autosweep RFID reload", category = "RFID Services"),
            createProduct("RFID", "TAPNGO", "Tap&Go", "TAPNGO_RELOAD", "Tap&Go Reload", 0.0, "Tap&Go RFID reload", category = "RFID Services"),
            createProduct("RFID", "CONNECT", "Connect RFID", "CONNECT_RELOAD", "Connect Reload", 0.0, "Connect RFID reload", category = "RFID Services"),
            createProduct("RFID", "ETC", "ETC RFID", "ETC_RELOAD", "ETC Reload", 0.0, "ETC RFID reload", category = "RFID Services"),
            createProduct("RFID", "OTHER", "Other Toll RFID", "OTHER_RELOAD", "Other RFID Reload", 0.0, "Other toll RFID reload", category = "RFID Services"),
        )

        productDao.insertAll(eloadProviders + billsProviders + ecashProviders + rfidProviders)
    }

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
