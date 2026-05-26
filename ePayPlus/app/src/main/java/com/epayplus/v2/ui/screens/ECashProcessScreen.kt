package com.epayplus.v2.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.ECashViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ECashProcessScreen(
    navController: NavController,
    providerCode: String,
    viewModel: ECashViewModel = hiltViewModel()
) {
    var mobileNumber by remember { mutableStateOf("") }
    var amount by remember { mutableStateOf("") }
    var isProcessing by remember { mutableStateOf(false) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Cash-In: $providerCode", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.Filled.ArrowBack, "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = CategoryEcash,
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
                .padding(16.dp)
        ) {
            OutlinedTextField(
                value = mobileNumber,
                onValueChange = { if (it.length <= 11) mobileNumber = it },
                label = { Text("Mobile Number") },
                leadingIcon = { Icon(Icons.Filled.Phone, "Phone") },
                placeholder = { Text("09xxxxxxxxx") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                singleLine = true
            )

            Spacer(modifier = Modifier.height(16.dp))

            OutlinedTextField(
                value = amount,
                onValueChange = { amount = it },
                label = { Text("Amount") },
                leadingIcon = { Text("₱", modifier = Modifier.padding(start = 12.dp)) },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                singleLine = true
            )

            Spacer(modifier = Modifier.height(8.dp))

            // Quick amount buttons
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                listOf("100", "200", "500", "1000").forEach { quickAmount ->
                    OutlinedButton(
                        onClick = { amount = quickAmount },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(8.dp)
                    ) {
                        Text("₱$quickAmount", fontSize = MaterialTheme.typography.labelSmall.fontSize)
                    }
                }
            }

            Spacer(modifier = Modifier.height(32.dp))

            Button(
                onClick = {
                    isProcessing = true
                    viewModel.processCashIn(providerCode, mobileNumber, amount.toDoubleOrNull() ?: 0.0)
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(52.dp),
                enabled = mobileNumber.length >= 10 && amount.isNotEmpty() && !isProcessing,
                colors = ButtonDefaults.buttonColors(containerColor = CategoryEcash),
                shape = RoundedCornerShape(12.dp)
            ) {
                if (isProcessing) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(24.dp),
                        color = Color.White,
                        strokeWidth = 2.dp
                    )
                } else {
                    Icon(Icons.Filled.Send, "Process")
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Process Cash-In", fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}
