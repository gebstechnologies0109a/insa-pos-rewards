package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
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
import com.epayplus.v2.domain.model.RfidProvider
import com.epayplus.v2.ui.components.ProviderIconFromRes
import com.epayplus.v2.ui.layout.providerGridColumns
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.nfc.nfcAvailability
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.RfidViewModel
import java.net.URLEncoder

private val providerAccent = mapOf(
    "EASYTRIP" to Color(0xFF1565C0),
    "AUTOSWEEP" to Color(0xFFE65100),
    "TAPNGO" to Color(0xFF2E7D32),
    "CONNECT" to Color(0xFF6A1B9A),
    "ETC" to Color(0xFF455A64),
    "OTHER" to Color(0xFF00838F)
)

@Composable
fun RfidProvidersScreen(
    navController: NavController,
    viewModel: RfidViewModel = hiltViewModel()
) {
    val providers by viewModel.providers.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()
    val context = androidx.compose.ui.platform.LocalContext.current
    val nfc = remember { nfcAvailability(context) }

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.verticalGradient(
                        listOf(CategoryRfid, CategoryRfid.copy(alpha = 0.85f))
                    )
                )
                .padding(20.dp)
        ) {
            Column {
                Row(verticalAlignment = Alignment.CenterVertically) {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.Filled.ArrowBack, "Back", tint = Color.White)
                    }
                    Spacer(modifier = Modifier.width(4.dp))
                    Column {
                        Text("RFID Services", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = Color.White)
                        Text("Toll RFID reload providers", fontSize = 13.sp, color = Color.White.copy(alpha = 0.85f))
                    }
                }
                if (nfc.adapterPresent) {
                    Spacer(modifier = Modifier.height(10.dp))
                    Surface(
                        shape = RoundedCornerShape(10.dp),
                        color = Color.White.copy(alpha = 0.18f)
                    ) {
                        Row(
                            modifier = Modifier.padding(horizontal = 12.dp, vertical = 8.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            Icon(
                                Icons.Filled.Nfc,
                                contentDescription = null,
                                tint = Color.White,
                                modifier = Modifier.size(18.dp)
                            )
                            Spacer(modifier = Modifier.width(8.dp))
                            Text(
                                if (nfc.enabled) "NFC tap available on reload screen"
                                else "Enable NFC in settings to tap-read tags",
                                fontSize = 12.sp,
                                color = Color.White.copy(alpha = 0.95f)
                            )
                        }
                    }
                }
            }
        }

        if (isLoading) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = CategoryRfid)
            }
        } else if (providers.isEmpty()) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Filled.Nfc, "No providers", modifier = Modifier.size(48.dp), tint = EPayMediumGray)
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("No RFID providers available", color = EPayMediumGray)
                }
            }
        } else {
            Column(modifier = Modifier.fillMaxSize().padding(16.dp)) {
                Text(
                    "RFID Services",
                    style = MaterialTheme.typography.titleSmall,
                    fontWeight = FontWeight.SemiBold,
                    color = EPayMediumGray
                )
                Text(
                    "Select toll / expressway RFID provider",
                    fontSize = 12.sp,
                    color = EPayMediumGray.copy(alpha = 0.85f)
                )
                Spacer(modifier = Modifier.height(12.dp))

                LazyVerticalGrid(
                    columns = providerGridColumns(minSize = 180.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    modifier = Modifier.fillMaxSize()
                ) {
                    items(providers, key = { it.code }) { provider ->
                        RfidProviderCard(provider) {
                            val encodedName = URLEncoder.encode(provider.name, "UTF-8")
                            navController.navigate(
                                NavRoutes.RfidProcess.createRoute(provider.code, encodedName)
                            )
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun RfidProviderCard(provider: RfidProvider, onClick: () -> Unit) {
    val accent = providerAccent[provider.code.uppercase()] ?: CategoryRfid
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .aspectRatio(1.1f)
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Column(
            modifier = Modifier.fillMaxSize().padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            ProviderIconFromRes(
                resId = provider.iconRes,
                contentDescription = provider.name,
                size = 52.dp,
                backgroundColor = accent.copy(alpha = 0.1f),
                contentPadding = 6.dp
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                provider.name,
                fontWeight = FontWeight.SemiBold,
                fontSize = 12.sp,
                textAlign = TextAlign.Center,
                maxLines = 2
            )
        }
    }
}
