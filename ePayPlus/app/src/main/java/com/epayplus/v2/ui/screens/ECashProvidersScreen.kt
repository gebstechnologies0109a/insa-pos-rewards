package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.ui.components.ProviderIcon
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.layout.providerGridColumns
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.ECashViewModel
import java.net.URLEncoder

@Composable
fun ECashProvidersScreen(
    navController: NavController,
    viewModel: ECashViewModel = hiltViewModel()
) {
    val providers by viewModel.providers.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()

    val providerColors = mapOf(
        "GCASH" to Color(0xFF007DFE),
        "MAYA" to Color(0xFF00C851),
        "SHOPEEPAY" to Color(0xFFEE4D2D),
        "GRABPAY" to Color(0xFF00B14F),
        "COINS" to Color(0xFF2196F3),
        "PAYPAL" to Color(0xFF003087),
        "LAZADA" to Color(0xFF0F146D)
    )

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        Box(
            modifier = Modifier.fillMaxWidth()
                .background(brush = Brush.verticalGradient(listOf(CategoryEcash, CategoryEcash.copy(alpha = 0.8f))))
                .padding(20.dp)
        ) {
            Column {
                Text("Cash-In", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = Color.White)
                Text("Top up e-wallets instantly", fontSize = 13.sp, color = Color.White.copy(alpha = 0.8f))
            }
        }

        if (isLoading) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = CategoryEcash)
            }
        } else if (providers.isEmpty()) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Filled.AccountBalanceWallet, "No providers", modifier = Modifier.size(48.dp), tint = EPayMediumGray)
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("No e-wallet providers available", color = EPayMediumGray)
                }
            }
        } else {
            Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
                Text("Select E-Wallet", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold, color = EPayMediumGray)
                Spacer(modifier = Modifier.height(12.dp))

                LazyVerticalGrid(
                    columns = providerGridColumns(minSize = 180.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    modifier = Modifier.fillMaxSize()
                ) {
                    items(providers) { provider ->
                        val color = providerColors[provider.providerCode] ?: CategoryEcash
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .aspectRatio(1.1f)
                                .clickable {
                                    val encodedName = URLEncoder.encode(provider.providerName, "UTF-8")
                                    navController.navigate(NavRoutes.ECashProcess.createRoute(provider.providerCode, encodedName))
                                },
                            shape = RoundedCornerShape(16.dp),
                            elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                            colors = CardDefaults.cardColors(containerColor = Color.White)
                        ) {
                            Column(
                                modifier = Modifier.fillMaxSize().padding(12.dp),
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.Center
                            ) {
                                ProviderIcon(
                                    providerCode = provider.providerCode,
                                    providerName = provider.providerName,
                                    size = 48.dp,
                                    fallbackIcon = Icons.Filled.AccountBalanceWallet,
                                    fallbackTint = color,
                                    backgroundColor = color.copy(alpha = 0.08f),
                                    contentPadding = 4.dp
                                )
                                Spacer(modifier = Modifier.height(8.dp))
                                Text(
                                    provider.providerName,
                                    fontWeight = FontWeight.SemiBold,
                                    fontSize = 12.sp,
                                    textAlign = TextAlign.Center,
                                    maxLines = 1
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}
