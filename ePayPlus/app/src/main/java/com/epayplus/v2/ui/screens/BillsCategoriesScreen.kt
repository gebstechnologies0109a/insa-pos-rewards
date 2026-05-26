package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
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
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.BillsViewModel

@Composable
fun BillsCategoriesScreen(
    navController: NavController,
    viewModel: BillsViewModel = hiltViewModel()
) {
    val categories by viewModel.categories.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()

    val categoryConfig = mapOf(
        "Electricity" to Pair(Icons.Filled.ElectricBolt, Color(0xFFFFA726)),
        "Water" to Pair(Icons.Filled.WaterDrop, Color(0xFF42A5F5)),
        "Internet/Cable" to Pair(Icons.Filled.Router, Color(0xFF7E57C2)),
        "Telecommunications" to Pair(Icons.Filled.Phone, Color(0xFF66BB6A)),
        "Government" to Pair(Icons.Filled.AccountBalance, Color(0xFF5C6BC0)),
        "Insurance" to Pair(Icons.Filled.Security, Color(0xFFEF5350)),
        "Loans" to Pair(Icons.Filled.CreditScore, Color(0xFF26A69A)),
        "Credit Cards" to Pair(Icons.Filled.CreditCard, Color(0xFFEC407A)),
        "Real Estate" to Pair(Icons.Filled.Home, Color(0xFF8D6E63)),
        "Schools" to Pair(Icons.Filled.School, Color(0xFF29B6F6)),
        "Others" to Pair(Icons.Filled.MoreHoriz, Color(0xFF78909C))
    )

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.verticalGradient(
                        colors = listOf(CategoryBills, CategoryBills.copy(alpha = 0.8f))
                    )
                )
                .padding(20.dp)
        ) {
            Column {
                Text("Bills Payment", fontSize = 24.sp, fontWeight = FontWeight.Bold, color = Color.White)
                Text("Pay all your bills in one place", fontSize = 13.sp, color = Color.White.copy(alpha = 0.8f))
            }
        }

        if (isLoading) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                CircularProgressIndicator(color = CategoryBills)
            }
        } else if (categories.isEmpty()) {
            Box(modifier = Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                Column(horizontalAlignment = Alignment.CenterHorizontally) {
                    Icon(Icons.Filled.Receipt, "No categories", modifier = Modifier.size(48.dp), tint = EPayMediumGray)
                    Spacer(modifier = Modifier.height(8.dp))
                    Text("No bill categories available", color = EPayMediumGray)
                }
            }
        } else {
            Column(modifier = Modifier.padding(16.dp)) {
                Text("Select Category", style = MaterialTheme.typography.titleSmall, fontWeight = FontWeight.SemiBold, color = EPayMediumGray)
                Spacer(modifier = Modifier.height(12.dp))

                LazyVerticalGrid(
                    columns = GridCells.Fixed(3),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    items(categories) { category ->
                        val (icon, color) = categoryConfig[category] ?: Pair(Icons.Filled.MoreHoriz, Color.Gray)
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .aspectRatio(1f)
                                .clickable { navController.navigate(NavRoutes.BillsBillers.createRoute(category)) },
                            shape = RoundedCornerShape(16.dp),
                            elevation = CardDefaults.cardElevation(defaultElevation = 2.dp),
                            colors = CardDefaults.cardColors(containerColor = Color.White)
                        ) {
                            Column(
                                modifier = Modifier.fillMaxSize().padding(8.dp),
                                horizontalAlignment = Alignment.CenterHorizontally,
                                verticalArrangement = Arrangement.Center
                            ) {
                                Surface(
                                    modifier = Modifier.size(44.dp),
                                    shape = CircleShape,
                                    color = color.copy(alpha = 0.12f)
                                ) {
                                    Box(contentAlignment = Alignment.Center) {
                                        Icon(icon, category, tint = color, modifier = Modifier.size(24.dp))
                                    }
                                }
                                Spacer(modifier = Modifier.height(6.dp))
                                Text(
                                    category,
                                    fontWeight = FontWeight.Medium,
                                    fontSize = 11.sp,
                                    textAlign = TextAlign.Center,
                                    maxLines = 2,
                                    lineHeight = 14.sp
                                )
                            }
                        }
                    }
                }
            }
        }
    }
}
