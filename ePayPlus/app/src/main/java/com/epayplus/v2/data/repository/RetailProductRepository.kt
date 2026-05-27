package com.epayplus.v2.data.repository

import com.epayplus.v2.data.remote.EPayApiService
import com.epayplus.v2.domain.model.PosCatalogResponse
import com.epayplus.v2.domain.model.PosSaleRequest
import com.epayplus.v2.domain.model.PosSaleResponse
import com.epayplus.v2.domain.model.RetailProductDto
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

    suspend fun checkout(request: PosSaleRequest): Result<PosSaleResponse> = runCatching {
        val response = apiService.recordPosSale(request)
        if (response.isSuccessful && response.body()?.success == true) {
            response.body()!!
        } else {
            throw Exception(response.body()?.message ?: "Checkout failed")
        }
    }
}
