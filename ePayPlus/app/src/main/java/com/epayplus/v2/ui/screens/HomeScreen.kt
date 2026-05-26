package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.HomeViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    navController: NavController,
    viewModel: HomeViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()

    Scaffold(
        topBar = {
            CenterAlignedTopAppBar(
                title = {
                    Text(
                        "ePayPlus",
                        fontWeight = FontWeight.Bold,
                        fontSize = 22.sp
                    )
                },
                colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                    containerColor = EPayGreen,
                    titleContentColor = Color.White
                ),
                actions = {
                    IconButton(onClick = { navController.navigate(NavRoutes.Settings.route) }) {
                        Icon(Icons.Filled.Settings, "Settings", tint = Color.White)
                    }
                }
            )
        }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .verticalScroll(rememberScrollState())
        ) {
            // Balance Card
            BalanceCard(
                balance = uiState.balance,
                businessName = uiState.businessName,
                todaySales = uiState.todaySales,
                todayTransactions = uiState.todayTransactions
            )

            Spacer(modifier = Modifier.height(16.dp))

            // Service Grid
            Text(
                "Services",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.padding(horizontal = 16.dp)
            )

            Spacer(modifier = Modifier.height(8.dp))

            ServiceGrid(navController)

            Spacer(modifier = Modifier.height(16.dp))

            // Quick Actions
            Text(
                "Quick Actions",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.padding(horizontal = 16.dp)
            )

            Spacer(modifier = Modifier.height(8.dp))

            QuickActionsRow(navController)

            Spacer(modifier = Modifier.height(24.dp))
        }
    }
}

@Composable
private fun BalanceCard(
    balance: Double,
    businessName: String,
    todaySales: Double,
    todayTransactions: Int
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(16.dp),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.linearGradient(
                        colors = listOf(EPayGreen, EPayGreenDark)
                    )
                )
                .padding(20.dp)
        ) {
            Column {
                Text(
                    businessName.ifEmpty { "ePayPlus V2.0" },
                    color = Color.White.copy(alpha = 0.8f),
                    fontSize = 14.sp
                )
                Spacer(modifier = Modifier.height(4.dp))
                Text(
                    "₱ ${String.format("%,.2f", balance)}",
                    color = Color.White,
                    fontSize = 32.sp,
                    fontWeight = FontWeight.Bold
                )
                Text(
                    "Available Balance",
                    color = Color.White.copy(alpha = 0.7f),
                    fontSize = 12.sp
                )
                Spacer(modifier = Modifier.height(16.dp))
                Divider(color = Color.White.copy(alpha = 0.3f))
                Spacer(modifier = Modifier.height(12.dp))
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.SpaceBetween
                ) {
                    Column {
                        Text("Today's Sales", color = Color.White.copy(alpha = 0.7f), fontSize = 11.sp)
                        Text(
                            "₱ ${String.format("%,.2f", todaySales)}",
                            color = Color.White,
                            fontWeight = FontWeight.SemiBold,
                            fontSize = 16.sp
                        )
                    }
                    Column(horizontalAlignment = Alignment.End) {
                        Text("Transactions", color = Color.White.copy(alpha = 0.7f), fontSize = 11.sp)
                        Text(
                            "$todayTransactions",
                            color = Color.White,
                            fontWeight = FontWeight.SemiBold,
                            fontSize = 16.sp
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun ServiceGrid(navController: NavController) {
    val services = listOf(
        ServiceItem("E-Load", Icons.Filled.PhoneAndroid, CategoryEload, NavRoutes.ELoadProviders.route),
        ServiceItem("Bills Pay", Icons.Filled.Receipt, CategoryBills, NavRoutes.BillsCategories.route),
        ServiceItem("E-Cash", Icons.Filled.AccountBalanceWallet, CategoryEcash, NavRoutes.ECashProviders.route),
        ServiceItem("WiFi", Icons.Filled.Wifi, CategoryWifi, NavRoutes.Settings.route),
        ServiceItem("Sales", Icons.Filled.BarChart, EPayBlue, NavRoutes.Sales.route),
        ServiceItem("History", Icons.Filled.History, EPayOrange, NavRoutes.TransactionHistory.route),
    )

    LazyVerticalGrid(
        columns = GridCells.Fixed(3),
        contentPadding = PaddingValues(horizontal = 16.dp),
        modifier = Modifier.height(220.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        items(services) { service ->
            ServiceCard(service) {
                navController.navigate(service.route)
            }
        }
    }
}

@Composable
private fun ServiceCard(service: ServiceItem, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .aspectRatio(1f)
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        elevation = CardDefaults.cardElevation(defaultElevation = 2.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Box(
                modifier = Modifier
                    .size(44.dp)
                    .clip(CircleShape)
                    .background(service.color.copy(alpha = 0.15f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(
                    service.icon,
                    contentDescription = service.label,
                    tint = service.color,
                    modifier = Modifier.size(24.dp)
                )
            }
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                service.label,
                style = MaterialTheme.typography.labelMedium,
                textAlign = TextAlign.Center,
                fontWeight = FontWeight.Medium
            )
        }
    }
}

@Composable
private fun QuickActionsRow(navController: NavController) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp)
    ) {
        QuickActionCard(
            modifier = Modifier.weight(1f),
            icon = Icons.Filled.QrCodeScanner,
            label = "Scan QR",
            color = EPayBlue
        ) { }

        QuickActionCard(
            modifier = Modifier.weight(1f),
            icon = Icons.Filled.Print,
            label = "Print Last",
            color = EPayOrange
        ) { }

        QuickActionCard(
            modifier = Modifier.weight(1f),
            icon = Icons.Filled.Refresh,
            label = "Refresh",
            color = EPayGreen
        ) { }
    }
}

@Composable
private fun QuickActionCard(
    modifier: Modifier = Modifier,
    icon: ImageVector,
    label: String,
    color: Color,
    onClick: () -> Unit
) {
    OutlinedCard(
        modifier = modifier.clickable(onClick = onClick),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            horizontalAlignment = Alignment.CenterHorizontally
        ) {
            Icon(icon, label, tint = color, modifier = Modifier.size(20.dp))
            Spacer(modifier = Modifier.height(4.dp))
            Text(label, fontSize = 11.sp, fontWeight = FontWeight.Medium)
        }
    }
}

private data class ServiceItem(
    val label: String,
    val icon: ImageVector,
    val color: Color,
    val route: String
)
