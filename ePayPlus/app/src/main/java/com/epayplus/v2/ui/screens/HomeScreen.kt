package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyRow
import androidx.compose.foundation.lazy.items
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
import androidx.annotation.DrawableRes
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import com.epayplus.v2.R
import com.epayplus.v2.ui.components.ProviderIcon
import com.epayplus.v2.ui.components.ProviderIcons
import com.epayplus.v2.ui.layout.isLandscape
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.data.local.entity.TransactionEntity
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.HomeUiState
import com.epayplus.v2.ui.viewmodel.HomeViewModel
import java.text.SimpleDateFormat
import java.util.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun HomeScreen(
    navController: NavController,
    viewModel: HomeViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val landscape = isLandscape

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.verticalGradient(
                        colors = listOf(EPayGreenDark, EPayGreen)
                    )
                )
                .padding(
                    top = if (landscape) 12.dp else 16.dp,
                    bottom = if (landscape) 14.dp else 20.dp
                )
        ) {
            if (landscape) {
                Row(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 20.dp),
                    horizontalArrangement = Arrangement.spacedBy(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Column(modifier = Modifier.weight(0.35f)) {
                        HomeHeaderTitle(
                            businessName = uiState.businessName,
                            onHistoryClick = { navController.navigate(NavRoutes.TransactionHistory.route) }
                        )
                    }
                    WalletCard(
                        uiState = uiState,
                        onRefresh = viewModel::refreshBalance,
                        modifier = Modifier.weight(0.65f)
                    )
                }
            } else {
                Column(modifier = Modifier.padding(horizontal = 20.dp)) {
                    HomeHeaderTitle(
                        businessName = uiState.businessName,
                        onHistoryClick = { navController.navigate(NavRoutes.TransactionHistory.route) }
                    )
                    Spacer(modifier = Modifier.height(20.dp))
                    WalletCard(
                        uiState = uiState,
                        onRefresh = viewModel::refreshBalance,
                        modifier = Modifier.fillMaxWidth()
                    )
                }
            }
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(top = if (landscape) 12.dp else 16.dp)
        ) {
            Text(
                "Quick Services",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.padding(horizontal = 20.dp)
            )
            Spacer(modifier = Modifier.height(12.dp))

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 16.dp),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                ServiceButton("LOAD", CategoryEload, iconRes = R.drawable.ic_quick_eload) {
                    navController.navigate(NavRoutes.ELoadProviders.route)
                }
                ServiceButton("Bills Payment", CategoryBills, iconRes = R.drawable.ic_quick_bills) {
                    navController.navigate(NavRoutes.BillsCategories.route)
                }
                ServiceButton("Cash-in", CategoryEcash, iconRes = R.drawable.ic_quick_cashin) {
                    navController.navigate(NavRoutes.ECashProviders.route)
                }
                ServiceButton("RFID", CategoryRfid, iconRes = R.drawable.ic_quick_rfid) {
                    navController.navigate(NavRoutes.RfidProviders.route)
                }
            }
            Spacer(modifier = Modifier.height(8.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.Center
            ) {
                ServiceButton(
                    "Maya Negosyo",
                    Color(0xFF00B464),
                    iconRes = R.drawable.ic_quick_maya_negosyo
                ) {
                    navController.navigate(NavRoutes.MayaNegosyo.route)
                }
            }

            Spacer(modifier = Modifier.height(if (landscape) 14.dp else 20.dp))

            if (uiState.announcements.isNotEmpty()) {
                Text(
                    "Announcements",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold,
                    modifier = Modifier.padding(horizontal = 20.dp)
                )
                Spacer(modifier = Modifier.height(8.dp))
                LazyRow(
                    contentPadding = PaddingValues(horizontal = 20.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(uiState.announcements) { announcement ->
                        Card(
                            modifier = Modifier.width(if (landscape) 360.dp else 280.dp),
                            shape = RoundedCornerShape(14.dp),
                            colors = CardDefaults.cardColors(containerColor = EPayGoldSurface)
                        ) {
                            Column(modifier = Modifier.padding(16.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Icon(
                                        Icons.Filled.Campaign,
                                        "Announcement",
                                        tint = EPayGold,
                                        modifier = Modifier.size(20.dp)
                                    )
                                    Spacer(modifier = Modifier.width(8.dp))
                                    Text(
                                        announcement.title,
                                        fontWeight = FontWeight.SemiBold,
                                        fontSize = 14.sp,
                                        maxLines = 1,
                                        overflow = TextOverflow.Ellipsis
                                    )
                                }
                                Spacer(modifier = Modifier.height(6.dp))
                                Text(
                                    announcement.content,
                                    fontSize = 12.sp,
                                    color = EPayMediumGray,
                                    maxLines = 2,
                                    overflow = TextOverflow.Ellipsis
                                )
                            }
                        }
                    }
                }
                Spacer(modifier = Modifier.height(if (landscape) 14.dp else 20.dp))
            }

            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = 20.dp),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text(
                    "Recent Transactions",
                    style = MaterialTheme.typography.titleMedium,
                    fontWeight = FontWeight.SemiBold
                )
                TextButton(onClick = { navController.navigate(NavRoutes.TransactionHistory.route) }) {
                    Text("See All", color = EPayGreen, fontSize = 13.sp)
                }
            }

            if (uiState.recentTransactions.isEmpty()) {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 20.dp),
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                ) {
                    Column(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(if (landscape) 24.dp else 32.dp),
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Icon(
                            Icons.Outlined.Inbox,
                            "Empty",
                            modifier = Modifier.size(40.dp),
                            tint = EPayMediumGray.copy(alpha = 0.5f)
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text("No transactions yet", color = EPayMediumGray, fontSize = 14.sp)
                        Text(
                            "Start by loading, paying bills, or cashing in",
                            color = EPayMediumGray.copy(alpha = 0.7f),
                            fontSize = 12.sp
                        )
                    }
                }
            } else {
                Card(
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(horizontal = 20.dp),
                    shape = RoundedCornerShape(14.dp),
                    colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)
                ) {
                    Column {
                        uiState.recentTransactions.forEachIndexed { index, transaction ->
                            RecentTransactionRow(transaction)
                            if (index < uiState.recentTransactions.lastIndex) {
                                Divider(
                                    modifier = Modifier.padding(horizontal = 16.dp),
                                    color = EPayLightGray
                                )
                            }
                        }
                    }
                }
            }

            Spacer(modifier = Modifier.height(24.dp))
        }
    }
}

