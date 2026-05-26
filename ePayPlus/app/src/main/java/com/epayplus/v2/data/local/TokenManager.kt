package com.epayplus.v2.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
import androidx.datastore.preferences.core.booleanPreferencesKey
import androidx.datastore.preferences.core.edit
import androidx.datastore.preferences.core.stringPreferencesKey
import androidx.datastore.preferences.preferencesDataStore
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.runBlocking
import javax.inject.Inject
import javax.inject.Singleton

private val Context.dataStore: DataStore<Preferences> by preferencesDataStore(name = "epayplus_prefs")

@Singleton
class TokenManager @Inject constructor(private val context: Context) {

    companion object {
        private val TOKEN_KEY = stringPreferencesKey("auth_token")
        private val ACCOUNT_ID_KEY = stringPreferencesKey("account_id")
        private val BUSINESS_NAME_KEY = stringPreferencesKey("business_name")
        private val OWNER_NAME_KEY = stringPreferencesKey("owner_name")
        private val DEVICE_ID_KEY = stringPreferencesKey("device_id")
        private val DEVICE_MODE_KEY = stringPreferencesKey("device_mode")
        private val BASE_URL_KEY = stringPreferencesKey("base_url")
        private val SETUP_COMPLETE_KEY = booleanPreferencesKey("setup_complete")
    }

    val tokenFlow: Flow<String?> = context.dataStore.data.map { it[TOKEN_KEY] }

    val isLoggedIn: Flow<Boolean> = tokenFlow.map { !it.isNullOrEmpty() }

    val isSetupComplete: Flow<Boolean> = context.dataStore.data.map {
        it[SETUP_COMPLETE_KEY] == true
    }

    val deviceMode: Flow<String> = context.dataStore.data.map {
        it[DEVICE_MODE_KEY] ?: "retailer"
    }

    fun getTokenSync(): String? = runBlocking {
        context.dataStore.data.first()[TOKEN_KEY]
    }

    fun getDeviceModeSync(): String = runBlocking {
        context.dataStore.data.first()[DEVICE_MODE_KEY] ?: "retailer"
    }

    suspend fun saveSession(token: String, accountId: String, businessName: String, ownerName: String) {
        context.dataStore.edit { prefs ->
            prefs[TOKEN_KEY] = token
            prefs[ACCOUNT_ID_KEY] = accountId
            prefs[BUSINESS_NAME_KEY] = businessName
            prefs[OWNER_NAME_KEY] = ownerName
        }
    }

    suspend fun saveToken(token: String) {
        context.dataStore.edit { it[TOKEN_KEY] = token }
    }

    suspend fun saveDeviceId(deviceId: String) {
        context.dataStore.edit { it[DEVICE_ID_KEY] = deviceId }
    }

    suspend fun saveAccountId(accountId: String) {
        context.dataStore.edit { it[ACCOUNT_ID_KEY] = accountId }
    }

    suspend fun saveDeviceMode(mode: String) {
        context.dataStore.edit { it[DEVICE_MODE_KEY] = mode }
    }

    suspend fun saveBaseUrl(url: String) {
        context.dataStore.edit { it[BASE_URL_KEY] = url }
    }

    suspend fun saveSetupComplete(complete: Boolean) {
        context.dataStore.edit { it[SETUP_COMPLETE_KEY] = complete }
    }

    suspend fun clearSession() {
        context.dataStore.edit { it.clear() }
    }

    suspend fun getAccountId(): String? = context.dataStore.data.first()[ACCOUNT_ID_KEY]
    suspend fun getBusinessName(): String? = context.dataStore.data.first()[BUSINESS_NAME_KEY]
    suspend fun getOwnerName(): String? = context.dataStore.data.first()[OWNER_NAME_KEY]
    suspend fun getDeviceId(): String? = context.dataStore.data.first()[DEVICE_ID_KEY]
    suspend fun getBaseUrl(): String? = context.dataStore.data.first()[BASE_URL_KEY]
}
