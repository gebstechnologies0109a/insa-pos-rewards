package com.epayplus.v2.ui.viewmodel

import android.content.Context
import android.os.Build
import android.provider.Settings
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.local.TokenManager
import com.epayplus.v2.data.remote.EPayApiService
import com.epayplus.v2.domain.model.DeviceRegisterRequest
import com.epayplus.v2.util.MachineIdHelper
import com.epayplus.v2.util.PhoneNumberUtils
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

    private val _serverUrl = MutableStateFlow("https://epayplus.diybizrewards.com/api/v2")
    val serverUrl: StateFlow<String> = _serverUrl.asStateFlow()

    private val _licenseCode = MutableStateFlow("")
    val licenseCode: StateFlow<String> = _licenseCode.asStateFlow()

    private val _machineUid = MutableStateFlow(MachineIdHelper.getMachineUid(context))
    val machineUid: StateFlow<String> = _machineUid.asStateFlow()

    private val _mobileNumber = MutableStateFlow("")
    val mobileNumber: StateFlow<String> = _mobileNumber.asStateFlow()

    private val _pin = MutableStateFlow("")
    val pin: StateFlow<String> = _pin.asStateFlow()

    private val _deviceMode = MutableStateFlow("retailer")
    val deviceMode: StateFlow<String> = _deviceMode.asStateFlow()

    fun updateServerUrl(url: String) { _serverUrl.value = url; _errorMessage.value = null }
    fun updateLicenseCode(code: String) { _licenseCode.value = code.uppercase(); _errorMessage.value = null }
    fun updateMachineUid(uid: String) { _machineUid.value = uid.uppercase(); _errorMessage.value = null }
    fun updateMobileNumber(value: String) {
        _mobileNumber.value = PhoneNumberUtils.sanitizeInput(value)
        _errorMessage.value = null
    }
    fun updatePin(p: String) { _pin.value = p; _errorMessage.value = null }
    fun updateDeviceMode(mode: String) { _deviceMode.value = mode }

    fun testConnection() {
        viewModelScope.launch {
            _isLoading.value = true
            _errorMessage.value = null
            try {
                tokenManager.saveBaseUrl(_serverUrl.value.trimEnd('/'))
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

    fun activateLicense() {
        viewModelScope.launch {
            _isLoading.value = true
            _errorMessage.value = null
            try {
                val deviceId = MachineIdHelper.getDeviceId(context)
                val machineUid = _machineUid.value.ifBlank { MachineIdHelper.getMachineUid(context) }

                val response = apiService.registerDevice(
                    DeviceRegisterRequest(
                        deviceId = deviceId,
                        machineUid = machineUid,
                        licenseCode = _licenseCode.value.trim().ifBlank { null },
                        type = _deviceMode.value,
                        appVersion = "3.1",
                        osVersion = "Android ${Build.VERSION.RELEASE}",
                        model = "${Build.MANUFACTURER} ${Build.MODEL}"
                    )
                )

                if (response.isSuccessful && response.body()?.success == true) {
                    tokenManager.saveDeviceId(deviceId)
                    tokenManager.saveMachineUid(response.body()?.device?.machineUid ?: machineUid)
                    MachineIdHelper.saveMachineUid(context, machineUid)
                    _licenseCode.value.takeIf { it.isNotBlank() }?.let { tokenManager.saveLicenseCode(it) }
                    _currentStep.value = 2
                } else {
                    _errorMessage.value = response.body()?.message ?: "License activation failed (${response.code()})"
                }
            } catch (e: Exception) {
                _errorMessage.value = "Error: ${e.localizedMessage}"
            }
            _isLoading.value = false
        }
    }

    fun activateAccount() {
        viewModelScope.launch {
            _isLoading.value = true
            _errorMessage.value = null
            try {
                val normalized = PhoneNumberUtils.normalizeForApi(_mobileNumber.value)
                if (normalized == null) {
                    _errorMessage.value = "Enter a valid Philippine mobile number (e.g. 09171234567)"
                    _isLoading.value = false
                    return@launch
                }
                val deviceId = tokenManager.getDeviceId() ?: MachineIdHelper.getDeviceId(context)
                val response = apiService.login(
                    com.epayplus.v2.domain.model.LoginRequest(
                        mobileNumber = normalized,
                        pin = _pin.value,
                        deviceId = deviceId
                    )
                )
                if (response.isSuccessful && response.body()?.success == true) {
                    val body = response.body()!!
                    tokenManager.saveSession(
                        body.token ?: "",
                        body.account?.id ?: "",
                        body.account?.businessName ?: "",
                        body.account?.ownerName ?: ""
                    )
                    _currentStep.value = 3
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

                val deviceId = tokenManager.getDeviceId() ?: MachineIdHelper.getDeviceId(context)
                val machineUid = tokenManager.getMachineUid() ?: MachineIdHelper.getMachineUid(context)

                apiService.registerDevice(
                    DeviceRegisterRequest(
                        deviceId = deviceId,
                        machineUid = machineUid,
                        licenseCode = tokenManager.getLicenseCode(),
                        type = _deviceMode.value,
                        appVersion = "3.1",
                        osVersion = "Android ${Build.VERSION.RELEASE}",
                        model = "${Build.MANUFACTURER} ${Build.MODEL}"
                    )
                )

                com.epayplus.v2.service.DeviceHeartbeatService.schedule(context)
                com.epayplus.v2.service.DeviceCommandService.schedule(context)
            } catch (_: Exception) { }
            _isLoading.value = false
        }
    }
}
