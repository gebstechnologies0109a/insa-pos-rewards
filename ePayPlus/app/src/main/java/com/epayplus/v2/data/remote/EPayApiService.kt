package com.epayplus.v2.data.remote

import com.epayplus.v2.domain.model.*
import retrofit2.Response
import retrofit2.http.*

interface EPayApiService {

    @POST("auth/login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @GET("account/balance")
    suspend fun getBalance(): Response<BalanceResponse>

    @GET("products/eload")
    suspend fun getEloadProducts(): Response<ProductListResponse>

    @GET("products/bills")
    suspend fun getBillsProducts(): Response<ProductListResponse>

    @GET("products/ecash")
    suspend fun getEcashProducts(): Response<ProductListResponse>

    @GET("providers")
    suspend fun getProviders(): Response<ProvidersResponse>

    @POST("transactions/eload")
    suspend fun processEload(@Body request: EloadRequest): Response<TransactionResponse>

    @POST("transactions/bills")
    suspend fun processBillPayment(@Body request: BillPaymentRequest): Response<TransactionResponse>

    @POST("transactions/ecash")
    suspend fun processEcash(@Body request: EcashRequest): Response<TransactionResponse>

    @GET("transactions/history")
    suspend fun getTransactionHistory(
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 50
    ): Response<TransactionHistoryResponse>

    @POST("transactions/sync")
    suspend fun syncTransactions(
        @Body transactions: List<SyncTransactionRequest>
    ): Response<SyncResponse>

    @GET("announcements")
    suspend fun getAnnouncements(): Response<AnnouncementsResponse>

    @POST("account/change-pin")
    suspend fun changePin(@Body request: ChangePinRequest): Response<GenericResponse>

    // Device Management API v2
    @POST("v2/device/register")
    suspend fun registerDevice(@Body params: Map<String, String>): Response<GenericResponse>

    @POST("v2/device/heartbeat")
    suspend fun sendHeartbeat(@Body params: Map<String, String>): Response<GenericResponse>

    @GET("v2/device/config")
    suspend fun getDeviceConfig(@Query("device_id") deviceId: String): Response<DeviceConfigResponse>

    @GET("v2/device/commands")
    suspend fun getDeviceCommands(@Query("device_id") deviceId: String): Response<DeviceCommandsResponse>

    @POST("v2/device/command-ack")
    suspend fun acknowledgeCommand(@Body params: Map<String, String>): Response<GenericResponse>

    @POST("v2/device/log")
    suspend fun sendDeviceLogs(@Body params: Map<String, Any>): Response<GenericResponse>

    @POST("v2/device/sms-report")
    suspend fun reportSms(@Body params: Map<String, String>): Response<GenericResponse>

    @POST("v2/sync/transactions")
    suspend fun syncOfflineTransactions(@Body params: Map<String, Any>): Response<GenericResponse>

    @GET("v2/sync/providers")
    suspend fun getSyncProviders(): Response<GenericResponse>

    @GET("v2/sync/config")
    suspend fun getSystemConfig(): Response<GenericResponse>
}
