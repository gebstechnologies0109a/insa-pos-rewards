package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.R
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.MayaNegosyoViewModel
import com.epayplus.v2.util.MayaNegosyoLauncher

private val MayaGreen = Color(0xFF00B464)

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun MayaNegosyoHubScreen(
    navController: NavController,
    viewModel: MayaNegosyoViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    val context = LocalContext.current
    var checkoutAmount by remember { mutableStateOf("100.00") }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Maya Negosyo", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = MayaGreen,
                    titleContentColor = Color.White,
                    navigationIconContentColor = Color.White
                )
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(12.dp)
        ) {
            if (uiState.isLoading) {
                LinearProgressIndicator(modifier = Modifier.fillMaxWidth(), color = MayaGreen)
            }

            HubActionCard(
                title = "Open Maya Negosyo App",
                subtitle = if (viewModel.negosyoInstalled()) "Installed — tap to launch" else "Opens Play Store if not installed",
                iconRes = R.drawable.ic_quick_maya_negosyo,
                accent = MayaGreen,
                onClick = { MayaNegosyoLauncher.launchNegosyo(context) }
            )

            HubActionCard(
                title = "Open Maya Business App",
                subtitle = if (viewModel.businessInstalled()) "Installed — secondary merchant app" else "ph.maya.business.android",
                icon = Icons.Filled.Business,
                accent = EPayBlue,
                onClick = { MayaNegosyoLauncher.launchBusiness(context) }
            )

            Card(shape = RoundedCornerShape(14.dp)) {
                Column(Modifier.padding(16.dp)) {
                    Text("Wallet & Balance", fontWeight = FontWeight.SemiBold)
                    Spacer(Modifier.height(8.dp))
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                        Column {
                            Text("E-Load", fontSize = 11.sp, color = EPayMediumGray)
                            Text(
                                "₱ ${String.format("%,.2f", uiState.eloadBalance)}",
                                fontWeight = FontWeight.Bold,
                                color = EPayGreenDark
                            )
                        }
                        Column(horizontalAlignment = Alignment.End) {
                            Text("Bills / Cash-In", fontSize = 11.sp, color = EPayMediumGray)
                            Text(
                                "₱ ${String.format("%,.2f", uiState.billsBalance)}",
                                fontWeight = FontWeight.Bold,
                                color = EPayBlue
                            )
                        }
                    }
                    Spacer(Modifier.height(6.dp))
                    Text(
                        "Combined ₱ ${String.format("%,.2f", uiState.combinedBalance)}",
                        fontSize = 12.sp,
                        color = EPayMediumGray
                    )
                }
            }

            val billerOn = uiState.integration?.billerEnabled == true
            HubActionCard(
                title = "Bills via Maya",
                subtitle = if (billerOn) "Partner Biller enabled — customers pay in Maya apps" else "Partner Biller disabled in admin",
                icon = Icons.Filled.Receipt,
                accent = if (billerOn) CategoryBills else EPayMediumGray,
                onClick = { navController.navigate(NavRoutes.BillsCategories.route) }
            )

            Card(shape = RoundedCornerShape(14.dp)) {
                Column(Modifier.padding(16.dp)) {
                    Text("Accept Maya Payments", fontWeight = FontWeight.SemiBold)
                    Text(
                        if (uiState.integration?.checkoutDemoMode == true && uiState.integration?.checkoutEnabled != true)
                            "Demo mode — configure Checkout keys on server"
                        else if (uiState.integration?.checkoutEnabled == true)
                            "Live Maya Checkout"
                        else "Checkout scaffold",
                        fontSize = 12.sp,
                        color = EPayMediumGray
                    )
                    Spacer(Modifier.height(8.dp))
                    OutlinedTextField(
                        value = checkoutAmount,
                        onValueChange = { checkoutAmount = it },
                        label = { Text("Amount (PHP)") },
                        modifier = Modifier.fillMaxWidth(),
                        singleLine = true
                    )
                    Spacer(Modifier.height(8.dp))
                    Button(
                        onClick = {
                            checkoutAmount.toDoubleOrNull()?.let { amt ->
                                viewModel.createCheckout(amt, "ePayPlus Maya payment")
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = MayaGreen)
                    ) {
                        Icon(Icons.Filled.QrCode, null, modifier = Modifier.size(18.dp))
                        Spacer(Modifier.width(8.dp))
                        Text("Create Checkout")
                    }
                    uiState.checkoutMessage?.let {
                        Text(it, fontSize = 11.sp, color = EPayMediumGray, modifier = Modifier.padding(top = 6.dp))
                    }
                }
            }

            Text("Quick links", fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                QuickChip("E-Load", Modifier.weight(1f)) { navController.navigate(NavRoutes.ELoadProviders.route) }
                QuickChip("Bills", Modifier.weight(1f)) { navController.navigate(NavRoutes.BillsCategories.route) }
                QuickChip("Cash-In", Modifier.weight(1f)) { navController.navigate(NavRoutes.ECashProviders.route) }
            }
            HubActionCard(
                title = "Transaction History",
                subtitle = "ePayPlus ledger",
                icon = Icons.Outlined.History,
                accent = EPayDarkGray,
                onClick = { navController.navigate(NavRoutes.TransactionHistory.route) }
            )

            Card(
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(
                    containerColor = if (billerOn) EPayGreen.copy(alpha = 0.08f) else EPayLightGray
                )
            ) {
                Row(
                    Modifier.padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    Icon(
                        if (billerOn) Icons.Filled.CheckCircle else Icons.Filled.Info,
                        null,
                        tint = if (billerOn) EPayGreen else EPayMediumGray
                    )
                    Spacer(Modifier.width(12.dp))
                    Column {
                        Text("Integration Status", fontWeight = FontWeight.SemiBold)
                        Text(
                            "Partner Biller: ${if (billerOn) "Enabled" else "Disabled"} · " +
                                "Checkout: ${if (uiState.integration?.checkoutEnabled == true) "Live" else if (uiState.integration?.checkoutDemoMode == true) "Demo" else "Off"}",
                            fontSize = 12.sp,
                            color = EPayMediumGray
                        )
                    }
                }
            }
        }
    }
}

