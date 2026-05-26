package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.local.entity.TransactionEntity
import com.epayplus.v2.data.repository.AccountRepository
import com.epayplus.v2.data.repository.ProductRepository
import com.epayplus.v2.data.repository.TransactionRepository
import com.epayplus.v2.domain.model.Announcement
import com.epayplus.v2.data.remote.EPayApiService
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
    val recentTransactions: List<TransactionEntity> = emptyList(),
    val announcements: List<Announcement> = emptyList(),
    val isLoading: Boolean = false,
    val isRefreshing: Boolean = false,
    val error: String? = null
)

@HiltViewModel
class HomeViewModel @Inject constructor(
    private val accountRepository: AccountRepository,
    private val transactionRepository: TransactionRepository,
    private val productRepository: ProductRepository,
    private val apiService: EPayApiService
) : ViewModel() {

    private val _uiState = MutableStateFlow(HomeUiState())
    val uiState: StateFlow<HomeUiState> = _uiState.asStateFlow()

    init {
        loadAccountInfo()
        observeTodaySales()
        observeRecentTransactions()
        refreshAll()
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

    private fun observeRecentTransactions() {
        viewModelScope.launch {
            transactionRepository.getAllTransactions().collect { transactions ->
                _uiState.update { it.copy(recentTransactions = transactions.take(5)) }
            }
        }
    }

    fun refreshAll() {
        viewModelScope.launch {
            _uiState.update { it.copy(isRefreshing = true) }

            accountRepository.refreshBalance()
                .onSuccess { balance ->
                    _uiState.update { it.copy(balance = balance) }
                }

            listOf("ELOAD", "BILLS", "ECASH").forEach { type ->
                try { productRepository.refreshProducts(type) } catch (_: Exception) {}
            }
            productRepository.ensureProductsExist()

            try {
                val response = apiService.getAnnouncements()
                if (response.isSuccessful && response.body()?.success == true) {
                    _uiState.update { it.copy(announcements = response.body()!!.announcements) }
                }
            } catch (_: Exception) {}

            _uiState.update { it.copy(isRefreshing = false, error = null) }
        }
    }

    fun refreshBalance() {
        viewModelScope.launch {
            _uiState.update { it.copy(isRefreshing = true) }
            accountRepository.refreshBalance()
                .onSuccess { balance ->
                    _uiState.update { it.copy(balance = balance, isRefreshing = false, error = null) }
                }
                .onFailure { error ->
                    _uiState.update { it.copy(isRefreshing = false, error = error.message) }
                }
        }
    }
}
