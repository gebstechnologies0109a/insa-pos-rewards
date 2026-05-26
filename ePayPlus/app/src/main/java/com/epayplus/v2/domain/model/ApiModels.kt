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

data class BalanceResponse(
    val success: Boolean,
    val balance: Double,
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
