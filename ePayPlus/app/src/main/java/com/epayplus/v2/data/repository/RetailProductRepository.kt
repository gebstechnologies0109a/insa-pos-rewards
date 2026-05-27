package com.epayplus.v2.data.repository

import com.epayplus.v2.data.remote.EPayApiService
import com.epayplus.v2.domain.model.PosCatalogResponse
import javax.inject.Inject
import javax.inject.Singleton

@Singleton
class RetailProductRepository @Inject constructor(
    private val apiService: EPayApiService
) {
    suspend fun fetchCatalog(): Result<PosCatalogResponse> = runCatching {
        val response = apiService.getPosCatalog()
        if (response.isSuccessful && response.body()?.success == true) {
            response.body()!!
        } else {
            throw Exception(response.body()?.message ?: "Failed to load POS catalog")
        }
    }
}
