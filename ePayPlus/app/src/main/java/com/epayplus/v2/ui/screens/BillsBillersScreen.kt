package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.ui.components.ProviderIcon
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.BillsViewModel
import java.net.URLEncoder

@Composable
fun BillsBillersScreen(
    navController: NavController,
    category: String,
    viewModel: BillsViewModel = hiltViewModel()
) {
    val billers by viewModel.getBillersByCategory(category).collectAsState(initial = emptyList())

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.verticalGradient(
                        colors = listOf(CategoryBills, CategoryBills.copy(alpha = 0.8f))
                    )
                )
                .padding(16.dp)
        ) {
            Row(verticalAlignment = Alignment.CenterVertically) {
                IconButton(onClick = { navController.popBackStack() }) {
                    Icon(Icons.Filled.ArrowBack, "Back", tint = Color.White)
                }
                Spacer(modifier = Modifier.width(8.dp))
                Column {
                    Text(category, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Color.White)
                    Text("Select biller", fontSize = 13.sp, color = Color.White.copy(alpha = 0.8f))
                }
            }
        }

        if (billers.isEmpty()) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Filled.Receipt, "No billers", modifier = Modifier.size(48.dp), tint = EPayMediumGray)
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("No billers in this category", color = EPayMediumGray)
                }
            }
        } else {
            LazyColumn(
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(billers) { biller ->
                    Card(
                        modifier = Modifier
                            .fillMaxWidth()
                            .clickable {
                                val encodedName = URLEncoder.encode(biller.providerName, "UTF-8")
                                navController.navigate(NavRoutes.BillsProcess.createRoute(biller.providerCode, encodedName))
                            },
                        shape = RoundedCornerShape(14.dp),
                        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp),
                        colors = CardDefaults.cardColors(containerColor = Color.White)
                    ) {
                        Row(
                            modifier = Modifier.fillMaxWidth().padding(16.dp),
                            verticalAlignment = Alignment.CenterVertically
                        ) {
                            ProviderIcon(
                                providerCode = biller.providerCode,
                                providerName = biller.providerName,
                                size = 44.dp,
                                fallbackIcon = Icons.Filled.Receipt,
                                fallbackTint = CategoryBills,
                                backgroundColor = CategoryBills.copy(alpha = 0.08f),
                                contentPadding = 4.dp
                            )
                            Spacer(modifier = Modifier.width(14.dp))
                            Column(modifier = Modifier.weight(1f)) {
                                Text(biller.providerName, fontWeight = FontWeight.SemiBold, fontSize = 15.sp)
                                Text(biller.description, fontSize = 12.sp, color = EPayMediumGray)
                            }
                            Icon(Icons.Filled.ChevronRight, "Go", tint = EPayMediumGray)
                        }
                    }
                }
            }
        }
    }
}
