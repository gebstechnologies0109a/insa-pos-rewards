package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.local.dao.ProviderInfo
import com.epayplus.v2.data.local.entity.ProductEntity
import com.epayplus.v2.data.repository.ProductRepository
import com.epayplus.v2.data.repository.TransactionRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class ProcessState {
    object Idle : ProcessState()
    object Processing : ProcessState()
    data class Success(val referenceNumber: String, val transactionId: Long) : ProcessState()
    data class Failed(val message: String) : ProcessState()
}

@HiltViewModel
class ELoadViewModel @Inject constructor(
    private val productRepository: ProductRepository,
    private val transactionRepository: TransactionRepository
) : ViewModel() {

    val providers: StateFlow<List<ProviderInfo>> =
        productRepository.getProvidersByType("ELOAD")
            .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

    private val _isLoading = MutableStateFlow(true)
    val isLoading: StateFlow<Boolean> = _isLoading.asStateFlow()

    private val _error = MutableStateFlow<String?>(null)
    val error: StateFlow<String?> = _error.asStateFlow()

    private val _processState = MutableStateFlow<ProcessState>(ProcessState.Idle)
    val processState: StateFlow<ProcessState> = _processState.asStateFlow()

    init {
        viewModelScope.launch {
            _isLoading.value = true
            try {
                productRepository.refreshProducts("ELOAD")
            } catch (_: Exception) {}
            productRepository.ensureProductsExist()
            _isLoading.value = false
        }
    }

    fun getProductsByProvider(providerCode: String): Flow<List<ProductEntity>> =
        productRepository.getProductsByProvider(providerCode)

    fun getRegularProductsByProvider(providerCode: String): Flow<List<ProductEntity>> =
        productRepository.getRegularProductsByProvider(providerCode)

    fun getPromoProductsByProvider(providerCode: String): Flow<List<ProductEntity>> =
        productRepository.getPromoProductsByProvider(providerCode)

    fun processEload(providerCode: String, productId: Long, phoneNumber: String) {
        viewModelScope.launch {
            _processState.value = ProcessState.Processing

            val product = productRepository.getProductById(productId)
            if (product == null) {
                _processState.value = ProcessState.Failed("Product not found")
                return@launch
            }

            transactionRepository.processEload(
                providerCode = providerCode,
                productCode = product.productCode,
                mobileNumber = phoneNumber,
                amount = product.amount,
                providerName = product.providerName,
                productName = product.productName
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

    fun retry() {
        _processState.value = ProcessState.Idle
    }

    fun reloadProviders() {
        viewModelScope.launch {
            _isLoading.value = true
            _error.value = null
            try {
                productRepository.refreshProducts("ELOAD")
            } catch (e: Exception) {
                _error.value = e.message
            }
            productRepository.ensureProductsExist()
            _isLoading.value = false
        }
    }
}
