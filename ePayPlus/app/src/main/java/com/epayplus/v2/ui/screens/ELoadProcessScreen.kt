package com.epayplus.v2.ui.screens

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.ELoadViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ELoadProcessScreen(
    navController: NavController,
    productId: Long,
    phoneNumber: String,
    viewModel: ELoadViewModel = hiltViewModel()
) {
    val processState by viewModel.processState.collectAsState()

    LaunchedEffect(productId, phoneNumber) {
        viewModel.processEload(productId, phoneNumber)
    }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Processing", fontWeight = FontWeight.Bold) },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = EPayGreen,
                    titleContentColor = Color.White
                )
            )
        }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            when (processState) {
                is ProcessState.Processing -> {
                    CircularProgressIndicator(
                        modifier = Modifier.size(64.dp),
                        color = EPayGreen,
                        strokeWidth = 4.dp
                    )
                    Spacer(modifier = Modifier.height(24.dp))
                    Text(
                        "Processing Transaction...",
                        style = MaterialTheme.typography.titleMedium,
                        fontWeight = FontWeight.SemiBold
                    )
                    Text(
                        "Please wait while we process your e-load request.",
                        style = MaterialTheme.typography.bodyMedium,
                        color = Color.Gray,
                        textAlign = TextAlign.Center
                    )
                }
                is ProcessState.Success -> {
                    val state = processState as ProcessState.Success
                    Icon(
                        Icons.Filled.CheckCircle,
                        "Success",
                        tint = StatusSuccess,
                        modifier = Modifier.size(72.dp)
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    Text("Transaction Successful!", fontWeight = FontWeight.Bold, fontSize = 20.sp)
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("Ref: ${state.referenceNumber}", color = Color.Gray)
                    Spacer(modifier = Modifier.height(32.dp))

                    Button(
                        onClick = {
                            navController.navigate(NavRoutes.Home.route) {
                                popUpTo(NavRoutes.Home.route) { inclusive = true }
                            }
                        },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = EPayGreen),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Text("Done", modifier = Modifier.padding(vertical = 4.dp))
                    }
                }
                is ProcessState.Failed -> {
                    val state = processState as ProcessState.Failed
                    Icon(
                        Icons.Filled.Error,
                        "Failed",
                        tint = StatusError,
                        modifier = Modifier.size(72.dp)
                    )
                    Spacer(modifier = Modifier.height(16.dp))
                    Text("Transaction Failed", fontWeight = FontWeight.Bold, fontSize = 20.sp)
                    Spacer(modifier = Modifier.height(8.dp))
                    Text(state.message, color = Color.Gray, textAlign = TextAlign.Center)
                    Spacer(modifier = Modifier.height(32.dp))

                    Button(
                        onClick = { navController.popBackStack() },
                        modifier = Modifier.fillMaxWidth(),
                        colors = ButtonDefaults.buttonColors(containerColor = EPayGreen),
                        shape = RoundedCornerShape(12.dp)
                    ) {
                        Text("Try Again", modifier = Modifier.padding(vertical = 4.dp))
                    }
                }
                else -> {}
            }
        }
    }
}

sealed class ProcessState {
    object Idle : ProcessState()
    object Processing : ProcessState()
    data class Success(val referenceNumber: String, val transactionId: Long) : ProcessState()
    data class Failed(val message: String) : ProcessState()
}
