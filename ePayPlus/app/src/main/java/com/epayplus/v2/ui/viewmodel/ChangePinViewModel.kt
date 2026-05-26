package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.repository.AccountRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

data class ChangePinUiState(
    val currentPin: String = "",
    val newPin: String = "",
    val confirmPin: String = "",
    val isLoading: Boolean = false,
    val error: String? = null,
    val isSuccess: Boolean = false
)

@HiltViewModel
class ChangePinViewModel @Inject constructor(
    private val accountRepository: AccountRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(ChangePinUiState())
    val uiState: StateFlow<ChangePinUiState> = _uiState.asStateFlow()

    fun updateCurrentPin(value: String) {
        if (value.length <= 6) _uiState.update { it.copy(currentPin = value, error = null) }
    }

    fun updateNewPin(value: String) {
        if (value.length <= 6) _uiState.update { it.copy(newPin = value, error = null) }
    }

    fun updateConfirmPin(value: String) {
        if (value.length <= 6) _uiState.update { it.copy(confirmPin = value, error = null) }
    }

    fun changePin() {
        val state = _uiState.value
        if (state.currentPin.isBlank()) {
            _uiState.update { it.copy(error = "Please enter current PIN") }
            return
        }
        if (state.newPin.length < 4) {
            _uiState.update { it.copy(error = "New PIN must be at least 4 digits") }
            return
        }
        if (state.newPin != state.confirmPin) {
            _uiState.update { it.copy(error = "New PINs do not match") }
            return
        }

        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            accountRepository.changePin(state.currentPin, state.newPin)
                .onSuccess {
                    _uiState.update { it.copy(isLoading = false, isSuccess = true) }
                }
                .onFailure { error ->
                    _uiState.update { it.copy(isLoading = false, error = error.message) }
                }
        }
    }
}
