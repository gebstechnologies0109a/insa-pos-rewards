package com.epayplus.v2.data.local.dao

import androidx.room.*
import com.epayplus.v2.data.local.entity.ProductEntity
import com.epayplus.v2.data.local.entity.SmsTemplateEntity
import kotlinx.coroutines.flow.Flow

@Dao
interface ProductDao {

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertAll(products: List<ProductEntity>)

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(product: ProductEntity): Long

    @Update
    suspend fun update(product: ProductEntity)

    @Delete
    suspend fun delete(product: ProductEntity)

    @Query("SELECT * FROM products WHERE type = :type AND isActive = 1 ORDER BY sortOrder, providerName")
    fun getProductsByType(type: String): Flow<List<ProductEntity>>

    @Query("""
        SELECT * FROM products
        WHERE providerCode = :providerCode AND isActive = 1 AND amount > 0
        ORDER BY
            CASE WHEN category = 'Promo' THEN 1 ELSE 0 END,
            sortOrder,
            amount
    """)
    fun getProductsByProvider(providerCode: String): Flow<List<ProductEntity>>

    @Query("SELECT DISTINCT providerCode, providerName FROM products WHERE type = :type AND isActive = 1 ORDER BY providerName")
    fun getProvidersByType(type: String): Flow<List<ProviderInfo>>

    @Query("SELECT * FROM products WHERE type = 'BILLS' AND category = :category AND isActive = 1 ORDER BY providerName")
    fun getProductsByCategory(category: String): Flow<List<ProductEntity>>

    @Query("SELECT DISTINCT category FROM products WHERE type = :type AND isActive = 1 AND category != '' ORDER BY category")
    fun getCategoriesByType(type: String): Flow<List<String>>

    @Query("SELECT COUNT(*) FROM products WHERE type = :type AND isActive = 1")
    suspend fun getProductCountByType(type: String): Int

    @Query("DELETE FROM products WHERE type = :type")
    suspend fun deleteByType(type: String)

    @Query("SELECT * FROM products WHERE id = :id")
    suspend fun getProductById(id: Long): ProductEntity?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insertTemplate(template: SmsTemplateEntity): Long

    @Query("SELECT * FROM sms_templates WHERE isActive = 1")
    fun getAllTemplates(): Flow<List<SmsTemplateEntity>>

    @Query("SELECT * FROM sms_templates WHERE provider = :provider AND isActive = 1")
    fun getTemplatesByProvider(provider: String): Flow<List<SmsTemplateEntity>>
}

data class ProviderInfo(
    val providerCode: String,
    val providerName: String
)
