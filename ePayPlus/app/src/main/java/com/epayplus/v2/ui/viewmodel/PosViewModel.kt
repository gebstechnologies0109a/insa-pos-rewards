package com.epayplus.v2.ui.viewmodel

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.repository.RetailProductRepository
import com.epayplus.v2.domain.model.PosSaleLineRequest
import com.epayplus.v2.domain.model.PosSaleRequest
import com.epayplus.v2.domain.model.PosServiceItem
import com.epayplus.v2.domain.model.RetailProductDto
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class PosCartItem(
    val product: RetailProductDto,
    val quantity: Int
) {
    val lineTotal: Double get() = product.price * quantity
}

data class PosUiState(
    val isLoading: Boolean = true,
    val services: List<PosServiceItem> = emptyList(),
    val retailProducts: List<RetailProductDto> = emptyList(),
    val cart: List<PosCartItem> = emptyList(),
    val error: String? = null,
    val checkoutLoading: Boolean = false,
    val checkoutSuccess: String? = null
) {
    val cartTotal: Double get() = cart.sumOf { it.lineTotal }
    val cartCount: Int get() = cart.sumOf { it.quantity }
}

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
                            services = catalog.services.ifEmpty { defaultServices() },
                            retailProducts = catalog.retailProducts
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

    fun addToCart(product: RetailProductDto) {
        _uiState.update { state ->
            val existing = state.cart.find { it.product.id == product.id }
            val newCart = if (existing != null) {
                val newQty = existing.quantity + 1
                if (newQty > product.stock) state.cart
                else state.cart.map {
                    if (it.product.id == product.id) it.copy(quantity = newQty) else it
                }
            } else {
                if (product.stock < 1) state.cart
                else state.cart + PosCartItem(product, 1)
            }
            state.copy(cart = newCart, checkoutSuccess = null)
        }
    }

    fun removeFromCart(productId: Long) {
        _uiState.update { state ->
            state.copy(cart = state.cart.filter { it.product.id != productId }, checkoutSuccess = null)
        }
    }

    fun updateQuantity(productId: Long, quantity: Int) {
        _uiState.update { state ->
            if (quantity <= 0) {
                return@update state.copy(cart = state.cart.filter { it.product.id != productId })
            }
            state.copy(
                cart = state.cart.map {
                    if (it.product.id == productId) {
                        val max = it.product.stock
                        it.copy(quantity = quantity.coerceAtMost(max))
                    } else it
                },
                checkoutSuccess = null
            )
        }
    }

    fun clearCart() {
        _uiState.update { it.copy(cart = emptyList(), checkoutSuccess = null) }
    }

    fun checkout(onSuccess: () -> Unit = {}) {
        val cart = _uiState.value.cart
        if (cart.isEmpty()) return

        viewModelScope.launch {
            _uiState.update { it.copy(checkoutLoading = true, error = null) }
            val lines = cart.map { item ->
                PosSaleLineRequest(
                    productType = "retail",
                    productId = item.product.id,
                    productName = item.product.name,
                    sku = item.product.sku,
                    quantity = item.quantity,
                    unitPrice = item.product.price
                )
            }
            retailProductRepository.checkout(PosSaleRequest(lines = lines))
                .onSuccess { response ->
                    _uiState.update {
                        it.copy(
                            checkoutLoading = false,
                            cart = emptyList(),
                            checkoutSuccess = response.sale?.reference ?: "Sale complete"
                        )
                    }
                    loadCatalog()
                    onSuccess()
                }
                .onFailure { e ->
                    _uiState.update {
                        it.copy(checkoutLoading = false, error = e.message)
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
