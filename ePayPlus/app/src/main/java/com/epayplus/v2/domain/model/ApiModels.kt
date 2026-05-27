package com.epayplus.v2.domain.model

import com.google.gson.annotations.SerializedName

data class LoginRequest(
    @SerializedName("account_id") val accountId: String,
    val pin: String,
    @SerializedName("device_id") val deviceId: String
)

data class LoginResponse(
    val success: Boolean,
    val token: String?,
    val account: AccountInfo?,
    val message: String?
)

data class AccountInfo(
    val id: String,
    @SerializedName("businessName") val businessName: String = "",
    @SerializedName("ownerName") val ownerName: String = "",
    @SerializedName("mobileNumber") val mobileNumber: String = "",
    val email: String = "",
    val balance: Double = 0.0,
    @SerializedName("isKioskEnabled") val isKioskEnabled: Boolean = false
)

data class DeviceRegisterRequest(
    @SerializedName("device_id") val deviceId: String,
    @SerializedName("machine_uid") val machineUid: String,
    @SerializedName("license_code") val licenseCode: String? = null,
    val type: String = "retailer",
    @SerializedName("app_version") val appVersion: String? = null,
    @SerializedName("os_version") val osVersion: String? = null,
    val model: String? = null
)

data class DeviceRegisterResponse(
    val success: Boolean,
    val device: DeviceInfo? = null,
    val message: String? = null
)

data class DeviceInfo(
    val id: Long = 0,
    @SerializedName("device_id") val deviceId: String = "",
    @SerializedName("machine_uid") val machineUid: String = "",
    val type: String = "",
    @SerializedName("license_code") val licenseCode: String? = null,
    val config: Map<String, Any>? = null
)

data class WalletsResponse(
    val success: Boolean,
    val wallets: WalletBalances? = null,
    val message: String? = null
)

data class WalletBalances(
    val eload: WalletInfo? = null,
    val bills: WalletInfo? = null,
    val total: Double = 0.0
)

data class WalletInfo(
    val label: String = "",
    val balance: Double = 0.0,
    val currency: String = "PHP"
)

data class HeartbeatResponse(
    val success: Boolean,
    @SerializedName("pending_commands") val pendingCommands: Int = 0,
    @SerializedName("server_time") val serverTime: String? = null,
    @SerializedName("config_version") val configVersion: Long = 0,
    val config: RemoteConfig? = null,
    @SerializedName("machine_uid") val machineUid: String? = null,
    val message: String? = null
)

data class RemoteConfig(
    val config: Map<String, Any>? = null,
    @SerializedName("enabled_services") val enabledServices: List<String>? = null,
    val services: Map<String, Boolean>? = null,
    @SerializedName("operating_hours") val operatingHours: String? = null,
    @SerializedName("is_locked") val isLocked: Boolean = false,
    @SerializedName("config_version") val configVersion: Long = 0,
    @SerializedName("machine_uid") val machineUid: String? = null
)

data class BalanceResponse(
    val success: Boolean,
    val balance: Double,
    @SerializedName("eload_balance") val eloadBalance: Double? = null,
    @SerializedName("bills_balance") val billsBalance: Double? = null,
    val message: String?
)

data class ProductListResponse(
    val success: Boolean,
    val products: List<ProductInfo>,
    val message: String?
)

data class ProductInfo(
    val code: String = "",
    val name: String = "",
    @SerializedName("providerCode") val providerCode: String = "",
    @SerializedName("providerName") val providerName: String = "",
    val amount: Double = 0.0,
    val fee: Double = 0.0,
    val description: String = "",
    val keyword: String = "",
    val category: String = ""
)

data class ProvidersResponse(
    val success: Boolean,
    val providers: List<ProviderDetail>,
    val message: String?
)

data class ProviderDetail(
    val code: String = "",
    val name: String = "",
    val type: String = "",
    @SerializedName("logoUrl") val logoUrl: String = "",
    val logo: String = "",
    val category: String = ""
)

data class EloadRequest(
    @SerializedName("provider_code") val providerCode: String,
    @SerializedName("product_code") val productCode: String,
    @SerializedName("mobile_number") val mobileNumber: String,
    val amount: Double,
    @SerializedName("reference_id") val referenceId: String = ""
)

