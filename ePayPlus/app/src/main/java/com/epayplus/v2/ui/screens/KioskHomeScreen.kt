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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.epayplus.v2.ui.theme.*

@Composable
fun KioskHomeScreen() {
    val services = listOf(
        KioskService("Buy Load", Icons.Filled.PhoneAndroid, CategoryEload),
        KioskService("Bills Payment", Icons.Filled.Receipt, CategoryBills),
        KioskService("Cash-In", Icons.Filled.AccountBalanceWallet, CategoryEcash),
        KioskService("WiFi", Icons.Filled.Wifi, CategoryWifi),
        KioskService("Balance Inquiry", Icons.Filled.AccountBalance, EPayBlue),
        KioskService("More", Icons.Filled.MoreHoriz, EPayOrange),
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
        // Header
        Column(
            modifier = Modifier
                .fillMaxWidth()
                .padding(32.dp),
            horizontalAlignment = Alignment.CenterHorizontally
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
        }

        // Service Grid
        Card(
            modifier = Modifier
                .fillMaxSize()
                .padding(horizontal = 16.dp),
            shape = RoundedCornerShape(topStart = 24.dp, topEnd = 24.dp)
        ) {
            LazyVerticalGrid(
                columns = GridCells.Fixed(2),
                contentPadding = PaddingValues(24.dp),
                horizontalArrangement = Arrangement.spacedBy(16.dp),
                verticalArrangement = Arrangement.spacedBy(16.dp),
                modifier = Modifier.fillMaxSize()
            ) {
                items(services) { service ->
                    KioskServiceCard(service)
                }
            }
        }
    }
}

@Composable
private fun KioskServiceCard(service: KioskService) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .aspectRatio(1f)
            .clickable { },
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
                modifier = Modifier.size(56.dp),
                shape = RoundedCornerShape(12.dp),
                color = service.color.copy(alpha = 0.15f)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Icon(
                        service.icon,
                        service.label,
                        tint = service.color,
                        modifier = Modifier.size(32.dp)
                    )
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

private data class KioskService(
    val label: String,
    val icon: ImageVector,
    val color: Color
)
