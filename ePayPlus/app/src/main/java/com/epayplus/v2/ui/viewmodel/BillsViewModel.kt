package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.local.entity.ProductEntity
import com.epayplus.v2.data.repository.ProductRepository
import com.epayplus.v2.data.repository.TransactionRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class BillsViewModel @Inject constructor(
    private val productRepository: ProductRepository,
    private val transactionRepository: TransactionRepository
) : ViewModel() {

    val categories: StateFlow<List<String>> =
        productRepository.getCategoriesByType("BILLS")
            .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

    private val _isLoading = MutableStateFlow(true)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    private val _processState = MutableStateFlow<ProcessState>(ProcessState.Idle)
    val processState: StateFlow<ProcessState> = _processState.asStateFlow()

    init {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                productRepository.refreshProducts("BILLS")
            } catch (_: Exception) {}
            productRepository.ensureProductsExist()
            _isLoading.value = false
        }
    }

    fun getBillersByCategory(category: String): Flow<List<ProductEntity>> =
        productRepository.getProductsByCategory(category)

    fun processBillPayment(billerCode: String, billerName: String, productCode: String, accountNumber: String, amount: Double) {
        viewModelScope.launch {
            _processState.value = ProcessState.Processing

            transactionRepository.processBillPayment(
                providerCode = billerCode,
                productCode = productCode,
                accountNumber = accountNumber,
                amount = amount,
                providerName = billerName
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