data class BillPaymentRequest(
    @SerializedName("provider_code") val providerCode: String,
    @SerializedName("product_code") val productCode: String,
    @SerializedName("account_number") val accountNumber: String,
    val amount: Double,
    @SerializedName("reference_id") val referenceId: String = ""
)

data class EcashRequest(
    @SerializedName("provider_code") val providerCode: String,
    @SerializedName("product_code") val productCode: String = "",
    @SerializedName("account_number") val accountNumber: String,
    val amount: Double,
    @SerializedName("reference_id") val referenceId: String = ""
)

data class RfidRequest(
    @SerializedName("provider_code") val providerCode: String,
    @SerializedName("product_code") val productCode: String = "",
    @SerializedName("account_number") val accountNumber: String,
    val amount: Double,
    @SerializedName("reference_id") val referenceId: String = "",
    @SerializedName("tag_id") val tagId: String? = null
)

data class TransactionResponse(
    val success: Boolean = false,
    @SerializedName("referenceNumber") val referenceNumber: String? = null,
    val status: String? = null,
    val message: String? = null,
    val balance: Double? = null
)

data class TransactionHistoryResponse(
    val success: Boolean,
    val transactions: List<TransactionInfo>,
    val totalPages: Int = 1,
    val message: String?
)

data class TransactionInfo(
    val id: String = "",
    val type: String = "",
    val provider: String = "",
    val product: String = "",
    val amount: Double = 0.0,
    val fee: Double = 0.0,
    @SerializedName("targetNumber") val targetNumber: String = "",
    @SerializedName("referenceNumber") val referenceNumber: String = "",
    val status: String = "",
    @SerializedName("createdAt") val createdAt: String = ""
)

data class SyncTransactionRequest(
    val localId: Long,
    val type: String,
    val referenceNumber: String,
    val amount: Double,
    val status: String,
    val createdAt: Long
)

data class SyncResponse(
    val success: Boolean,
    val syncedCount: Int,
    val message: String?
)

data class AnnouncementsResponse(
    val success: Boolean,
    val announcements: List<Announcement>,
    val message: String?
)

data class Announcement(
    val id: String = "",
    val title: String = "",
    val content: String = "",
    val type: String = "",
    val createdAt: String = ""
)

data class ChangePinRequest(
    @SerializedName("current_pin") val currentPin: String,
    @SerializedName("new_pin") val newPin: String
)

data class GenericResponse(
    val success: Boolean,
    val message: String?
)

data class MayaIntegrationResponse(
    val success: Boolean,
    val data: MayaIntegrationData? = null
)

data class MayaIntegrationData(
    @SerializedName("biller_enabled") val billerEnabled: Boolean = false,
    @SerializedName("checkout_enabled") val checkoutEnabled: Boolean = false,
    @SerializedName("checkout_demo_mode") val checkoutDemoMode: Boolean = true,
    @SerializedName("negosyo_package") val negosyoPackage: String = "com.paymaya.negosyo",
    @SerializedName("business_package") val businessPackage: String = "ph.maya.business.android",
    @SerializedName("deep_link_uri") val deepLinkUri: String = "negosyo://",
    @SerializedName("feature_flags") val featureFlags: Map<String, Boolean> = emptyMap()
)

data class MayaCheckoutSessionRequest(
    val amount: Double,
    val description: String? = null
)

data class MayaCheckoutSessionResponse(
    val success: Boolean = false,
    val demo: Boolean = true,
    @SerializedName("checkout_id") val checkoutId: String? = null,
    @SerializedName("redirect_url") val redirectUrl: String? = null,
    val reference: String? = null,
    val message: String? = null
)

data class DeviceConfigResponse(
    val success: Boolean,
    val config: Map<String, Any>? = null,
    @SerializedName("enabled_services") val enabledServices: List<String>? = null,
    @SerializedName("operating_hours") val operatingHours: String? = null,
    val type: String? = null,
    val message: String? = null
)

data class DeviceCommandsResponse(
    val success: Boolean,
    val commands: List<DeviceCommandInfo>? = null,
    val message: String? = null
)

data class DeviceCommandInfo(
    val id: Long = 0,
    val command: String = "",
    val params: Map<String, Any>? = null,
    @SerializedName("created_at") val createdAt: String = ""
)
