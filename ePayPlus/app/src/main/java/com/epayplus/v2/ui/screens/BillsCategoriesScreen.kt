package com.epayplus.v2.ui.screens

import androidx.compose.foundation.Image
import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Receipt
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.ui.components.BillsCategoryIcons
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.layout.providerGridColumns
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.BillsViewModel

@Composable
fun BillsCategoriesScreen(
    navController: NavController,
    viewModel: BillsViewModel = hiltViewModel()
) {
    val categories by viewModel.categories.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()

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
                    columns = providerGridColumns(minSize = 180.dp),
                    horizontalArrangement = Arrangement.spacedBy(12.dp),
                    verticalArrangement = Arrangement.spacedBy(12.dp),
                    modifier = Modifier.fillMaxSize()
                ) {
                    items(categories) { category ->
                        val iconRes = BillsCategoryIcons.categoryIconRes(category, category)
                        val color = BillsCategoryIcons.categoryColor(category, category)
                        Card(
                            modifier = Modifier
                                .fillMaxWidth()
                                .aspectRatio(1.1f)
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
                                        Image(
                                            painter = painterResource(iconRes),
                                            contentDescription = category,
                                            modifier = Modifier.size(28.dp),
                                            contentScale = ContentScale.Fit
                                        )
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
