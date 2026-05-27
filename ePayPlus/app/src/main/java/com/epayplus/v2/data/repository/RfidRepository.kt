package com.epayplus.v2.data.repository

import com.epayplus.v2.R
import com.epayplus.v2.data.local.dao.ProductDao
import com.epayplus.v2.data.local.dao.ProviderInfo
import com.epayplus.v2.data.local.entity.ProductEntity
import com.epayplus.v2.data.remote.EPayApiService
import com.epayplus.v2.domain.model.RfidProvider
import com.epayplus.v2.ui.components.RfidProviderIcons
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.map
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class RfidRepository @Inject constructor(
    private val productDao: ProductDao,
    private val apiService: EPayApiService
) {
    fun observeProviders(): Flow<List<RfidProvider>> =
        productDao.getProvidersByType("RFID").map { providers ->
            if (providers.isEmpty()) defaultProviders() else providers.map { it.toRfidProvider() }
        }

    suspend fun refreshProviders(): Result<Int> {
        return try {
            val response = apiService.getRfidProducts()
            if (response.isSuccessful && response.body()?.success == true) {
                val products = response.body()!!.products.mapIndexed { index, product ->
                    ProductEntity(
                        type = "RFID",
                        providerCode = product.providerCode,
                        providerName = product.providerName,
                        productCode = product.code,
                        productName = product.name,
                        amount = product.amount,
                        fee = product.fee,
                        description = product.description,
                        keyword = product.keyword,
                        category = product.category.ifEmpty { "RFID Services" },
                        sortOrder = index
                    )
                }
                if (products.isNotEmpty()) {
                    productDao.deleteByType("RFID")
                    productDao.insertAll(products)
                }
                Result.success(products.size)
            } else {
                Result.failure(Exception(response.body()?.message ?: "Failed to fetch RFID providers"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun ensureProvidersExist() {
        if (productDao.getProductCountByType("RFID") == 0) {
            productDao.insertAll(defaultProducts())
        }
    }

    fun defaultProviders(): List<RfidProvider> = DEFAULT_PROVIDERS

    private fun ProviderInfo.toRfidProvider() = RfidProvider(
        code = providerCode,
        name = providerName,
        iconRes = RfidProviderIcons.iconRes(providerCode),
        category = "RFID Services"
    )

    private fun defaultProducts(): List<ProductEntity> = DEFAULT_PROVIDERS.mapIndexed { index, provider ->
        ProductEntity(
            type = "RFID",
            providerCode = provider.code,
            providerName = provider.name,
            productCode = "${provider.code}_RELOAD",
            productName = "${provider.name} Reload",
            amount = 0.0,
            description = "${provider.name} RFID wallet reload",
            category = provider.category,
            sortOrder = index
        )
    }

    companion object {
        val DEFAULT_PROVIDERS = listOf(
            RfidProvider("EASYTRIP", "EasyTrip", R.drawable.ic_rfid_easytrip,
                accountLabel = "EasyTrip Account No.",
                accountHint = "12-digit EasyTrip account"),
            RfidProvider("AUTOSWEEP", "Autosweep", R.drawable.ic_rfid_autosweep,
                accountLabel = "Autosweep RFID No.",
                accountHint = "Autosweep account or plate"),
            RfidProvider("TAPNGO", "Tap&Go", R.drawable.ic_rfid_tapngo,
                accountLabel = "Tap&Go Account No.",
                accountHint = "TPLEX Tap&Go account"),
            RfidProvider("CONNECT", "Connect RFID", R.drawable.ic_rfid_connect,
                accountLabel = "Connect Account No.",
                accountHint = "MPT Connect RFID account"),
            RfidProvider("ETC", "ETC RFID", R.drawable.ic_rfid_etc,
                accountLabel = "ETC Account / Tag ID",
                accountHint = "Electronic toll collection account"),
            RfidProvider("OTHER", "Other Toll RFID", R.drawable.ic_rfid_other,
                accountLabel = "RFID Account / Tag ID",
                accountHint = "Account or RFID tag number")
        )
    }
}
