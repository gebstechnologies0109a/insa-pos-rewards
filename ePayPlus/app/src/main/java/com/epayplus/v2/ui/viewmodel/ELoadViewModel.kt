package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.local.dao.ProviderInfo
import com.epayplus.v2.data.local.entity.ProductEntity
import com.epayplus.v2.data.repository.AccountRepository
import com.epayplus.v2.data.repository.ProductRepository
import com.epayplus.v2.data.repository.TransactionRepository
import com.epayplus.v2.ui.screens.ProcessState
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class ELoadViewModel @Inject constructor(
    private val productRepository: ProductRepository,
    private val transactionRepository: TransactionRepository,
    private val accountRepository: AccountRepository
) : ViewModel() {

    val providers: StateFlow<List<ProviderInfo>> =
        productRepository.getProvidersByType("ELOAD")
            .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

    private val _processState = MutableStateFlow<ProcessState>(ProcessState.Idle)
    val processState: StateFlow<ProcessState> = _processState.asStateFlow()

    private val _isLoading = MutableStateFlow(true)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    init {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                val account = accountRepository.getAccountSync()
                val token = account?.apiKey
                if (!token.isNullOrEmpty()) {
                    val result = productRepository.refreshProducts(token, "ELOAD")
                    if (result.isFailure) {
                        _error.value = result.exceptionOrNull()?.message
                    }
                }
                productRepository.ensureProductsExist()
            } catch (e: Exception) {
                _error.value = e.message
                productRepository.ensureProductsExist()
            } finally {
                _isLoading.value = false
            }
        }
    }

    fun getProductsByProvider(providerCode: String): Flow<List<ProductEntity>> =
        productRepository.getProductsByProvider(providerCode)

    fun processEload(productId: Long, phoneNumber: String) {
        viewModelScope.launch {
            _processState.value = ProcessState.Processing

            delay(2000) // Simulate processing

            val transaction = transactionRepository.createTransaction(
                type = "ELOAD",
                provider = "PROVIDER",
                product = "Load",
                amount = 50.0,
                fee = 0.0,
                targetNumber = phoneNumber
            )

            // Simulate SMS-based loading or API call
            delay(1500)

            transactionRepository.updateTransactionStatus(transaction.id, "SUCCESS")
            _processState.value = ProcessState.Success(
                referenceNumber = transaction.referenceNumber,
                transactionId = transaction.id
            )
        }
    }
}
