package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.repository.RetailProductRepository
import com.epayplus.v2.domain.model.PosServiceItem
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PosUiState(
    val isLoading: Boolean = true,
    val services: List<PosServiceItem> = emptyList(),
    val error: String? = null
)

@HiltViewModel
class PosViewModel @Inject constructor(
    private val retailProductRepository: RetailProductRepository
) : ViewModel() {

    private val _uiState = MutableStateFlow(PosUiState())
    val uiState: StateFlow<PosUiState> = _uiState.asStateFlow()

    init {
        loadCatalog()
    }

    fun loadCatalog() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            retailProductRepository.fetchCatalog()
                .onSuccess { catalog ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            services = catalog.services.ifEmpty { defaultServices() }
                        )
                    }
                }
                .onFailure { e ->
                    _uiState.update {
                        it.copy(
                            isLoading = false,
                            services = defaultServices(),
                            error = e.message
                        )
                    }
                }
        }
    }

    private fun defaultServices() = listOf(
        PosServiceItem("eload", "E-Load", "eload"),
        PosServiceItem("bills", "Bills Payment", "bills"),
        PosServiceItem("ecash", "Cash-in", "ecash"),
        PosServiceItem("rfid", "RFID", "rfid"),
    )
}
