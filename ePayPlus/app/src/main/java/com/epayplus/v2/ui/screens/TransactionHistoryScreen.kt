package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
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
import androidx.compose.foundation.lazy.LazyListScope
import com.epayplus.v2.ui.layout.isLandscape
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
    var searchQuery by remember { mutableStateOf("") }

    val landscape = isLandscape

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        CenterAlignedTopAppBar(
            title = { Text("Transaction History", fontWeight = FontWeight.Bold) },
            navigationIcon = {
                IconButton(onClick = { navController.popBackStack() }) {
                    Icon(Icons.Filled.ArrowBack, "Back")
                }
            },
            colors = TopAppBarDefaults.centerAlignedTopAppBarColors(
                containerColor = EPayGreen,
                titleContentColor = Color.White,
                navigationIconContentColor = Color.White
            )
        )

        if (landscape) {
            Row(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(horizontal = 16.dp, vertical = 8.dp),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Column(modifier = Modifier.weight(0.35f)) {
                    TransactionFilters(
                        searchQuery = searchQuery,
                        selectedFilter = selectedFilter,
                        onSearchChange = {
                            searchQuery = it
                            viewModel.search(it)
                        },
                        onClearSearch = {
                            searchQuery = ""
                            viewModel.search("")
                        },
                        onFilterSelected = { filter ->
                            selectedFilter = filter
                            viewModel.filterByType(filter)
                        }
                    )
                }
                LazyColumn(
                    modifier = Modifier.weight(0.65f),
                    contentPadding = PaddingValues(vertical = 8.dp),
                    verticalArrangement = Arrangement.spacedBy(8.dp)
                ) {
                    transactionListItems(transactions)
                }
            }
        } else {
            Column(modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp)) {
                TransactionFilters(
                    searchQuery = searchQuery,
                    selectedFilter = selectedFilter,
                    onSearchChange = {
                        searchQuery = it
                        viewModel.search(it)
                    },
                    onClearSearch = {
                        searchQuery = ""
                        viewModel.search("")
                    },
                    onFilterSelected = { filter ->
                        selectedFilter = filter
                        viewModel.filterByType(filter)
                    }
                )
            }

            LazyColumn(
                contentPadding = PaddingValues(horizontal = 16.dp, vertical = 8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                transactionListItems(transactions)
            }
        }
    }
}

@Composable
private fun TransactionFilters(
    searchQuery: String,
    selectedFilter: String,
    onSearchChange: (String) -> Unit,
    onClearSearch: () -> Unit,
    onFilterSelected: (String) -> Unit
) {
    OutlinedTextField(
        value = searchQuery,
        onValueChange = onSearchChange,
        placeholder = { Text("Search transactions...") },
        leadingIcon = { Icon(Icons.Outlined.Search, "Search", tint = EPayMediumGray) },
        trailingIcon = {
            if (searchQuery.isNotEmpty()) {
                IconButton(onClick = onClearSearch) {
                    Icon(Icons.Filled.Clear, "Clear", modifier = Modifier.size(18.dp))
                }
            }
        },
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        singleLine = true,
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor = EPayGreen,
            unfocusedContainerColor = Color.White,
            focusedContainerColor = Color.White
        )
    )

    Spacer(modifier = Modifier.height(8.dp))

    Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
        listOf("ALL", "ELOAD", "BILLS", "ECASH").forEach { filter ->
            FilterChip(
                selected = selectedFilter == filter,
                onClick = { onFilterSelected(filter) },
                label = {
                    Text(
                        when (filter) {
                            "ALL" -> "All"
                            "ELOAD" -> "Load"
                            "BILLS" -> "Bills"
                            "ECASH" -> "Cash-In"
                            else -> filter
                        },
                        fontSize = 12.sp
                    )
                },
                colors = FilterChipDefaults.filterChipColors(
                    selectedContainerColor = EPayGreen.copy(alpha = 0.12f),
                    selectedLabelColor = EPayGreen
                )
            )
        }
    }
}

private fun LazyListScope.transactionListItems(
    transactions: List<TransactionEntity>
) {
    items(transactions) { transaction ->
        TransactionCard(transaction)
    }

    if (transactions.isEmpty()) {
        item {
            Box(
                modifier = Modifier.fillMaxWidth().padding(40.dp),
                contentAlignment = Alignment.Center
            ) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Outlined.Inbox, "Empty", modifier = Modifier.size(56.dp), tint = EPayMediumGray.copy(alpha = 0.4f))
                    Spacer(modifier = Modifier.height(12.dp))
                    Text("No transactions found", fontWeight = FontWeight.Medium, color = EPayMediumGray)
                    Text("Your transactions will appear here", fontSize = 13.sp, color = EPayMediumGray.copy(alpha = 0.7f))
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
        else -> EPayMediumGray
    }
    val typeIcon = when (transaction.type) {
        "ELOAD" -> Icons.Filled.PhoneAndroid
        "BILLS" -> Icons.Filled.Receipt
        "ECASH" -> Icons.Filled.AccountBalanceWallet
        else -> Icons.Filled.SwapHoriz
    }
    val statusColor = when (transaction.status) {
        "SUCCESS" -> StatusSuccess
        "FAILED" -> StatusError
        "PENDING" -> StatusPending
        else -> EPayMediumGray
    }
    val dateFormat = SimpleDateFormat("MMM dd, yyyy hh:mm a", Locale.getDefault())

    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Row(
            modifier = Modifier.fillMaxWidth().padding(14.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Surface(modifier = Modifier.size(44.dp), shape = CircleShape, color = typeColor.copy(alpha = 0.12f)) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(typeIcon, transaction.type, tint = typeColor, modifier = Modifier.size(22.dp))
                }
            }
            Spacer(modifier = Modifier.width(14.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text("${transaction.provider} - ${transaction.product}", fontWeight = FontWeight.SemiBold, fontSize = 14.sp, maxLines = 1)
                Text(transaction.targetNumber, fontSize = 12.sp, color = EPayMediumGray)
                Text(dateFormat.format(Date(transaction.createdAt)), fontSize = 11.sp, color = EPayMediumGray.copy(alpha = 0.7f))
            }
            Column(horizontalAlignment = Alignment.End) {
                Text("₱${String.format("%,.2f", transaction.amount)}", fontWeight = FontWeight.Bold, fontSize = 15.sp)
                Surface(shape = RoundedCornerShape(6.dp), color = statusColor.copy(alpha = 0.12f)) {
                    Text(
                        transaction.status,
                        color = statusColor,
                        fontSize = 10.sp,
                        fontWeight = FontWeight.SemiBold,
                        modifier = Modifier.padding(horizontal = 8.dp, vertical = 3.dp)
                    )
                }
            }
        }
    }
}
