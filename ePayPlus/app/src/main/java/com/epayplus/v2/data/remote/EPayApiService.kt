package com.epayplus.v2.data.remote

import com.epayplus.v2.domain.model.*
import retrofit2.Response
import retrofit2.http.*

interface EPayApiService {

    @POST("auth/login")
    suspend fun login(@Body request: LoginRequest): Response<LoginResponse>

    @GET("account/balance")
    suspend fun getBalance(): Response<BalanceResponse>

    @GET("wallets")
    suspend fun getWallets(): Response<WalletsResponse>

    @GET("products/eload")
    suspend fun getEloadProducts(): Response<ProductListResponse>

    @GET("products/bills")
    suspend fun getBillsProducts(): Response<ProductListResponse>

    @GET("products/ecash")
    suspend fun getEcashProducts(): Response<ProductListResponse>

    @GET("products/rfid")
    suspend fun getRfidProducts(): Response<ProductListResponse>

    @GET("products/providers")
    suspend fun getProviders(): Response<ProvidersResponse>

    @POST("transactions/eload")
    suspend fun processEload(@Body request: EloadRequest): Response<TransactionResponse>

    @POST("transactions/bills")
    suspend fun processBillPayment(@Body request: BillPaymentRequest): Response<TransactionResponse>

    @POST("transactions/ecash")
    suspend fun processEcash(@Body request: EcashRequest): Response<TransactionResponse>

    @POST("transactions/rfid")
    suspend fun processRfid(@Body request: RfidRequest): Response<TransactionResponse>

    @GET("transactions/history")
    suspend fun getTransactionHistory(
        @Query("page") page: Int = 1,
        @Query("limit") limit: Int = 50
    ): Response<TransactionHistoryResponse>

    @POST("transactions/sync")
    suspend fun syncTransactions(
        @Body transactions: List<SyncTransactionRequest>
    ): Response<SyncResponse>

    @GET("products/announcements")
    suspend fun getAnnouncements(): Response<AnnouncementsResponse>

    @POST("auth/change-pin")
    suspend fun changePin(@Body request: ChangePinRequest): Response<GenericResponse>

    // Device Management API v2
    @POST("device/register")
    suspend fun registerDevice(@Body request: DeviceRegisterRequest): Response<DeviceRegisterResponse>

    @POST("device/heartbeat")
    suspend fun sendHeartbeat(@Body params: Map<String, String>): Response<HeartbeatResponse>

    @GET("device/config")
    suspend fun getDeviceConfig(@Query("device_id") deviceId: String): Response<DeviceConfigResponse>

    @GET("config")
    suspend fun getRemoteConfig(
        @Query("device_id") deviceId: String,
        @Query("machine_uid") machineUid: String? = null
    ): Response<DeviceConfigResponse>

    @GET("device/commands")
    suspend fun getDeviceCommands(@Query("device_id") deviceId: String): Response<DeviceCommandsResponse>

    @POST("device/command-ack")
    suspend fun acknowledgeCommand(@Body params: Map<String, String>): Response<GenericResponse>

    @POST("device/log")
    suspend fun sendDeviceLogs(@Body params: Map<String, Any>): Response<GenericResponse>

    @POST("device/sms-report")
    suspend fun reportSms(@Body params: Map<String, String>): Response<GenericResponse>

    @POST("sync/transactions")
    suspend fun syncOfflineTransactions(@Body params: Map<String, Any>): Response<GenericResponse>

    @GET("sync/providers")
    suspend fun getSyncProviders(): Response<GenericResponse>

    @GET("sync/config")
    suspend fun getSystemConfig(): Response<GenericResponse>

    @GET("integrations/maya")
    suspend fun getMayaIntegration(): Response<MayaIntegrationResponse>

    @POST("maya-checkout/sessions")
    suspend fun createMayaCheckout(@Body request: MayaCheckoutSessionRequest): Response<MayaCheckoutSessionResponse>

    @GET("pos/catalog")
    suspend fun getPosCatalog(): Response<PosCatalogResponse>

    @POST("pos/sales")
    suspend fun recordPosSale(@Body request: PosSaleRequest): Response<PosSaleResponse>

    @GET("retail-products")
    suspend fun getRetailProducts(): Response<RetailProductListResponse>
}
