package com.epayplus.v2.di

import android.content.Context
import androidx.room.Room
import com.epayplus.v2.data.local.EPayDatabase
import com.epayplus.v2.data.local.TokenManager
import com.epayplus.v2.data.local.dao.TransactionDao
import com.epayplus.v2.data.local.dao.ProductDao
import com.epayplus.v2.data.local.dao.AccountDao
import com.epayplus.v2.data.remote.AuthInterceptor
import com.epayplus.v2.data.remote.EPayApiService
import com.epayplus.v2.data.repository.TransactionRepository
import com.epayplus.v2.data.repository.ProductRepository
import com.epayplus.v2.data.repository.AccountRepository
import dagger.Module
import dagger.Provides
import dagger.hilt.InstallIn
import dagger.hilt.android.qualifiers.ApplicationContext
import dagger.hilt.components.SingletonComponent
import okhttp3.OkHttpClient
import okhttp3.logging.HttpLoggingInterceptor
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory
import java.util.concurrent.TimeUnit
import javax.inject.Singleton

@Module
@InstallIn(SingletonComponent::class)
object AppModule {

    @Provides
    @Singleton
    fun provideTokenManager(@ApplicationContext context: Context): TokenManager {
        return TokenManager(context)
    }

    @Provides
    @Singleton
    fun provideDatabase(@ApplicationContext context: Context): EPayDatabase {
        return Room.databaseBuilder(
            context,
            EPayDatabase::class.java,
            "epayplus_db"
        ).fallbackToDestructiveMigration().build()
    }

    @Provides
    @Singleton
    fun provideTransactionDao(database: EPayDatabase): TransactionDao = database.transactionDao()

    @Provides
    @Singleton
    fun provideProductDao(database: EPayDatabase): ProductDao = database.productDao()

    @Provides
    @Singleton
    fun provideAccountDao(database: EPayDatabase): AccountDao = database.accountDao()

    @Provides
    @Singleton
    fun provideAuthInterceptor(tokenManager: TokenManager): AuthInterceptor {
        return AuthInterceptor(tokenManager)
    }

    @Provides
    @Singleton
    fun provideOkHttpClient(authInterceptor: AuthInterceptor): OkHttpClient {
        val logging = HttpLoggingInterceptor().apply {
            level = HttpLoggingInterceptor.Level.BODY
        }
        return OkHttpClient.Builder()
            .addInterceptor(authInterceptor)
            .addInterceptor(logging)
            .connectTimeout(30, TimeUnit.SECONDS)
            .readTimeout(30, TimeUnit.SECONDS)
            .writeTimeout(30, TimeUnit.SECONDS)
            .build()
    }

    @Provides
    @Singleton
    fun provideRetrofit(client: OkHttpClient): Retrofit {
        return Retrofit.Builder()
            .baseUrl("https://epayplus.diybizrewards.com/api/v2/")
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
    }

    @Provides
    @Singleton
    fun provideApiService(retrofit: Retrofit): EPayApiService {
        return retrofit.create(EPayApiService::class.java)
    }

    @Provides
    @Singleton
    fun provideTransactionRepository(
        transactionDao: TransactionDao,
        apiService: EPayApiService
    ): TransactionRepository = TransactionRepository(transactionDao, apiService)

    @Provides
    @Singleton
    fun provideProductRepository(
        productDao: ProductDao,
        apiService: EPayApiService
    ): ProductRepository = ProductRepository(productDao, apiService)

    @Provides
    @Singleton
    fun provideAccountRepository(
        accountDao: AccountDao,
        apiService: EPayApiService,
        tokenManager: TokenManager
    ): AccountRepository = AccountRepository(accountDao, apiService, tokenManager)
}
