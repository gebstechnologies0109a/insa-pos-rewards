package com.epayplus.v2.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
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
import com.epayplus.v2.data.local.entity.TransactionEntity
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.TransactionViewModel
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun TransactionHistoryScreen(
    navController: NavController,
    viewModel: TransactionViewModel = hiltViewModel()
) {
    val transactions by viewModel.transactions.collectAsState()
    var selectedFilter by remember { mutableStateOf("ALL") }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Transaction History", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.Filled.ArrowBack, "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = EPayOrange,
                    titleContentColor = Color.White,
                    navigationIconContentColor = Color.White
                )
            )
        }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            // Filter chips
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                listOf("ALL", "ELOAD", "BILLS", "ECASH").forEach { filter ->
                    FilterChip(
                        selected = selectedFilter == filter,
                        onClick = {
                            selectedFilter = filter
                            viewModel.filterByType(filter)
                        },
                        label = { Text(filter, fontSize = 12.sp) }
                    )
                }
            }

            LazyColumn(
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(transactions) { transaction ->
                    TransactionCard(transaction)
                }

                if (transactions.isEmpty()) {
                    item {
                        Box(
                            modifier = Modifier
                                .fillMaxWidth()
                                .padding(32.dp),
                            contentAlignment = Alignment.Center
                        ) {
                            Column(horizontalAlignment = Alignment.CenterHorizontally) {
                                Icon(
                                    Icons.Filled.Inbox,
                                    "Empty",
                                    modifier = Modifier.size(48.dp),
                                    tint = Color.Gray
                                )
                                Spacer(modifier = Modifier.height(8.dp))
                                Text("No transactions yet", color = Color.Gray)
                            }
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun TransactionCard(transaction: TransactionEntity) {
    val typeColor = when (transaction.type) {
        "ELOAD" -> CategoryEload
        "BILLS" -> CategoryBills
        "ECASH" -> CategoryEcash
        "WIFI" -> CategoryWifi
        else -> Color.Gray
    }

    val statusColor = when (transaction.status) {
        "SUCCESS" -> StatusSuccess
        "FAILED" -> StatusError
        "PENDING" -> StatusPending
        else -> Color.Gray
    }

    val dateFormat = SimpleDateFormat("MMM dd, hh:mm a", Locale.getDefault())

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(12.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Surface(
                modifier = Modifier.size(40.dp),
                shape = CircleShape,
                color = typeColor.copy(alpha = 0.15f)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    val icon = when (transaction.type) {
                        "ELOAD" -> Icons.Filled.PhoneAndroid
                        "BILLS" -> Icons.Filled.Receipt
                        "ECASH" -> Icons.Filled.AccountBalanceWallet
                        else -> Icons.Filled.Wifi
                    }
                    Icon(icon, transaction.type, tint = typeColor, modifier = Modifier.size(20.dp))
                }
            }
            Spacer(modifier = Modifier.width(12.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(
                    "${transaction.provider} - ${transaction.product}",
                    fontWeight = FontWeight.Medium,
                    fontSize = 14.sp,
                    maxLines = 1
                )
                Text(
                    transaction.targetNumber,
                    fontSize = 12.sp,
                    color = Color.Gray
                )
                Text(
                    dateFormat.format(Date(transaction.createdAt)),
                    fontSize = 11.sp,
                    color = Color.Gray
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                Text(
                    "₱${String.format("%,.2f", transaction.amount)}",
                    fontWeight = FontWeight.Bold,
                    fontSize = 14.sp
                )
                Surface(
                    shape = RoundedCornerShape(4.dp),
                    color = statusColor.copy(alpha = 0.15f)
                ) {
                    Text(
                        transaction.status,
                        color = statusColor,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.padding(horizontal = 6.dp, vertical = 2.dp)
                    )
                }
            }
        }
    }
}
