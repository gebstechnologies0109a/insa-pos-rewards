package com.epayplus.v2.ui.screens

import androidx.compose.animation.core.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.ArrowBack
import androidx.compose.material.icons.filled.MonetizationOn
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp

@Composable
fun KioskPaymentScreen(
    requiredAmount: Double = 0.0,
    insertedAmount: Double = 0.0,
    onBack: () -> Unit = {},
    onConfirm: () -> Unit = {}
) {
    val remaining = (requiredAmount - insertedAmount).coerceAtLeast(0.0)
    val isEnough = insertedAmount >= requiredAmount

    val pulseAlpha by rememberInfiniteTransition(label = "pulse").animateFloat(
        initialValue = 0.4f,
        targetValue = 1f,
        animationSpec = infiniteRepeatable(
            animation = tween(800, easing = EaseInOutCubic),
            repeatMode = RepeatMode.Reverse
        ),
        label = "pulse_alpha"
    )

    Column(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background)
            .padding(24.dp),
        horizontalAlignment = Alignment.CenterHorizontally,
        verticalArrangement = Arrangement.SpaceBetween
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
            Text("Insert Payment", fontSize = 24.sp, fontWeight = FontWeight.Bold)
            Spacer(modifier = Modifier.weight(1f))
            Spacer(modifier = Modifier.size(48.dp))
        }

        Spacer(modifier = Modifier.height(32.dp))

        // Amount info
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(containerColor = Color(0xFFF0F7FF)),
            shape = RoundedCornerShape(20.dp)
        ) {
            Column(
                modifier = Modifier.padding(32.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text("Amount Required", color = Color.Gray, fontSize = 16.sp)
                Text(
                    "₱${String.format("%.2f", requiredAmount)}",
                    fontSize = 42.sp,
                    fontWeight = FontWeight.Bold,
                    color = MaterialTheme.colorScheme.primary
                )
            }
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Insert coins animation
        Column(
            horizontalAlignment = Alignment.CenterHorizontally,
            modifier = Modifier.alpha(if (isEnough) 0.5f else pulseAlpha)
        ) {
            Icon(
                Icons.Default.MonetizationOn,
                contentDescription = null,
                modifier = Modifier.size(80.dp),
                tint = if (isEnough) Color(0xFF4CAF50) else Color(0xFFFFA726)
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                if (isEnough) "Payment Complete!" else "Insert coins or bills...",
                fontSize = 20.sp,
                fontWeight = FontWeight.Medium,
                color = if (isEnough) Color(0xFF4CAF50) else Color.Gray
            )
        }

        Spacer(modifier = Modifier.height(24.dp))

        // Inserted amount
        Card(
            modifier = Modifier.fillMaxWidth(),
            colors = CardDefaults.cardColors(
                containerColor = if (isEnough) Color(0xFFE8F5E9) else Color(0xFFFFF8E1)
            ),
            shape = RoundedCornerShape(20.dp)
        ) {
            Column(
                modifier = Modifier.padding(24.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text("Amount Inserted", color = Color.Gray, fontSize = 14.sp)
                Text(
                    "₱${String.format("%.2f", insertedAmount)}",
                    fontSize = 36.sp,
                    fontWeight = FontWeight.Bold,
                    color = if (isEnough) Color(0xFF4CAF50) else Color(0xFFF57C00)
                )
                if (!isEnough) {
                    Text(
                        "Remaining: ₱${String.format("%.2f", remaining)}",
                        fontSize = 16.sp,
                        color = Color.Gray
                    )
                }
            }
        }

        Spacer(modifier = Modifier.weight(1f))

        // Confirm button
        Button(
            onClick = onConfirm,
            modifier = Modifier
                .fillMaxWidth()
                .height(60.dp),
            enabled = isEnough,
            shape = RoundedCornerShape(16.dp),
            colors = ButtonDefaults.buttonColors(
                containerColor = Color(0xFF4CAF50)
            )
        ) {
            Text(
                if (isEnough) "PROCESS PAYMENT" else "WAITING FOR PAYMENT...",
                fontSize = 20.sp,
                fontWeight = FontWeight.Bold
            )
        }
    }
}
