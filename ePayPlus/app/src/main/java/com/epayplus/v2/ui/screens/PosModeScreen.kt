package com.epayplus.v2.ui.screens

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.R
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.PosViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PosModeScreen(
    navController: NavController,
    viewModel: PosViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("POS Mode", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = EPayGreen,
                    titleContentColor = Color.White,
                    navigationIconContentColor = Color.White
                )
            )
        }
    ) { padding ->
        when {
            uiState.isLoading -> {
                Box(
                    modifier = Modifier
                        .fillMaxSize()
                        .padding(padding),
                    contentAlignment = Alignment.Center
                ) {
                    CircularProgressIndicator(color = EPayGreen)
                }
            }
            else -> PosServicesTab(
                navController = navController,
                services = uiState.services,
                modifier = Modifier.padding(padding)
            )
        }
    }
}

@Composable
private fun PosServicesTab(
    navController: NavController,
    services: List<com.epayplus.v2.domain.model.PosServiceItem>,
    modifier: Modifier = Modifier
) {
    val items = services.ifEmpty {
        listOf(
            com.epayplus.v2.domain.model.PosServiceItem("eload", "E-Load", "eload"),
            com.epayplus.v2.domain.model.PosServiceItem("bills", "Bills", "bills"),
            com.epayplus.v2.domain.model.PosServiceItem("ecash", "Cash-in", "ecash"),
            com.epayplus.v2.domain.model.PosServiceItem("rfid", "RFID", "rfid"),
        )
    }

    LazyVerticalGrid(
        columns = GridCells.Fixed(2),
        contentPadding = PaddingValues(16.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = modifier.fillMaxSize()
    ) {
        items(items) { service ->
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable {
                        when (service.route) {
                            "eload" -> navController.navigate(NavRoutes.ELoadProviders.route)
                            "bills" -> navController.navigate(NavRoutes.BillsCategories.route)
                            "ecash" -> navController.navigate(NavRoutes.ECashProviders.route)
                            "rfid" -> navController.navigate(NavRoutes.RfidProviders.route)
                        }
                    },
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = EPayGreenSurface)
            ) {
                Column(
                    modifier = Modifier.padding(20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    val iconRes = when (service.route) {
                        "eload" -> R.drawable.ic_quick_eload
                        "bills" -> R.drawable.ic_quick_bills
                        "ecash" -> R.drawable.ic_quick_cashin
                        "rfid" -> R.drawable.ic_quick_rfid
                        else -> R.drawable.ic_quick_pos
                    }
                    Icon(
                        painter = painterResource(iconRes),
                        contentDescription = service.label,
                        tint = EPayGreen,
                        modifier = Modifier.size(40.dp)
                    )
                    Spacer(Modifier.height(8.dp))
                    Text(service.label, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
                }
            }
        }
    }
}
