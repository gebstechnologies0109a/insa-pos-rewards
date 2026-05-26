package com.epayplus.v2.data.local

import android.content.Context
import androidx.datastore.core.DataStore
import androidx.datastore.preferences.core.Preferences
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
    }

    val tokenFlow: Flow<String?> = context.dataStore.data.map { it[TOKEN_KEY] }

    val isLoggedIn: Flow<Boolean> = tokenFlow.map { !it.isNullOrEmpty() }

    fun getTokenSync(): String? = runBlocking {
        context.dataStore.data.first()[TOKEN_KEY]
    }

    suspend fun saveSession(token: String, accountId: String, businessName: String, ownerName: String) {
        context.dataStore.edit { prefs ->
            prefs[TOKEN_KEY] = token
            prefs[ACCOUNT_ID_KEY] = accountId
            prefs[BUSINESS_NAME_KEY] = businessName
            prefs[OWNER_NAME_KEY] = ownerName
        }
    }

    suspend fun clearSession() {
        context.dataStore.edit { it.clear() }
    }

    suspend fun getAccountId(): String? = context.dataStore.data.first()[ACCOUNT_ID_KEY]
    suspend fun getBusinessName(): String? = context.dataStore.data.first()[BUSINESS_NAME_KEY]
    suspend fun getOwnerName(): String? = context.dataStore.data.first()[OWNER_NAME_KEY]
}
