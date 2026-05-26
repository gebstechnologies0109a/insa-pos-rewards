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

    suspend fun refreshProducts(token: String, type: String): Result<Int> {
        return try {
            val response = when (type) {
                "ELOAD" -> apiService.getEloadProducts("Bearer $token")
                "BILLS" -> apiService.getBillsProducts("Bearer $token")
                "ECASH" -> apiService.getEcashProducts("Bearer $token")
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
                productDao.deleteByType(type)
                productDao.insertAll(products)
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
                productDao.getProductCountByType("ECASH")
        if (total == 0) {
            insertDefaultProducts()
        }
    }

    suspend fun insertDefaultProducts() {
        val eloadProviders = listOf(
            createProduct("ELOAD", "GLOBE", "Globe", "G50", "Globe 50", 50.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "G100", "Globe 100", 100.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "G300", "Globe 300", 300.0, "Globe prepaid load"),
            createProduct("ELOAD", "GLOBE", "Globe", "G500", "Globe 500", 500.0, "Globe prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "S50", "Smart 50", 50.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "S100", "Smart 100", 100.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "S300", "Smart 300", 300.0, "Smart prepaid load"),
            createProduct("ELOAD", "SMART", "Smart", "S500", "Smart 500", 500.0, "Smart prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT50", "TNT 50", 50.0, "TNT prepaid load"),
            createProduct("ELOAD", "TNT", "Talk N Text", "TNT100", "TNT 100", 100.0, "TNT prepaid load"),
            createProduct("ELOAD", "DITO", "DITO", "D50", "DITO 50", 50.0, "DITO prepaid load"),
            createProduct("ELOAD", "DITO", "DITO", "D100", "DITO 100", 100.0, "DITO prepaid load"),
            createProduct("ELOAD", "TM", "TM", "TM50", "TM 50", 50.0, "TM prepaid load"),
            createProduct("ELOAD", "TM", "TM", "TM100", "TM 100", 100.0, "TM prepaid load"),
        )

        val billsProviders = listOf(
            createProduct("BILLS", "MERALCO", "Meralco", "MERALCO_PAY", "Meralco Payment", 0.0, "Electric bill", category = "Electricity"),
            createProduct("BILLS", "MAYNILAD", "Maynilad", "MAYNILAD_PAY", "Maynilad Payment", 0.0, "Water bill", category = "Water"),
            createProduct("BILLS", "PLDT", "PLDT", "PLDT_PAY", "PLDT Payment", 0.0, "Telephone/Internet", category = "Telecom"),
            createProduct("BILLS", "GLOBE_BILL", "Globe Telecom", "GLOBE_BILL_PAY", "Globe Bill Payment", 0.0, "Telecom bill", category = "Telecom"),
            createProduct("BILLS", "SKY", "Sky Cable", "SKY_PAY", "Sky Cable Payment", 0.0, "Cable TV", category = "Cable"),
            createProduct("BILLS", "SSS", "SSS", "SSS_PAY", "SSS Contribution", 0.0, "Government", category = "Government"),
            createProduct("BILLS", "PAGIBIG", "Pag-IBIG", "PAGIBIG_PAY", "Pag-IBIG Payment", 0.0, "Government", category = "Government"),
            createProduct("BILLS", "PHILHEALTH", "PhilHealth", "PHILHEALTH_PAY", "PhilHealth Payment", 0.0, "Government", category = "Government"),
        )

        val ecashProviders = listOf(
            createProduct("ECASH", "GCASH", "GCash", "GCASH_CASHIN", "GCash Cash-In", 0.0, "GCash wallet top-up"),
            createProduct("ECASH", "MAYA", "Maya", "MAYA_CASHIN", "Maya Cash-In", 0.0, "Maya wallet top-up"),
            createProduct("ECASH", "COINS", "Coins.ph", "COINS_CASHIN", "Coins.ph Cash-In", 0.0, "Coins.ph wallet top-up"),
            createProduct("ECASH", "GRABPAY", "GrabPay", "GRAB_CASHIN", "GrabPay Cash-In", 0.0, "GrabPay wallet top-up"),
            createProduct("ECASH", "SHOPEEPAY", "ShopeePay", "SHOPEE_CASHIN", "ShopeePay Cash-In", 0.0, "ShopeePay wallet top-up"),
        )

        productDao.insertAll(eloadProviders + billsProviders + ecashProviders)
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
