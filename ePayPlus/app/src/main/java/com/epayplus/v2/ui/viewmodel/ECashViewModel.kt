package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.local.dao.ProviderInfo
import com.epayplus.v2.data.repository.AccountRepository
import com.epayplus.v2.data.repository.ProductRepository
import com.epayplus.v2.data.repository.TransactionRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class ECashViewModel @Inject constructor(
    private val productRepository: ProductRepository,
    private val transactionRepository: TransactionRepository,
    private val accountRepository: AccountRepository
) : ViewModel() {

    val providers: StateFlow<List<ProviderInfo>> =
        productRepository.getProvidersByType("ECASH")
            .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

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
                    val result = productRepository.refreshProducts(token, "ECASH")
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

    fun processCashIn(providerCode: String, mobileNumber: String, amount: Double) {
        viewModelScope.launch {
            transactionRepository.createTransaction(
                type = "ECASH",
                provider = providerCode,
                product = "Cash-In",
                amount = amount,
                fee = 0.0,
                targetNumber = mobileNumber
            )
        }
    }
}
