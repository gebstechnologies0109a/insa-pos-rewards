package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Cancel
import androidx.compose.material.icons.filled.Print
import androidx.compose.material.icons.filled.Home
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@Composable
fun KioskResultScreen(
    isSuccess: Boolean = true,
    referenceNumber: String = "",
    amount: String = "",
    targetNumber: String = "",
    message: String = "",
    onPrintReceipt: () -> Unit = {},
    onDone: () -> Unit = {}
) {
    val backgroundColor = if (isSuccess) Color(0xFFE8F5E9) else Color(0xFFFFEBEE)
    val accentColor = if (isSuccess) Color(0xFF4CAF50) else Color(0xFFF44336)

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(32.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.SpaceBetween
    ) {
        Spacer(modifier = Modifier.height(48.dp))

        // Status icon
        Box(
            modifier = Modifier
                .size(120.dp)
                .background(backgroundColor, CircleShape),
            contentAlignment = Alignment.Center
        ) {
            Icon(
                if (isSuccess) Icons.Default.CheckCircle else Icons.Default.Cancel,
                contentDescription = null,
                modifier = Modifier.size(80.dp),
                tint = accentColor
            )
        }

        Spacer(modifier = Modifier.height(24.dp))

        Text(
            if (isSuccess) "Transaction Successful!" else "Transaction Failed",
            fontSize = 28.sp,
            fontWeight = FontWeight.Bold,
            color = accentColor
        )

        if (message.isNotEmpty()) {
            Spacer(modifier = Modifier.height(8.dp))
            Text(message, fontSize = 16.sp, color = Color.Gray, textAlign = TextAlign.Center)
        }

        Spacer(modifier = Modifier.height(32.dp))

        // Transaction details
        if (isSuccess) {
            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = Color(0xFFF5F5F5))
            ) {
                Column(modifier = Modifier.padding(24.dp)) {
                    DetailRow("Number", targetNumber)
                    DetailRow("Amount", "₱$amount")
                    if (referenceNumber.isNotEmpty()) {
                        DetailRow("Reference", referenceNumber)
                    }
                }
            }
        }

        Spacer(modifier = Modifier.weight(1f))

        // Action buttons
        Row(
            modifier = Modifier.fillMaxWidth(),
            horizontalArrangement = Arrangement.spacedBy(16.dp)
        ) {
            if (isSuccess) {
                OutlinedButton(
                    onClick = onPrintReceipt,
                    modifier = Modifier
                        .weight(1f)
                        .height(60.dp),
                    shape = RoundedCornerShape(16.dp)
                ) {
                    Icon(Icons.Default.Print, "Print", modifier = Modifier.size(24.dp))
                    Spacer(modifier = Modifier.width(8.dp))
                    Text("PRINT", fontSize = 18.sp, fontWeight = FontWeight.Bold)
                }
            }

            Button(
                onClick = onDone,
                modifier = Modifier
                    .weight(1f)
                    .height(60.dp),
                shape = RoundedCornerShape(16.dp),
                colors = ButtonDefaults.buttonColors(containerColor = accentColor)
            ) {
                Icon(Icons.Default.Home, "Home", modifier = Modifier.size(24.dp))
                Spacer(modifier = Modifier.width(8.dp))
                Text("DONE", fontSize = 18.sp, fontWeight = FontWeight.Bold)
            }
        }
    }
}

@Composable
private fun DetailRow(label: String, value: String) {
    Row(
        modifier = Modifier
            .fillMaxWidth()
            .padding(vertical = 6.dp),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(label, color = Color.Gray, fontSize = 16.sp)
        Text(value, fontWeight = FontWeight.Medium, fontSize = 16.sp)
    }
}
