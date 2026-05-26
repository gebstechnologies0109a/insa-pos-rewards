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

    val transactions: StateFlow<List<TransactionEntity>> = _filterType
        .flatMapLatest { type ->
            if (type == "ALL") {
                transactionRepository.getAllTransactions()
            } else {
                transactionRepository.getTransactionsByType(type)
            }
        }
        .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

    fun filterByType(type: String) {
        _filterType.value = type
    }
}
