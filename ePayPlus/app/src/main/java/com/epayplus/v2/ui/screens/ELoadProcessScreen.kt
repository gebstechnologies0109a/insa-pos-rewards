package com.epayplus.v2.ui.screens

import androidx.compose.animation.*
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
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
import com.epayplus.v2.ui.viewmodel.ProcessState

@Composable
fun ELoadProcessScreen(
    navController: NavController,
    providerCode: String,
    productId: Long,
    phoneNumber: String,
    viewModel: ELoadViewModel = hiltViewModel()
) {
    val processState by viewModel.processState.collectAsState()

    LaunchedEffect(Unit) {
        if (processState is ProcessState.Idle) {
            viewModel.processEload(providerCode, productId, phoneNumber)
        }
    }

    Box(
        modifier = Modifier
            .fillMaxSize()
            .background(MaterialTheme.colorScheme.background),
        contentAlignment = Alignment.Center
    ) {
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .padding(24.dp),
            shape = RoundedCornerShape(24.dp),
            elevation = CardDefaults.cardElevation(defaultElevation = 4.dp)
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(32.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                when (val state = processState) {
                    is ProcessState.Processing -> {
                        CircularProgressIndicator(
                            modifier = Modifier.size(64.dp),
                            color = EPayGreen,
                            strokeWidth = 5.dp
                        )
                        Spacer(modifier = Modifier.height(24.dp))
                        Text(
                            "Processing E-Load",
                            fontSize = 20.sp,
                            fontWeight = FontWeight.Bold
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            "Loading $phoneNumber...\nPlease wait.",
                            textAlign = TextAlign.Center,
                            color = EPayMediumGray,
                            fontSize = 14.sp
                        )
                    }
                    is ProcessState.Success -> {
                        Surface(
                            modifier = Modifier.size(72.dp),
                            shape = CircleShape,
                            color = StatusSuccess.copy(alpha = 0.12f)
                        ) {
                            Box(contentAlignment = Alignment.Center) {
                                Icon(
                                    Icons.Filled.CheckCircle,
                                    "Success",
                                    tint = StatusSuccess,
                                    modifier = Modifier.size(48.dp)
                                )
                            }
                        }
                        Spacer(modifier = Modifier.height(20.dp))
                        Text(
                            "Load Successful!",
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold,
                            color = StatusSuccess
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            "Ref: ${state.referenceNumber}",
                            color = EPayMediumGray,
                            fontSize = 13.sp
                        )
                        Spacer(modifier = Modifier.height(24.dp))

                        Button(
                            onClick = {
                                navController.navigate(
                                    NavRoutes.TransactionResult.createRoute(state.transactionId, "ELOAD")
                                ) {
                                    popUpTo(NavRoutes.ELoadProviders.route) { inclusive = false }
                                }
                            },
                            modifier = Modifier.fillMaxWidth(),
                            colors = ButtonDefaults.buttonColors(containerColor = EPayGreen),
                            shape = RoundedCornerShape(14.dp)
                        ) {
                            Text("View Receipt", modifier = Modifier.padding(vertical = 4.dp), fontWeight = FontWeight.SemiBold)
                        }
                        Spacer(modifier = Modifier.height(8.dp))
                        OutlinedButton(
                            onClick = {
                                navController.navigate(NavRoutes.Home.route) {
                                    popUpTo(NavRoutes.Home.route) { inclusive = true }
                                }
                            },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(14.dp)
                        ) {
                            Text("Back to Home", modifier = Modifier.padding(vertical = 4.dp))
                        }
                    }
                    is ProcessState.Failed -> {
                        Surface(
                            modifier = Modifier.size(72.dp),
                            shape = CircleShape,
                            color = StatusError.copy(alpha = 0.12f)
                        ) {
                            Box(contentAlignment = Alignment.Center) {
                                Icon(
                                    Icons.Filled.Cancel,
                                    "Failed",
                                    tint = StatusError,
                                    modifier = Modifier.size(48.dp)
                                )
                            }
                        }
                        Spacer(modifier = Modifier.height(20.dp))
                        Text(
                            "Transaction Failed",
                            fontSize = 22.sp,
                            fontWeight = FontWeight.Bold,
                            color = StatusError
                        )
                        Spacer(modifier = Modifier.height(8.dp))
                        Text(
                            state.message,
                            textAlign = TextAlign.Center,
                            color = EPayMediumGray,
                            fontSize = 14.sp
                        )
                        Spacer(modifier = Modifier.height(24.dp))

                        Button(
                            onClick = { navController.popBackStack() },
                            modifier = Modifier.fillMaxWidth(),
                            colors = ButtonDefaults.buttonColors(containerColor = EPayGreen),
                            shape = RoundedCornerShape(14.dp)
                        ) {
                            Icon(Icons.Filled.Refresh, "Retry", modifier = Modifier.size(18.dp))
                            Spacer(modifier = Modifier.width(8.dp))
                            Text("Try Again", modifier = Modifier.padding(vertical = 4.dp), fontWeight = FontWeight.SemiBold)
                        }
                        Spacer(modifier = Modifier.height(8.dp))
                        OutlinedButton(
                            onClick = {
                                navController.navigate(NavRoutes.Home.route) {
                                    popUpTo(NavRoutes.Home.route) { inclusive = true }
                                }
                            },
                            modifier = Modifier.fillMaxWidth(),
                            shape = RoundedCornerShape(14.dp)
                        ) {
                            Text("Back to Home")
                        }
                    }
                    else -> {}
                }
            }
        }
    }
}
