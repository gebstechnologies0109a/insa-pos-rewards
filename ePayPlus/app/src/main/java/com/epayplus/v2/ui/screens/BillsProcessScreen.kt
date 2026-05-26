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
import com.epayplus.v2.ui.viewmodel.BillsViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BillsProcessScreen(
    navController: NavController,
    billerCode: String,
    viewModel: BillsViewModel = hiltViewModel()
) {
    var accountNumber by remember { mutableStateOf("") }
    var amount by remember { mutableStateOf("") }
    var isProcessing by remember { mutableStateOf(false) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Pay Bill", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.Filled.ArrowBack, "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = CategoryBills,
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
            Text("Biller: $billerCode", fontWeight = FontWeight.SemiBold)
            Spacer(modifier = Modifier.height(24.dp))

            OutlinedTextField(
                value = accountNumber,
                onValueChange = { accountNumber = it },
                label = { Text("Account Number") },
                leadingIcon = { Icon(Icons.Filled.AccountBox, "Account") },
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
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(12.dp),
                singleLine = true
            )

            Spacer(modifier = Modifier.height(32.dp))

            Button(
                onClick = {
                    isProcessing = true
                    viewModel.processBillPayment(billerCode, accountNumber, amount.toDoubleOrNull() ?: 0.0)
                },
                modifier = Modifier
                    .fillMaxWidth()
                    .height(52.dp),
                enabled = accountNumber.isNotEmpty() && amount.isNotEmpty() && !isProcessing,
                colors = ButtonDefaults.buttonColors(containerColor = CategoryBills),
                shape = RoundedCornerShape(12.dp)
            ) {
                if (isProcessing) {
                    CircularProgressIndicator(
                        modifier = Modifier.size(24.dp),
                        color = Color.White,
                        strokeWidth = 2.dp
                    )
                } else {
                    Icon(Icons.Filled.Payment, "Pay")
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Pay Now", fontWeight = FontWeight.SemiBold)
                }
            }
        }
    }
}
