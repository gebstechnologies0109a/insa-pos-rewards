package com.epayplus.v2.data.remote

import com.epayplus.v2.domain.model.*
import retrofit2.Response
import retrofit2.http.*

interface EPayApiService {

    @POST("auth/login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @GET("account/balance")
    suspend fun getBalance(@Header("Authorization") token: String): Response<BalanceResponse>

    @GET("products/eload")
    suspend fun getEloadProducts(@Header("Authorization") token: String): Response<ProductListResponse>

    @GET("products/bills")
    suspend fun getBillsProducts(@Header("Authorization") token: String): Response<ProductListResponse>

    @GET("products/ecash")
    suspend fun getEcashProducts(@Header("Authorization") token: String): Response<ProductListResponse>

    @POST("transactions/eload")
    suspend fun processEload(
        @Header("Authorization") token: String,
        @Body request: EloadRequest
    ): Response<TransactionResponse>

    @POST("transactions/bills")
    suspend fun processBillPayment(
        @Header("Authorization") token: String,
        @Body request: BillPaymentRequest
    ): Response<TransactionResponse>

    @POST("transactions/ecash")
    suspend fun processEcash(
        @Header("Authorization") token: String,
        @Body request: EcashRequest
    ): Response<TransactionResponse>

    @GET("transactions/history")
    suspend fun getTransactionHistory(
        @Header("Authorization") token: String,
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 50
    ): Response<TransactionHistoryResponse>

    @POST("transactions/sync")
    suspend fun syncTransactions(
        @Header("Authorization") token: String,
        @Body transactions: List<SyncTransactionRequest>
    ): Response<SyncResponse>

    @GET("announcements")
    suspend fun getAnnouncements(
        @Header("Authorization") token: String
    ): Response<AnnouncementsResponse>

    @POST("account/change-pin")
    suspend fun changePin(
        @Header("Authorization") token: String,
        @Body request: ChangePinRequest
    ): Response<GenericResponse>
}
