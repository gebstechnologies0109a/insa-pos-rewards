package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.local.entity.TransactionEntity
import com.epayplus.v2.data.repository.TransactionRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import javax.inject.Inject

@HiltViewModel
class TransactionViewModel @Inject constructor(
    private val transactionRepository: TransactionRepository
) : ViewModel() {

    private val _filterType = MutableStateFlow("ALL")
    private val _searchQuery = MutableStateFlow("")

    val transactions: StateFlow<List<TransactionEntity>> = combine(
        _filterType, _searchQuery
    ) { type, query -> Pair(type, query) }
        .flatMapLatest { (type, query) ->
            if (query.isNotEmpty()) {
                transactionRepository.searchTransactions(query)
            } else if (type == "ALL") {
                transactionRepository.getAllTransactions()
            } else {
                transactionRepository.getTransactionsByType(type)
            }
        }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

    fun filterByType(type: String) {
        _filterType.value = type
    }

    fun search(query: String) {
        _searchQuery.value = query
    }

    suspend fun getTransactionById(id: Long): TransactionEntity? =
        transactionRepository.getTransactionById(id)
}
