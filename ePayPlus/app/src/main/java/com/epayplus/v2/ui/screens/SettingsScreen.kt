package com.epayplus.v2.ui.screens

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.navigation.NavController
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SettingsScreen(navController: NavController) {
    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Settings", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.Filled.ArrowBack, "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = EPayGreen,
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
                .verticalScroll(rememberScrollState())
                .padding(16.dp),
            verticalArrangement = Arrangement.spacedBy(8.dp)
        ) {
            Text("Account", fontWeight = FontWeight.SemiBold, color = Color.Gray)
            SettingsItem(Icons.Filled.Person, "Account Info", "Manage your account details") {}
            SettingsItem(Icons.Filled.Lock, "Change PIN", "Update your security PIN") {}
            SettingsItem(Icons.Filled.Key, "API Settings", "Configure server connection") {}

            Spacer(modifier = Modifier.height(8.dp))
            Text("Device", fontWeight = FontWeight.SemiBold, color = Color.Gray)
            SettingsItem(Icons.Filled.Print, "Printer Setup", "Configure thermal printer") {}
            SettingsItem(Icons.Filled.Bluetooth, "Bluetooth", "Manage Bluetooth devices") {}
            SettingsItem(Icons.Filled.Wifi, "WiFi Vendo", "Piso WiFi settings") {}
            SettingsItem(Icons.Filled.ScreenLockPortrait, "Kiosk Mode", "Enable kiosk lock mode") {}

            Spacer(modifier = Modifier.height(8.dp))
            Text("Products", fontWeight = FontWeight.SemiBold, color = Color.Gray)
            SettingsItem(Icons.Filled.PhoneAndroid, "E-Load Settings", "Manage load products") {}
            SettingsItem(Icons.Filled.Receipt, "Bills Settings", "Configure bills payment") {}
            SettingsItem(Icons.Filled.Sms, "SMS Templates", "Configure SMS-based loading") {}

            Spacer(modifier = Modifier.height(8.dp))
            Text("App", fontWeight = FontWeight.SemiBold, color = Color.Gray)
            SettingsItem(Icons.Filled.Notifications, "Notifications", "Manage push notifications") {}
            SettingsItem(Icons.Filled.DarkMode, "Appearance", "Theme and display settings") {}
            SettingsItem(Icons.Filled.Info, "About", "App version and info") {
                navController.navigate(NavRoutes.About.route)
            }
        }
    }
}

@Composable
private fun SettingsItem(
    icon: ImageVector,
    title: String,
    subtitle: String,
    onClick: () -> Unit
) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(12.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Icon(icon, title, tint = EPayGreen, modifier = Modifier.size(24.dp))
            Spacer(modifier = Modifier.width(16.dp))
            Column(modifier = Modifier.weight(1f)) {
                Text(title, fontWeight = FontWeight.Medium)
                Text(subtitle, style = MaterialTheme.typography.bodySmall, color = Color.Gray)
            }
            Icon(Icons.Filled.ChevronRight, "Go", tint = Color.Gray)
        }
    }
}
