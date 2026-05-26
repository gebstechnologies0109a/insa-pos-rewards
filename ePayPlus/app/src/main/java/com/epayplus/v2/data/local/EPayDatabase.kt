package com.epayplus.v2.data.local

import androidx.room.Database
import androidx.room.RoomDatabase
import com.epayplus.v2.data.local.dao.AccountDao
import com.epayplus.v2.data.local.dao.ProductDao
import com.epayplus.v2.data.local.dao.TransactionDao
import com.epayplus.v2.data.local.entity.*

@Database(
    entities = [
        TransactionEntity::class,
        ProductEntity::class,
        AccountEntity::class,
        SalesSummaryEntity::class,
        SmsTemplateEntity::class
    ],
    version = 1,
    exportSchema = true
)
abstract class EPayDatabase : RoomDatabase() {
    abstract fun transactionDao(): TransactionDao
    abstract fun productDao(): ProductDao
    abstract fun accountDao(): AccountDao
}
