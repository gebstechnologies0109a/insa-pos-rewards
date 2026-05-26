package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.BillsViewModel
import com.epayplus.v2.ui.viewmodel.ProcessState
import java.net.URLDecoder

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun BillsProcessScreen(
    navController: NavController,
    billerCode: String,
    billerName: String,
    viewModel: BillsViewModel = hiltViewModel()
) {
    val decodedName = try { URLDecoder.decode(billerName, "UTF-8") } catch (_: Exception) { billerName }
    var accountNumber by remember { mutableStateOf("") }
    var amount by remember { mutableStateOf("") }
    var showConfirmDialog by remember { mutableStateOf(false) }
    val processState by viewModel.processState.collectAsState()

    LaunchedEffect(processState) {
        if (processState is ProcessState.Success) {
            val state = processState as ProcessState.Success
            navController.navigate(NavRoutes.TransactionResult.createRoute(state.transactionId, "BILLS")) {
                popUpTo(NavRoutes.BillsCategories.route) { inclusive = false }
            }
            viewModel.resetProcessState()
        }
    }

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        Box(
            modifier = Modifier.fillMaxWidth()
                .background(brush = Brush.verticalGradient(listOf(CategoryBills, CategoryBills.copy(alpha = 0.8f))))
                .padding(16.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = { navController.popBackStack() }) {
                    Icon(Icons.Filled.ArrowBack, "Back", tint = Color.White)
                }
                Spacer(modifier = Modifier.width(8.dp))
                Column {
                    Text("Pay Bill", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Color.White)
                    Text(decodedName, fontSize = 13.sp, color = Color.White.copy(alpha = 0.8f))
                }
            }
        }

        Column(
            modifier = Modifier
                .fillMaxSize()
                .verticalScroll(rememberScrollState())
                .padding(20.dp)
        ) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = CategoryBills.copy(alpha = 0.08f))
            ) {
                Row(modifier = Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                    Surface(modifier = Modifier.size(44.dp), shape = CircleShape, color = CategoryBills.copy(alpha = 0.15f)) {
                        Box(contentAlignment = Alignment.Center) {
                            Icon(Icons.Filled.Receipt, "Biller", tint = CategoryBills, modifier = Modifier.size(24.dp))
                        }
                    }
                    Spacer(modifier = Modifier.width(12.dp))
                    Column {
                        Text(decodedName, fontWeight = FontWeight.SemiBold, fontSize = 16.sp)
                        Text(billerCode, fontSize = 12.sp, color = EPayMediumGray)
                    }
                }
            }

            Spacer(modifier = Modifier.height(20.dp))

            OutlinedTextField(
                value = accountNumber,
                onValueChange = { accountNumber = it },
                label = { Text("Account Number") },
                leadingIcon = { Icon(Icons.Filled.Numbers, "Account", tint = CategoryBills) },
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                singleLine = true,
                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = CategoryBills, focusedLabelColor = CategoryBills)
            )

            Spacer(modifier = Modifier.height(16.dp))

            OutlinedTextField(
                value = amount,
                onValueChange = { amount = it.filter { c -> c.isDigit() || c == '.' } },
                label = { Text("Amount") },
                leadingIcon = { Text("  ₱", fontWeight = FontWeight.Bold, color = CategoryBills) },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Decimal),
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                singleLine = true,
                colors = OutlinedTextFieldDefaults.colors(focusedBorderColor = CategoryBills, focusedLabelColor = CategoryBills)
            )

            Spacer(modifier = Modifier.height(12.dp))

            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("100", "500", "1000", "2000").forEach { quickAmount ->
                    OutlinedButton(
                        onClick = { amount = quickAmount },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = CategoryBills)
                    ) {
                        Text("₱$quickAmount", fontSize = 12.sp)
                    }
                }
            }

            if (processState is ProcessState.Failed) {
                Spacer(modifier = Modifier.height(16.dp))
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(10.dp),
                    colors = CardDefaults.cardColors(containerColor = StatusError.copy(alpha = 0.1f))
                ) {
                    Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
                        Icon(Icons.Filled.ErrorOutline, "Error", tint = StatusError, modifier = Modifier.size(18.dp))
                        Spacer(modifier = Modifier.width(8.dp))
                        Text((processState as ProcessState.Failed).message, fontSize = 13.sp, color = StatusError)
                    }
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

            Button(
                onClick = { showConfirmDialog = true },
                modifier = Modifier.fillMaxWidth().height(52.dp),
                enabled = accountNumber.isNotEmpty() && amount.isNotEmpty() && processState !is ProcessState.Processing,
                colors = ButtonDefaults.buttonColors(containerColor = CategoryBills),
                shape = RoundedCornerShape(14.dp)
            ) {
                if (processState is ProcessState.Processing) {
                    CircularProgressIndicator(modifier = Modifier.size(24.dp), color = Color.White, strokeWidth = 2.5.dp)
                } else {
                    Icon(Icons.Filled.Payment, "Pay")
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Pay Now", fontWeight = FontWeight.SemiBold, fontSize = 16.sp)
                }
            }
        }
    }

    if (showConfirmDialog) {
        AlertDialog(
            onDismissRequest = { showConfirmDialog = false },
            icon = { Icon(Icons.Filled.Receipt, "Bill", tint = CategoryBills) },
            title = { Text("Confirm Payment", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    ConfirmRow("Biller", decodedName)
                    ConfirmRow("Account #", accountNumber)
                    ConfirmRow("Amount", "₱${String.format("%,.2f", amount.toDoubleOrNull() ?: 0.0)}")
                    Divider()
                    ConfirmRow("Total", "₱${String.format("%,.2f", amount.toDoubleOrNull() ?: 0.0)}")
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        showConfirmDialog = false
                        viewModel.processBillPayment(billerCode, decodedName, "${billerCode}_PAY", accountNumber, amount.toDoubleOrNull() ?: 0.0)
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = CategoryBills),
                    shape = RoundedCornerShape(12.dp)
                ) { Text("Confirm Payment") }
            },
            dismissButton = { TextButton(onClick = { showConfirmDialog = false }) { Text("Cancel") } },
            shape = RoundedCornerShape(20.dp)
        )
    }
}
