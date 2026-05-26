package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.local.dao.ProviderInfo
import com.epayplus.v2.data.repository.ProductRepository
import com.epayplus.v2.data.repository.TransactionRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class ECashViewModel @Inject constructor(
    private val productRepository: ProductRepository,
    private val transactionRepository: TransactionRepository
) : ViewModel() {

    val providers: StateFlow<List<ProviderInfo>> =
        productRepository.getProvidersByType("ECASH")
            .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

    private val _isLoading = MutableStateFlow(true)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    private val _processState = MutableStateFlow<ProcessState>(ProcessState.Idle)
    val processState: StateFlow<ProcessState> = _processState.asStateFlow()

    init {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                productRepository.refreshProducts("ECASH")
            } catch (_: Exception) {}
            productRepository.ensureProductsExist()
            _isLoading.value = false
        }
    }

    fun processCashIn(providerCode: String, providerName: String, accountNumber: String, amount: Double) {
        viewModelScope.launch {
            _processState.value = ProcessState.Processing

            transactionRepository.processEcash(
                providerCode = providerCode,
                accountNumber = accountNumber,
                amount = amount,
                providerName = providerName
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
