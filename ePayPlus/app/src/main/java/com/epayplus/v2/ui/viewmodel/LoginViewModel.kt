package com.epayplus.v2.ui.viewmodel

import android.app.Application
import android.provider.Settings
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.repository.AccountRepository
import com.epayplus.v2.util.PhoneNumberUtils
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

data class LoginUiState(
    val mobileNumber: String = "",
    val pin: String = "",
    val isLoading: Boolean = false,
    val error: String? = null,
    val isSuccess: Boolean = false
)

@HiltViewModel
class LoginViewModel @Inject constructor(
    application: Application,
    private val accountRepository: AccountRepository
) : AndroidViewModel(application) {

    private val _uiState = MutableStateFlow(LoginUiState())
    val uiState: StateFlow<LoginUiState> = _uiState.asStateFlow()

    fun updateMobileNumber(value: String) {
        _uiState.update { it.copy(mobileNumber = PhoneNumberUtils.sanitizeInput(value), error = null) }
    }

    fun updatePin(value: String) {
        if (value.length <= 6) {
            _uiState.update { it.copy(pin = value, error = null) }
        }
    }

    fun login() {
        val state = _uiState.value
        if (state.mobileNumber.isBlank()) {
            _uiState.update { it.copy(error = "Please enter your mobile number") }
            return
        }
        val normalized = PhoneNumberUtils.normalizeForApi(state.mobileNumber)
        if (normalized == null) {
            _uiState.update {
                it.copy(error = "Enter a valid Philippine mobile number (e.g. 09171234567)")
            }
            return
        }
        if (state.pin.isBlank()) {
            _uiState.update { it.copy(error = "Please enter your PIN") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }

            val deviceId = Settings.Secure.getString(
                getApplication<Application>().contentResolver,
                Settings.Secure.ANDROID_ID
            )

            accountRepository.login(normalized, state.pin, deviceId)
                .onSuccess {
                    _uiState.update { it.copy(isLoading = false, isSuccess = true) }
                }
                .onFailure { error ->
                    _uiState.update {
                        it.copy(isLoading = false, error = error.message ?: "Login failed")
                    }
                }
        }
    }
}
