package com.epayplus.v2.ui.viewmodel

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.epayplus.v2.data.remote.EPayApiService
import com.epayplus.v2.domain.model.MayaCheckoutSessionRequest
import com.epayplus.v2.domain.model.MayaIntegrationData
import com.epayplus.v2.util.MayaNegosyoLauncher
import dagger.hilt.android.lifecycle.HiltViewModel
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import javax.inject.Inject

data class MayaNegosyoUiState(
    val isLoading: Boolean = true,
    val integration: MayaIntegrationData? = null,
    val eloadBalance: Double = 0.0,
    val billsBalance: Double = 0.0,
    val combinedBalance: Double = 0.0,
    val checkoutMessage: String? = null,
    val checkoutUrl: String? = null,
    val error: String? = null
)

@HiltViewModel
class MayaNegosyoViewModel @Inject constructor(
    application: Application,
    private val apiService: EPayApiService
) : AndroidViewModel(application) {

    private val _uiState = MutableStateFlow(MayaNegosyoUiState())
    val uiState: StateFlow<MayaNegosyoUiState> = _uiState.asStateFlow()

    init {
        refresh()
    }

    fun refresh() {
        viewModelScope.launch {
            _uiState.update { it.copy(isLoading = true, error = null) }
            try {
                val integrationResp = apiService.getMayaIntegration()
                val integration = if (integrationResp.isSuccessful) {
                    integrationResp.body()?.data
                } else {
                    null
                }

                var eload = 0.0
                var bills = 0.0
                var combined = 0.0
                val walletResponse = apiService.getWallets()
                if (walletResponse.isSuccessful && walletResponse.body()?.success == true) {
                    val wallets = walletResponse.body()?.wallets
                    eload = wallets?.eload?.balance ?: 0.0
                    bills = wallets?.bills?.balance ?: 0.0
                    combined = wallets?.total ?: (eload + bills)
                }

                _uiState.update {
                    it.copy(
                        isLoading = false,
                        integration = integration ?: defaultIntegration(),
                        eloadBalance = eload,
                        billsBalance = bills,
                        combinedBalance = combined
                    )
                }
            } catch (e: Exception) {
                _uiState.update {
                    it.copy(
                        isLoading = false,
                        integration = defaultIntegration(),
                        error = e.message
                    )
                }
            }
        }
    }

    fun createCheckout(amount: Double, description: String) {
        viewModelScope.launch {
            _uiState.update { it.copy(checkoutMessage = null, checkoutUrl = null) }
            try {
                val response = apiService.createMayaCheckout(
                    MayaCheckoutSessionRequest(amount = amount, description = description)
                )
                if (response.isSuccessful) {
                    val body = response.body()
                    _uiState.update {
                        it.copy(
                            checkoutMessage = body?.message,
                            checkoutUrl = body?.redirectUrl
                        )
                    }
                    body?.redirectUrl?.let { url ->
                        MayaNegosyoLauncher.openCheckoutUrl(getApplication(), url)
                    }
                } else {
                    _uiState.update {
                        it.copy(checkoutMessage = "Checkout failed (${response.code()})")
                    }
                }
            } catch (e: Exception) {
                _uiState.update { it.copy(checkoutMessage = e.message ?: "Checkout error") }
            }
        }
    }

    fun negosyoInstalled(): Boolean =
        MayaNegosyoLauncher.isPackageInstalled(getApplication(), MayaNegosyoLauncher.NEGOSYO_PACKAGE)

    fun businessInstalled(): Boolean =
        MayaNegosyoLauncher.isPackageInstalled(getApplication(), MayaNegosyoLauncher.BUSINESS_PACKAGE)

    private fun defaultIntegration() = MayaIntegrationData(
        billerEnabled = false,
        checkoutEnabled = false,
        checkoutDemoMode = true,
        negosyoPackage = MayaNegosyoLauncher.NEGOSYO_PACKAGE,
        businessPackage = MayaNegosyoLauncher.BUSINESS_PACKAGE,
        deepLinkUri = MayaNegosyoLauncher.NEGOSYO_DEEP_LINK,
        featureFlags = emptyMap()
    )
}
