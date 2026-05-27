package com.epayplus.v2.ui.screens

import androidx.annotation.DrawableRes
import androidx.compose.foundation.ExperimentalFoundationApi
import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.combinedClickable
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavController
import com.epayplus.v2.R
import com.epayplus.v2.ui.layout.kioskGridColumns
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*

@OptIn(ExperimentalFoundationApi::class)
@Composable
fun KioskHomeScreen(
    navController: NavController,
    onAdminExitRequested: () -> Unit = {}
) {
    val services = listOf(
        KioskServiceItem("LOAD", Icons.Filled.PhoneAndroid, CategoryEload, "eload", R.drawable.ic_quick_eload_large),
        KioskServiceItem("Bills Payment", Icons.Filled.Receipt, CategoryBills, "bills", R.drawable.ic_quick_bills_large),
        KioskServiceItem("Cash-in", Icons.Filled.AccountBalanceWallet, CategoryEcash, "ecash", R.drawable.ic_quick_cashin_large),
        KioskServiceItem("RFID", Icons.Filled.Nfc, CategoryRfid, "rfid", R.drawable.ic_quick_rfid_large),
        KioskServiceItem("Maya Negosyo", Icons.Filled.Store, Color(0xFF00B464), "maya-negosyo", R.drawable.ic_quick_maya_negosyo),
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(
                brush = Brush.verticalGradient(
                    colors = listOf(EPayGreen, EPayGreenDark)
                )
            )
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 32.dp, vertical = 20.dp),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(
                modifier = Modifier.combinedClickable(
                    onClick = {},
                    onLongClick = onAdminExitRequested
                )
            ) {
                Text(
                    "ePayPlus",
                    fontSize = 36.sp,
                    fontWeight = FontWeight.Bold,
                    color = Color.White
                )
                Text(
                    "Select a service to get started",
                    color = Color.White.copy(alpha = 0.8f),
                    fontSize = 16.sp
                )
                Text(
                    "Long-press title to exit kiosk",
                    color = Color.White.copy(alpha = 0.45f),
                    fontSize = 11.sp,
                    modifier = Modifier.padding(top = 4.dp)
                )
            }
        }

        Card(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 16.dp),
            shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp)
        ) {
            LazyVerticalGrid(
                columns = kioskGridColumns(),
                contentPadding = PaddingValues(24.dp),
                horizontalArrangement = Arrangement.spacedBy(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
                modifier = Modifier.fillMaxSize()
            ) {
                items(services) { service ->
                    KioskServiceCard(service) {
                        navigateKioskService(navController, service.route)
                    }
                }
            }
        }
    }
}

private fun navigateKioskService(navController: NavController, routeKey: String) {
    val destination = when (routeKey) {
        "eload" -> NavRoutes.ELoadProviders.route
        "bills" -> NavRoutes.BillsCategories.route
        "ecash" -> NavRoutes.ECashProviders.route
        "rfid" -> NavRoutes.RfidProviders.route
        "maya-negosyo" -> NavRoutes.MayaNegosyo.route
        else -> return
    }
    navController.navigate(destination)
}

@Composable
private fun KioskServiceCard(service: KioskServiceItem, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .aspectRatio(1.15f)
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(16.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 4.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)
    ) {
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(16.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Surface(
                modifier = Modifier.size(64.dp),
                shape = CircleShape,
                color = service.color.copy(alpha = 0.12f),
                tonalElevation = 1.dp
            ) {
                Box(contentAlignment = Alignment.Center) {
                    if (service.logoRes != null) {
                        Image(
                            painter = painterResource(service.logoRes),
                            contentDescription = service.label,
                            modifier = Modifier.size(44.dp),
                            contentScale = ContentScale.Fit
                        )
                    } else {
                        Icon(
                            service.icon,
                            service.label,
                            tint = service.color,
                            modifier = Modifier.size(32.dp)
                        )
                    }
                }
            }
            Spacer(modifier = Modifier.height(12.dp))
            Text(
                service.label,
                fontWeight = FontWeight.SemiBold,
                fontSize = 14.sp,
                textAlign = TextAlign.Center
            )
        }
    }
}

private data class KioskServiceItem(
    val label: String,
    val icon: ImageVector,
    val color: Color,
    val route: String = "",
    @DrawableRes val logoRes: Int? = null
)
