package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.Backspace
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@Composable
fun KioskPhoneInputScreen(
    title: String = "Enter Phone Number",
    onBack: () -> Unit = {},
    onConfirm: (String) -> Unit = {}
) {
    var phoneNumber by remember { mutableStateOf("") }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        // Header
        Row(
            modifier = Modifier.fillMaxWidth(),
            verticalAlignment = Alignment.CenterVertically
        ) {
            IconButton(onClick = onBack) {
                Icon(Icons.Default.ArrowBack, "Back", modifier = Modifier.size(32.dp))
            }
            Spacer(modifier = Modifier.weight(1f))
            Text(title, fontSize = 24.sp, fontWeight = FontWeight.Bold)
            Spacer(modifier = Modifier.weight(1f))
            Spacer(modifier = Modifier.size(48.dp))
        }

        Spacer(modifier = Modifier.height(32.dp))

        // Phone number display
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(horizontal = 16.dp),
            colors = CardDefaults.cardColors(containerColor = Color(0xFFF5F5F5))
        ) {
            Text(
                text = if (phoneNumber.isEmpty()) "09XX XXX XXXX" else formatPhone(phoneNumber),
                fontSize = 36.sp,
                fontWeight = FontWeight.Bold,
                textAlign = TextAlign.Center,
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(24.dp),
                color = if (phoneNumber.isEmpty()) Color.Gray else Color.Black
            )
        }

        Spacer(modifier = Modifier.height(32.dp))

        // Keypad
        val keys = listOf(
            listOf("1", "2", "3"),
            listOf("4", "5", "6"),
            listOf("7", "8", "9"),
            listOf("C", "0", "⌫")
        )

        keys.forEach { row ->
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceEvenly
            ) {
                row.forEach { key ->
                    Box(
                        modifier = Modifier
                            .size(80.dp)
                            .padding(4.dp)
                            .clip(CircleShape)
                            .background(
                                when (key) {
                                    "C" -> Color(0xFFFF5252)
                                    "⌫" -> Color(0xFFFFA726)
                                    else -> MaterialTheme.colorScheme.primaryContainer
                                }
                            )
                            .clickable {
                                when (key) {
                                    "C" -> phoneNumber = ""
                                    "⌫" -> if (phoneNumber.isNotEmpty()) phoneNumber = phoneNumber.dropLast(1)
                                    else -> if (phoneNumber.length < 11) phoneNumber += key
                                }
                            },
                        contentAlignment = Alignment.Center
                    ) {
                        if (key == "⌫") {
                            Icon(Icons.Default.Backspace, "Delete", tint = Color.White, modifier = Modifier.size(28.dp))
                        } else {
                            Text(
                                key,
                                fontSize = 28.sp,
                                fontWeight = FontWeight.Bold,
                                color = if (key == "C") Color.White else Color.Black
                            )
                        }
                    }
                }
            }
            Spacer(modifier = Modifier.height(8.dp))
        }

        Spacer(modifier = Modifier.weight(1f))

        // Confirm button
        Button(
            onClick = { onConfirm(phoneNumber) },
            modifier = Modifier
                .fillMaxWidth()
                .height(60.dp)
                .padding(horizontal = 16.dp),
            enabled = phoneNumber.length == 11,
            shape = RoundedCornerShape(16.dp)
        ) {
            Text("CONTINUE", fontSize = 20.sp, fontWeight = FontWeight.Bold)
        }
    }
}

private fun formatPhone(number: String): String {
    return when {
        number.length <= 4 -> number
        number.length <= 7 -> "${number.substring(0, 4)} ${number.substring(4)}"
        else -> "${number.substring(0, 4)} ${number.substring(4, 7)} ${number.substring(7)}"
    }
}