@Composable
private fun HomeHeaderTitle(businessName: String, onHistoryClick: () -> Unit) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column {
            Text(
                "ePayPlus",
                fontSize = 24.sp,
                fontWeight = FontWeight.Bold,
                color = Color.White
            )
            Text(
                businessName.ifEmpty { "Welcome" },
                fontSize = 13.sp,
                color = Color.White.copy(alpha = 0.8f)
            )
        }
        IconButton(onClick = onHistoryClick) {
            Icon(Icons.Outlined.Notifications, "Notifications", tint = Color.White)
        }
    }
}

@Composable
private fun WalletCard(
    uiState: HomeUiState,
    onRefresh: () -> Unit,
    modifier: Modifier = Modifier
) {
    val landscape = isLandscape

    Card(
        modifier = modifier,
        shape = RoundedCornerShape(20.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 6.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        if (landscape) {
            Row(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                WalletPrimarySection(
                    uiState = uiState,
                    onRefresh = onRefresh,
                    modifier = Modifier.weight(1f)
                )
                Divider(
                    modifier = Modifier
                        .fillMaxHeight()
                        .width(1.dp),
                    color = EPayLightGray
                )
                WalletSecondarySection(
                    uiState = uiState,
                    modifier = Modifier.weight(1f)
                )
            }
        } else {
            Column(modifier = Modifier.padding(20.dp)) {
                WalletPrimarySection(uiState = uiState, onRefresh = onRefresh)
                Spacer(modifier = Modifier.height(12.dp))
                Divider(color = EPayLightGray)
                Spacer(modifier = Modifier.height(12.dp))
                WalletSecondarySection(uiState = uiState)
            }
        }
    }
}

@Composable
private fun WalletPrimarySection(
    uiState: HomeUiState,
    onRefresh: () -> Unit,
    modifier: Modifier = Modifier
) {
    Column(modifier = modifier) {
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Text("Dual Wallets", fontSize = 13.sp, color = EPayMediumGray)
            if (uiState.isRefreshing) {
                CircularProgressIndicator(
                    modifier = Modifier.size(16.dp),
                    color = EPayGreen,
                    strokeWidth = 2.dp
                )
            } else {
                IconButton(onClick = onRefresh, modifier = Modifier.size(28.dp)) {
                    Icon(Icons.Filled.Refresh, "Refresh", tint = EPayGreen, modifier = Modifier.size(18.dp))
                }
            }
        }
        Text(
            "₱ ${String.format("%,.2f", uiState.eloadBalance)}",
            fontSize = 28.sp,
            fontWeight = FontWeight.Bold,
            color = EPayGreenDark
        )
        Text("E-Load Wallet", fontSize = 11.sp, color = EPayMediumGray)
        Spacer(modifier = Modifier.height(8.dp))
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column {
                Text("Bills / Cash-In", fontSize = 11.sp, color = EPayMediumGray)
                Text(
                    "₱ ${String.format("%,.2f", uiState.billsBalance)}",
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 16.sp,
                    color = EPayBlue
                )
            }
            Column(horizontalAlignment = Alignment.End) {
                Text("Combined", fontSize = 11.sp, color = EPayMediumGray)
                Text(
                    "₱ ${String.format("%,.2f", uiState.balance)}",
                    fontWeight = FontWeight.SemiBold,
                    fontSize = 16.sp,
                    color = EPayDarkGray
                )
            }
        }
    }
}

