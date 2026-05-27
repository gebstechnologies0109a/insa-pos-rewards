package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
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
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.ui.components.ProviderIconFromRes
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.nfc.rememberRfidTagReader
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.ProcessState
import com.epayplus.v2.ui.viewmodel.RfidViewModel
import java.net.URLDecoder

@Composable
fun RfidProcessScreen(
    navController: NavController,
    providerCode: String,
    providerName: String,
    viewModel: RfidViewModel = hiltViewModel()
) {
    val decodedName = try {
        URLDecoder.decode(providerName, "UTF-8")
    } catch (_: Exception) {
        providerName
    }
    val provider = viewModel.providerForCode(providerCode)
    val iconRes = provider?.iconRes
    val accountLabel = provider?.accountLabel ?: "RFID Account / Plate No."
    val accountHint = provider?.accountHint ?: "Enter account or tag number"

    var accountNumber by remember { mutableStateOf("") }
    var amount by remember { mutableStateOf("") }
    var showConfirmDialog by remember { mutableStateOf(false) }
    val processState by viewModel.processState.collectAsState()

    val nfc = rememberRfidTagReader { tagId ->
        accountNumber = tagId.replace(":", "").take(32)
    }

    LaunchedEffect(processState) {
        if (processState is ProcessState.Success) {
            val state = processState as ProcessState.Success
            navController.navigate(NavRoutes.TransactionResult.createRoute(state.transactionId, "RFID")) {
                popUpTo(NavRoutes.RfidProviders.route) { inclusive = false }
            }
            viewModel.resetProcessState()
        }
    }

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(brush = Brush.verticalGradient(listOf(CategoryRfid, CategoryRfid.copy(alpha = 0.8f))))
                .padding(16.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = { navController.popBackStack() }) {
                    Icon(Icons.Filled.ArrowBack, "Back", tint = Color.White)
                }
                Spacer(modifier = Modifier.width(8.dp))
                Column {
                    Text("RFID Reload", fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Color.White)
                    Text(decodedName, fontSize = 13.sp, color = Color.White.copy(alpha = 0.85f))
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
                colors = CardDefaults.cardColors(containerColor = CategoryRfid.copy(alpha = 0.08f))
            ) {
                Row(modifier = Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
                    if (iconRes != null) {
                        ProviderIconFromRes(
                            resId = iconRes,
                            contentDescription = decodedName,
                            size = 44.dp,
                            backgroundColor = CategoryRfid.copy(alpha = 0.15f)
                        )
                    } else {
                        Icon(Icons.Filled.Nfc, "Provider", tint = CategoryRfid, modifier = Modifier.size(44.dp))
                    }
                    Spacer(modifier = Modifier.width(12.dp))
                    Column {
                        Text(decodedName, fontWeight = FontWeight.SemiBold, fontSize = 16.sp)
                        Text("RFID Services", fontSize = 12.sp, color = EPayMediumGray)
                    }
                }
            }

            if (nfc.adapterPresent) {
                Spacer(modifier = Modifier.height(12.dp))
                Card(
                    modifier = Modifier.fillMaxWidth(),
                    shape = RoundedCornerShape(12.dp),
                    colors = CardDefaults.cardColors(
                        containerColor = if (nfc.canReadTags) CategoryRfid.copy(alpha = 0.1f)
                        else EPayLightGray.copy(alpha = 0.5f)
                    )
                ) {
                    Row(
                        modifier = Modifier.padding(12.dp),
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Icon(Icons.Filled.Nfc, null, tint = CategoryRfid, modifier = Modifier.size(22.dp))
                        Spacer(modifier = Modifier.width(10.dp))
                        Text(
                            if (nfc.canReadTags) "Tap RFID card to fill tag ID (optional)"
                            else "NFC available — enable it in settings to tap-read tags",
                            fontSize = 12.sp,
                            color = EPayDarkGray
                        )
                    }
                }
            }

            Spacer(modifier = Modifier.height(20.dp))

            OutlinedTextField(
                value = accountNumber,
                onValueChange = { accountNumber = it.take(32) },
                label = { Text(accountLabel) },
                placeholder = { Text(accountHint) },
                leadingIcon = { Icon(Icons.Filled.CreditCard, "Account", tint = CategoryRfid) },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Ascii),
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                singleLine = true,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = CategoryRfid,
                    focusedLabelColor = CategoryRfid
                )
            )

            Spacer(modifier = Modifier.height(16.dp))

            OutlinedTextField(
                value = amount,
                onValueChange = { amount = it.filter { c -> c.isDigit() || c == '.' } },
                label = { Text("Reload Amount") },
                leadingIcon = { Text("  ₱", fontWeight = FontWeight.Bold, color = CategoryRfid) },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Number),
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(14.dp),
                singleLine = true,
                colors = OutlinedTextFieldDefaults.colors(
                    focusedBorderColor = CategoryRfid,
                    focusedLabelColor = CategoryRfid
                )
            )

            Spacer(modifier = Modifier.height(12.dp))

            Row(modifier = Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                listOf("200", "500", "1000", "2000").forEach { quickAmount ->
                    OutlinedButton(
                        onClick = { amount = quickAmount },
                        modifier = Modifier.weight(1f),
                        shape = RoundedCornerShape(10.dp),
                        colors = ButtonDefaults.outlinedButtonColors(contentColor = CategoryRfid)
                    ) {
                        Text("₱$quickAmount", fontSize = 11.sp)
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
                enabled = accountNumber.length >= 4 && amount.isNotEmpty() && processState !is ProcessState.Processing,
                colors = ButtonDefaults.buttonColors(containerColor = CategoryRfid),
                shape = RoundedCornerShape(14.dp)
            ) {
                if (processState is ProcessState.Processing) {
                    CircularProgressIndicator(modifier = Modifier.size(24.dp), color = Color.White, strokeWidth = 2.5.dp)
                } else {
                    Icon(Icons.Filled.Send, "Process")
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("Process RFID Reload", fontWeight = FontWeight.SemiBold, fontSize = 16.sp)
                }
            }
        }
    }

    if (showConfirmDialog) {
        AlertDialog(
            onDismissRequest = { showConfirmDialog = false },
            icon = { Icon(Icons.Filled.Nfc, "RFID", tint = CategoryRfid) },
            title = { Text("Confirm RFID Reload", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    ConfirmRow("Provider", decodedName)
                    ConfirmRow("Account / Tag", accountNumber)
                    ConfirmRow("Amount", "₱${String.format("%,.2f", amount.toDoubleOrNull() ?: 0.0)}")
                    HorizontalDivider()
                    ConfirmRow("Total", "₱${String.format("%,.2f", amount.toDoubleOrNull() ?: 0.0)}")
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        showConfirmDialog = false
                        viewModel.processReload(
                            providerCode = providerCode,
                            providerName = decodedName,
                            accountNumber = accountNumber,
                            amount = amount.toDoubleOrNull() ?: 0.0
                        )
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = CategoryRfid),
                    shape = RoundedCornerShape(12.dp)
                ) { Text("Confirm Reload") }
            },
            dismissButton = { TextButton(onClick = { showConfirmDialog = false }) { Text("Cancel") } },
            shape = RoundedCornerShape(20.dp)
        )
    }
}
