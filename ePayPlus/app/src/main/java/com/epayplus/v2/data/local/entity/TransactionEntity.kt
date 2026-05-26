package com.epayplus.v2.data.local.entity

import androidx.room.Entity
import androidx.room.PrimaryKey

@Entity(tableName = "transactions")
data class TransactionEntity(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val type: String, // ELOAD, BILLS, ECASH, WIFI
    val provider: String,
    val product: String,
    val amount: Double,
    val fee: Double = 0.0,
    val targetNumber: String,
    val referenceNumber: String,
    val status: String, // PENDING, SUCCESS, FAILED
    val remarks: String = "",
    val paymentMethod: String = "WALLET", // WALLET, CASH, COINS
    val createdAt: Long = System.currentTimeMillis(),
    val completedAt: Long? = null,
    val syncedToServer: Boolean = false
)

@Entity(tableName = "products")
data class ProductEntity(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val type: String, // ELOAD, BILLS, ECASH
    val providerCode: String,
    val providerName: String,
    val productCode: String,
    val productName: String,
    val amount: Double,
    val fee: Double = 0.0,
    val description: String = "",
    val keyword: String = "",
    val isActive: Boolean = true,
    val sortOrder: Int = 0,
    val category: String = "",
    val updatedAt: Long = System.currentTimeMillis()
)

@Entity(tableName = "accounts")
data class AccountEntity(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val accountId: String,
    val businessName: String,
    val ownerName: String,
    val mobileNumber: String,
    val email: String = "",
    val address: String = "",
    val balance: Double = 0.0,
    val pin: String = "",
    val isKioskMode: Boolean = false,
    val kioskPin: String = "",
    val apiKey: String = "",
    val serverUrl: String = "",
    val simSlot: Int = 0,
    val printerAddress: String = "",
    val printerType: String = "BLUETOOTH", // BLUETOOTH, USB, SERIAL
    val createdAt: Long = System.currentTimeMillis(),
    val lastLoginAt: Long = System.currentTimeMillis()
)

@Entity(tableName = "sales_summary")
data class SalesSummaryEntity(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val date: String, // yyyy-MM-dd
    val totalTransactions: Int = 0,
    val totalSales: Double = 0.0,
    val totalFees: Double = 0.0,
    val totalProfit: Double = 0.0,
    val eloadCount: Int = 0,
    val eloadSales: Double = 0.0,
    val billsCount: Int = 0,
    val billsSales: Double = 0.0,
    val ecashCount: Int = 0,
    val ecashSales: Double = 0.0,
    val wifiCount: Int = 0,
    val wifiSales: Double = 0.0
)

@Entity(tableName = "sms_templates")
data class SmsTemplateEntity(
    @PrimaryKey(autoGenerate = true)
    val id: Long = 0,
    val provider: String,
    val keyword: String,
    val format: String,
    val targetNumber: String,
    val isActive: Boolean = true
)
