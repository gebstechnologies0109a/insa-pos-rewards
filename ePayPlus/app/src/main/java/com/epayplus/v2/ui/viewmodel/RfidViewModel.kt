package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.repository.RfidRepository
import com.epayplus.v2.data.repository.TransactionRepository
import com.epayplus.v2.domain.model.RfidProvider
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class RfidViewModel @Inject constructor(
    private val rfidRepository: RfidRepository,
    private val transactionRepository: TransactionRepository
) : ViewModel() {

    val providers: StateFlow<List<RfidProvider>> =
        rfidRepository.observeProviders()
            .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

    private val _isLoading = MutableStateFlow(true)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    private val _processState = MutableStateFlow<ProcessState>(ProcessState.Idle)
    val processState: StateFlow<ProcessState> = _processState.asStateFlow()

    init {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                rfidRepository.refreshProviders()
            } catch (_: Exception) {
            }
            rfidRepository.ensureProvidersExist()
            _isLoading.value = false
        }
    }

    fun providerForCode(code: String): RfidProvider? =
        providers.value.find { it.code.equals(code, ignoreCase = true) }
            ?: RfidRepository.DEFAULT_PROVIDERS.find { it.code.equals(code, ignoreCase = true) }

    fun processReload(
        providerCode: String,
        providerName: String,
        accountNumber: String,
        amount: Double,
        tagId: String? = null
    ) {
        viewModelScope.launch {
            _processState.value = ProcessState.Processing
            transactionRepository.processRfid(
                providerCode = providerCode,
                accountNumber = accountNumber,
                amount = amount,
                providerName = providerName,
                tagId = tagId
            ).onSuccess { transaction ->
                _processState.value = ProcessState.Success(
                    referenceNumber = transaction.referenceNumber,
                    transactionId = transaction.id
                )
            }.onFailure { error ->
                _processState.value = ProcessState.Failed(
                    error.message ?: "Transaction failed"
                )
            }
        }
    }

    fun resetProcessState() {
        _processState.value = ProcessState.Idle
    }
}
