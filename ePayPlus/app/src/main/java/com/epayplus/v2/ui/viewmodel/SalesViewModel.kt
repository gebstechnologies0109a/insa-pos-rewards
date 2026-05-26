package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.local.entity.SalesSummaryEntity
import com.epayplus.v2.data.repository.TransactionRepository
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.*
import javax.inject.Inject

@HiltViewModel
class SalesViewModel @Inject constructor(
    private val transactionRepository: TransactionRepository
) : ViewModel() {

    val salesSummaries: StateFlow<List<SalesSummaryEntity>> =
        transactionRepository.getRecentSalesSummaries()
            .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), emptyList())

    val todaySales: StateFlow<Double> =
        transactionRepository.getTodaySales()
            .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), 0.0)

    val todayCount: StateFlow<Int> =
        transactionRepository.getTodayTransactionCount()
            .stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), 0)
}
