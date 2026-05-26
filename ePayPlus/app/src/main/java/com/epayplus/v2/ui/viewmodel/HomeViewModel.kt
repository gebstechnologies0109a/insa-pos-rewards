package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.repository.AccountRepository
import com.epayplus.v2.data.repository.ProductRepository
import com.epayplus.v2.data.repository.TransactionRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

data class HomeUiState(
    val balance: Double = 0.0,
    val businessName: String = "",
    val ownerName: String = "",
    val todaySales: Double = 0.0,
    val todayTransactions: Int = 0,
    val isLoading: Boolean = false,
    val error: String? = null
)

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val accountRepository: AccountRepository,
    private val transactionRepository: TransactionRepository,
    private val productRepository: ProductRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(HomeUiState())
    val uiState: StateFlow<HomeUiState> = _uiState.asStateFlow()

    init {
        loadAccountInfo()
        observeTodaySales()
        syncProducts()
    }

    private fun syncProducts() {
        viewModelScope.launch {
            val account = accountRepository.getAccountSync()
            val token = account?.apiKey
            if (!token.isNullOrEmpty()) {
                listOf("ELOAD", "BILLS", "ECASH").forEach { type ->
                    productRepository.refreshProducts(token, type)
                }
            }
            productRepository.ensureProductsExist()
        }
    }

    private fun loadAccountInfo() {
        viewModelScope.launch {
            accountRepository.getAccount().collect { account ->
                account?.let {
                    _uiState.update { state ->
                        state.copy(
                            balance = account.balance,
                            businessName = account.businessName,
                            ownerName = account.ownerName
                        )
                    }
                }
            }
        }
    }

    private fun observeTodaySales() {
        viewModelScope.launch {
            transactionRepository.getTodaySales().collect { sales ->
                _uiState.update { it.copy(todaySales = sales) }
            }
        }
        viewModelScope.launch {
            transactionRepository.getTodayTransactionCount().collect { count ->
                _uiState.update { it.copy(todayTransactions = count) }
            }
        }
    }

    fun refreshBalance() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true) }
            accountRepository.refreshBalance()
                .onSuccess { balance ->
                    _uiState.update { it.copy(balance = balance, isLoading = false, error = null) }
                }
                .onFailure { error ->
                    _uiState.update { it.copy(isLoading = false, error = error.message) }
                }
        }
    }
}
