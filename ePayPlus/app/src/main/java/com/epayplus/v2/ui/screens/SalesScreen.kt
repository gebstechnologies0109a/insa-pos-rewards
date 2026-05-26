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
import androidx.compose.ui.graphics.Brush
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
    val todaySales by viewModel.todaySales.collectAsState()
    val todayCount by viewModel.todayCount.collectAsState()

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        Box(
            modifier = Modifier.fillMaxWidth()
                .background(brush = Brush.verticalGradient(listOf(EPayBlue, EPayBlue.copy(alpha = 0.85f))))
                .padding(16.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = { navController.popBackStack() }) {
                    Icon(Icons.Filled.ArrowBack, "Back", tint = Color.White)
                }
                Spacer(modifier = Modifier.width(8.dp))
                Text("Sales Report", fontSize = 22.sp, fontWeight = FontWeight.Bold, color = Color.White)
            }
        }

        LazyColumn(
            contentPadding = PaddingValues(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            item {
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(20.dp),
                    colors = CardDefaults.cardColors(containerColor = EPayBlue)
                ) {
                    Column(modifier = Modifier.padding(24.dp)) {
                        Text("Today's Summary", color = Color.White.copy(alpha = 0.8f), fontSize = 14.sp)
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            "₱ ${String.format("%,.2f", todaySales)}",
                            color = Color.White,
                            fontSize = 32.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Text("Total Sales", color = Color.White.copy(alpha = 0.7f), fontSize = 12.sp)
                        Spacer(modifier = Modifier.height(16.dp))
                        Row(horizontalArrangement = Arrangement.spacedBy(24.dp)) {
                            Column {
                                Text("Transactions", color = Color.White.copy(alpha = 0.7f), fontSize = 11.sp)
                                Text("$todayCount", color = Color.White, fontWeight = FontWeight.Bold, fontSize = 20.sp)
                            }
                        }
                    }
                }
            }

            item {
                Spacer(modifier = Modifier.height(4.dp))
                Text("Recent Days", fontWeight = FontWeight.SemiBold, color = EPayMediumGray)
            }

            if (salesSummaries.isEmpty()) {
                item {
                    Card(
                        modifier = Modifier.fillMaxWidth(),
                        shape = RoundedCornerShape(14.dp)
                    ) {
                        Column(
                            modifier = Modifier.fillMaxWidth().padding(32.dp),
                            horizontalAlignment = Alignment.CenterHorizontally
                        ) {
                            Icon(Icons.Filled.BarChart, "No data", modifier = Modifier.size(48.dp), tint = EPayMediumGray.copy(alpha = 0.4f))
                            Spacer(modifier = Modifier.height(8.dp))
                            Text("No sales data yet", color = EPayMediumGray)
                        }
                    }
                }
            }

            items(salesSummaries) { summary ->
                SalesDayCard(summary)
            }
        }
    }
}

@Composable
private fun SalesDayCard(summary: SalesSummaryEntity) {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column {
                Text(summary.date, fontWeight = FontWeight.SemiBold)
                Text("${summary.totalTransactions} transactions", fontSize = 12.sp, color = EPayMediumGray)
            }
            Column(horizontalAlignment = Alignment.End) {
                Text("₱${String.format("%,.2f", summary.totalSales)}", fontWeight = FontWeight.Bold, color = EPayGreen)
                Text("Profit: ₱${String.format("%,.2f", summary.totalProfit)}", fontSize = 11.sp, color = EPayMediumGray)
            }
        }
    }
}
