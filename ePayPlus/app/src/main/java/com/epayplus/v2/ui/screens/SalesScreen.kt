package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.data.local.entity.SalesSummaryEntity
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.SalesViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SalesScreen(
    navController: NavController,
    viewModel: SalesViewModel = hiltViewModel()
) {
    val salesSummaries by viewModel.salesSummaries.collectAsState()
    val todaySummary by viewModel.todaySummary.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Sales Report", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.Filled.ArrowBack, "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = EPayBlue,
                    titleContentColor = Color.White,
                    navigationIconContentColor = Color.White
                )
            )
        }
    ) { paddingValues ->
        LazyColumn(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues),
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            // Today's summary card
            item {
                TodaySummaryCard(todaySummary)
            }

            // Breakdown cards
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    StatCard(
                        modifier = Modifier.weight(1f),
                        label = "E-Load",
                        value = todaySummary.eloadCount,
                        amount = todaySummary.eloadSales,
                        color = CategoryEload
                    )
                    StatCard(
                        modifier = Modifier.weight(1f),
                        label = "Bills",
                        value = todaySummary.billsCount,
                        amount = todaySummary.billsSales,
                        color = CategoryBills
                    )
                }
            }
            item {
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    StatCard(
                        modifier = Modifier.weight(1f),
                        label = "E-Cash",
                        value = todaySummary.ecashCount,
                        amount = todaySummary.ecashSales,
                        color = CategoryEcash
                    )
                    StatCard(
                        modifier = Modifier.weight(1f),
                        label = "WiFi",
                        value = todaySummary.wifiCount,
                        amount = todaySummary.wifiSales,
                        color = CategoryWifi
                    )
                }
            }

            // History
            item {
                Spacer(modifier = Modifier.height(8.dp))
                Text("Recent Days", fontWeight = FontWeight.SemiBold)
            }

            items(salesSummaries) { summary ->
                SalesDayCard(summary)
            }
        }
    }
}

@Composable
private fun TodaySummaryCard(summary: SalesSummaryEntity) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.cardColors(containerColor = EPayBlue)
    ) {
        Column(modifier = Modifier.padding(20.dp)) {
            Text("Today's Sales", color = Color.White.copy(alpha = 0.8f), fontSize = 14.sp)
            Spacer(modifier = Modifier.height(4.dp))
            Text(
                "₱ ${String.format("%,.2f", summary.totalSales)}",
                color = Color.White,
                fontSize = 28.sp,
                fontWeight = FontWeight.Bold
            )
            Spacer(modifier = Modifier.height(8.dp))
            Row(horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                Column {
                    Text("Transactions", color = Color.White.copy(alpha = 0.7f), fontSize = 11.sp)
                    Text("${summary.totalTransactions}", color = Color.White, fontWeight = FontWeight.SemiBold)
                }
                Column {
                    Text("Profit", color = Color.White.copy(alpha = 0.7f), fontSize = 11.sp)
                    Text("₱${String.format("%,.2f", summary.totalProfit)}", color = Color.White, fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}

@Composable
private fun StatCard(modifier: Modifier, label: String, value: Int, amount: Double, color: Color) {
    Card(
        modifier = modifier,
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(modifier = Modifier.padding(12.dp)) {
            Text(label, fontSize = 12.sp, color = Color.Gray)
            Text("$value txns", fontWeight = FontWeight.SemiBold)
            Text("₱${String.format("%,.0f", amount)}", color = color, fontWeight = FontWeight.Bold)
        }
    }
}

@Composable
private fun SalesDayCard(summary: SalesSummaryEntity) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(8.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column {
                Text(summary.date, fontWeight = FontWeight.Medium)
                Text("${summary.totalTransactions} transactions", fontSize = 12.sp, color = Color.Gray)
            }
            Text(
                "₱${String.format("%,.2f", summary.totalSales)}",
                fontWeight = FontWeight.Bold,
                color = EPayGreen
            )
        }
    }
}