@Composable
private fun WalletSecondarySection(
    uiState: HomeUiState,
    modifier: Modifier = Modifier
) {
    Row(
        modifier = modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween,
        verticalAlignment = Alignment.CenterVertically
    ) {
        Column {
            Text("Today's Sales", fontSize = 11.sp, color = EPayMediumGray)
            Text(
                "₱ ${String.format("%,.2f", uiState.todaySales)}",
                fontWeight = FontWeight.SemiBold,
                fontSize = 16.sp,
                color = EPayGreen
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            Text("Transactions", fontSize = 11.sp, color = EPayMediumGray)
            Text(
                "${uiState.todayTransactions}",
                fontWeight = FontWeight.SemiBold,
                fontSize = 16.sp,
                color = EPayDarkGray
            )
        }
    }
}

@Composable
private fun ServiceButton(
    label: String,
    color: Color,
    icon: ImageVector? = null,
    @DrawableRes iconRes: Int? = null,
    onClick: () -> Unit
) {
    val landscape = isLandscape
    Column(
        horizontalAlignment = Alignment.CenterHorizontally,
        modifier = Modifier
            .clip(RoundedCornerShape(12.dp))
            .clickable(onClick = onClick)
            .padding(8.dp)
    ) {
        Surface(
            modifier = Modifier.size(if (landscape) 64.dp else 56.dp),
            shape = CircleShape,
            color = color.copy(alpha = 0.12f),
            tonalElevation = 1.dp
        ) {
            Box(contentAlignment = Alignment.Center) {
                when {
                    iconRes != null -> {
                        androidx.compose.foundation.Image(
                            painter = painterResource(iconRes),
                            contentDescription = label,
                            modifier = Modifier.size(if (landscape) 38.dp else 34.dp),
                            contentScale = androidx.compose.ui.layout.ContentScale.Fit
                        )
                    }
                    icon != null -> {
                        Icon(icon, label, tint = color, modifier = Modifier.size(28.dp))
                    }
                }
            }
        }
        Spacer(modifier = Modifier.height(6.dp))
        Text(
            label,
            fontSize = 12.sp,
            fontWeight = FontWeight.Medium,
            color = EPayDarkGray
        )
    }
}

@Composable
private fun RecentTransactionRow(transaction: TransactionEntity) {
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
        else -> StatusPending
    }
    val dateFormat = SimpleDateFormat("MMM dd, hh:mm a", Locale.getDefault())

    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(horizontal = 16.dp, vertical = 12.dp),
        verticalAlignment = Alignment.CenterVertically
    ) {
        val providerDrawable = ProviderIcons.resolve(transaction.provider, transaction.provider)
        if (providerDrawable != null) {
            ProviderIcon(
                providerCode = transaction.provider,
                providerName = transaction.provider,
                modifier = Modifier,
                size = 40.dp,
                fallbackIcon = typeIcon,
                fallbackTint = typeColor,
                backgroundColor = typeColor.copy(alpha = 0.08f),
                contentPadding = 3.dp
            )
        } else {
            Surface(
                modifier = Modifier.size(40.dp),
                shape = CircleShape,
                color = typeColor.copy(alpha = 0.12f)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(typeIcon, transaction.type, tint = typeColor, modifier = Modifier.size(20.dp))
                }
            }
        }
        Spacer(modifier = Modifier.width(12.dp))
        Column(modifier = Modifier.weight(1f)) {
            Text(
                transaction.provider,
                fontWeight = FontWeight.Medium,
                fontSize = 14.sp,
                maxLines = 1,
                overflow = TextOverflow.Ellipsis
            )
            Text(
                "${transaction.targetNumber} • ${dateFormat.format(Date(transaction.createdAt))}",
                fontSize = 11.sp,
                color = EPayMediumGray
            )
        }
        Column(horizontalAlignment = Alignment.End) {
            Text(
                "₱${String.format("%,.2f", transaction.amount)}",
                fontWeight = FontWeight.SemiBold,
                fontSize = 14.sp
            )
            Surface(
                shape = RoundedCornerShape(4.dp),
                color = statusColor.copy(alpha = 0.12f)
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
