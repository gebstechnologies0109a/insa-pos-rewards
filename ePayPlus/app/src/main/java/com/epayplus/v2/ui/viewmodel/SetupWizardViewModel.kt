package com.epayplus.v2.ui.viewmodel

import android.content.Context
import android.provider.Settings
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.local.TokenManager
import com.epayplus.v2.data.remote.EPayApiService
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class SetupWizardViewModel @Inject constructor(
    @ApplicationContext private val context: Context,
    private val apiService: EPayApiService,
    private val tokenManager: TokenManager
) : ViewModel() {

    private val _currentStep = MutableStateFlow(0)
    val currentStep: StateFlow<Int> = _currentStep.asStateFlow()

    private val _isLoading = MutableStateFlow(false)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    private val _errorMessage = MutableStateFlow<String?>(null)
    val errorMessage: StateFlow<String?> = _errorMessage.asStateFlow()

    private val _serverUrl = MutableStateFlow("")
    val serverUrl: StateFlow<String> = _serverUrl.asStateFlow()

    private val _accountId = MutableStateFlow("")
    val accountId: StateFlow<String> = _accountId.asStateFlow()

    private val _pin = MutableStateFlow("")
    val pin: StateFlow<String> = _pin.asStateFlow()

    private val _deviceMode = MutableStateFlow("retailer")
    val deviceMode: StateFlow<String> = _deviceMode.asStateFlow()

    fun updateServerUrl(url: String) { _serverUrl.value = url; _errorMessage.value = null }
    fun updateAccountId(id: String) { _accountId.value = id; _errorMessage.value = null }
    fun updatePin(p: String) { _pin.value = p; _errorMessage.value = null }
    fun updateDeviceMode(mode: String) { _deviceMode.value = mode }

    fun testConnection() {
        viewModelScope.launch {
            _isLoading.value = true
            _errorMessage.value = null
            try {
                tokenManager.saveBaseUrl(_serverUrl.value.trimEnd('/'))
                // Attempt a basic connection test
                val response = apiService.getSystemConfig()
                if (response.isSuccessful) {
                    _currentStep.value = 1
                } else {
                    _errorMessage.value = "Server responded with error: ${response.code()}"
                }
            } catch (e: Exception) {
                _errorMessage.value = "Connection failed: ${e.localizedMessage}"
            }
            _isLoading.value = false
        }
    }

    fun activateAccount() {
        viewModelScope.launch {
            _isLoading.value = true
            _errorMessage.value = null
            try {
                val deviceId = Settings.Secure.getString(
                    context.contentResolver, Settings.Secure.ANDROID_ID
                )
                val response = apiService.login(
                    com.epayplus.v2.domain.model.LoginRequest(
                        accountId = _accountId.value,
                        pin = _pin.value,
                        deviceId = deviceId
                    )
                )
                if (response.isSuccessful && response.body()?.success == true) {
                    tokenManager.saveToken(response.body()?.token ?: "")
                    tokenManager.saveDeviceId(deviceId)
                    tokenManager.saveAccountId(_accountId.value)
                    _currentStep.value = 2
                } else {
                    _errorMessage.value = response.body()?.message ?: "Activation failed"
                }
            } catch (e: Exception) {
                _errorMessage.value = "Error: ${e.localizedMessage}"
            }
            _isLoading.value = false
        }
    }

    fun completeSetup() {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                tokenManager.saveDeviceMode(_deviceMode.value)
                tokenManager.saveSetupComplete(true)

                // Register device with server
                val deviceId = tokenManager.getDeviceId() ?: ""
                apiService.registerDevice(
                    mapOf(
                        "device_id" to deviceId,
                        "type" to _deviceMode.value,
                        "app_version" to "3.0",
                        "os_version" to "Android ${android.os.Build.VERSION.RELEASE}",
                        "model" to "${android.os.Build.MANUFACTURER} ${android.os.Build.MODEL}"
                    )
                )
            } catch (_: Exception) { }
            _isLoading.value = false
        }
    }
}
