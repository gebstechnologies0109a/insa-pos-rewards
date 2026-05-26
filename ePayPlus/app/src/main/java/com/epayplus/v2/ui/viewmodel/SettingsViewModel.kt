package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.repository.AccountRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

data class SettingsUiState(
    val businessName: String = "",
    val ownerName: String = "",
    val accountId: String = ""
)

@HiltViewModel
class SettingsViewModel @Inject constructor(
    private val accountRepository: AccountRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(SettingsUiState())
    val uiState: StateFlow<SettingsUiState> = _uiState.asStateFlow()

    init {
        viewModelScope.launch {
            accountRepository.getAccount().collect { account ->
                account?.let {
                    _uiState.update { state ->
                        state.copy(
                            businessName = account.businessName,
                            ownerName = account.ownerName,
                            accountId = account.accountId
                        )
                    }
                }
            }
        }
    }

    fun logout(onComplete: () -> Unit) {
        viewModelScope.launch {
            accountRepository.logout()
            onComplete()
        }
    }
}