@Composable
private fun HubActionCard(
    title: String,
    subtitle: String,
    accent: Color,
    onClick: () -> Unit,
    icon: androidx.compose.ui.graphics.vector.ImageVector? = null,
    iconRes: Int? = null
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(14.dp),
        elevation = CardDefaults.cardElevation(2.dp)
    ) {
        Row(
            Modifier.padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Surface(
                modifier = Modifier.size(48.dp),
                shape = RoundedCornerShape(12.dp),
                color = accent.copy(alpha = 0.12f)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    when {
                        iconRes != null -> Icon(
                            painter = painterResource(iconRes),
                            contentDescription = title,
                            tint = Color.Unspecified,
                            modifier = Modifier.size(32.dp)
                        )
                        icon != null -> Icon(icon, title, tint = accent)
                    }
                }
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text(title, fontWeight = FontWeight.SemiBold)
                Text(subtitle, fontSize = 12.sp, color = EPayMediumGray)
            }
            Icon(Icons.Filled.ChevronRight, null, tint = EPayMediumGray)
        }
    }
}

@Composable
private fun QuickChip(label: String, modifier: Modifier = Modifier, onClick: () -> Unit) {
    Surface(
        modifier = modifier.clickable(onClick = onClick),
        shape = RoundedCornerShape(10.dp),
        color = MayaGreen.copy(alpha = 0.1f)
    ) {
        Text(
            label,
            modifier = Modifier.padding(vertical = 10.dp, horizontal = 8.dp),
            fontSize = 12.sp,
            fontWeight = FontWeight.Medium,
            color = MayaGreen
        )
    }
}
