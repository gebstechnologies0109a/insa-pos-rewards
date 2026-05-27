package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.R
import com.epayplus.v2.domain.model.RetailProductDto
import com.epayplus.v2.ui.navigation.NavRoutes
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.PosViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun PosModeScreen(
    navController: NavController,
    viewModel: PosViewModel = hiltViewModel()
) {
    val uiState by viewModel.uiState.collectAsState()
    var selectedTab by remember { mutableIntStateOf(0) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("POS Mode", fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, "Back")
                    }
                },
                actions = {
                    if (uiState.cartCount > 0) {
                        BadgedBox(
                            badge = { Badge { Text("${uiState.cartCount}") } }
                        ) {
                            IconButton(onClick = { navController.navigate(NavRoutes.PosCart.route) }) {
                                Icon(Icons.Filled.ShoppingCart, "Cart")
                            }
                        }
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = EPayGreen,
                    titleContentColor = Color.White,
                    navigationIconContentColor = Color.White,
                    actionIconContentColor = Color.White
                )
            )
        }
    ) { padding ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(padding)
        ) {
            TabRow(
                selectedTabIndex = selectedTab,
                containerColor = MaterialTheme.colorScheme.surface
            ) {
                Tab(selected = selectedTab == 0, onClick = { selectedTab = 0 }, text = { Text("ePay Services") })
                Tab(selected = selectedTab == 1, onClick = { selectedTab = 1 }, text = { Text("Shop Items") })
            }

            when {
                uiState.isLoading -> {
                    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.Center) {
                        CircularProgressIndicator(color = EPayGreen)
                    }
                }
                selectedTab == 0 -> PosServicesTab(navController, uiState.services)
                else -> PosShopTab(
                    products = uiState.retailProducts,
                    onAdd = viewModel::addToCart,
                    onRefresh = viewModel::loadCatalog,
                    error = uiState.error
                )
            }
        }
    }
}

@Composable
private fun PosServicesTab(navController: NavController, services: List<com.epayplus.v2.domain.model.PosServiceItem>) {
    val items = services.ifEmpty {
        listOf(
            com.epayplus.v2.domain.model.PosServiceItem("eload", "E-Load", "eload"),
            com.epayplus.v2.domain.model.PosServiceItem("bills", "Bills", "bills"),
            com.epayplus.v2.domain.model.PosServiceItem("ecash", "Cash-in", "ecash"),
            com.epayplus.v2.domain.model.PosServiceItem("rfid", "RFID", "rfid"),
        )
    }

    LazyVerticalGrid(
        columns = GridCells.Fixed(2),
        contentPadding = PaddingValues(16.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier.fillMaxSize()
    ) {
        items(items) { service ->
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable {
                        when (service.route) {
                            "eload" -> navController.navigate(NavRoutes.ELoadProviders.route)
                            "bills" -> navController.navigate(NavRoutes.BillsCategories.route)
                            "ecash" -> navController.navigate(NavRoutes.ECashProviders.route)
                            "rfid" -> navController.navigate(NavRoutes.RfidProviders.route)
                        }
                    },
                shape = RoundedCornerShape(14.dp),
                colors = CardDefaults.cardColors(containerColor = EPayGreenSurface)
            ) {
                Column(
                    modifier = Modifier.padding(20.dp),
                    horizontalAlignment = Alignment.CenterHorizontally
                ) {
                    val iconRes = when (service.route) {
                        "eload" -> R.drawable.ic_quick_eload
                        "bills" -> R.drawable.ic_quick_bills
                        "ecash" -> R.drawable.ic_quick_cashin
                        "rfid" -> R.drawable.ic_quick_rfid
                        else -> R.drawable.ic_quick_pos
                    }
                    Icon(
                        painter = painterResource(iconRes),
                        contentDescription = service.label,
                        tint = EPayGreen,
                        modifier = Modifier.size(40.dp)
                    )
                    Spacer(Modifier.height(8.dp))
                    Text(service.label, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
                }
            }
        }
    }
}

@Composable
private fun PosShopTab(
    products: List<RetailProductDto>,
    onAdd: (RetailProductDto) -> Unit,
    onRefresh: () -> Unit,
    error: String?
) {
    if (products.isEmpty()) {
        Column(
            modifier = Modifier.fillMaxSize().padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Text("No shop items in stock", color = EPayMediumGray)
            Spacer(Modifier.height(8.dp))
            TextButton(onClick = onRefresh) { Text("Refresh", color = EPayGreen) }
            error?.let { Text(it, color = MaterialTheme.colorScheme.error, fontSize = 12.sp) }
        }
        return
    }

    LazyVerticalGrid(
        columns = GridCells.Fixed(2),
        contentPadding = PaddingValues(16.dp),
        horizontalArrangement = Arrangement.spacedBy(12.dp),
        verticalArrangement = Arrangement.spacedBy(12.dp),
        modifier = Modifier.fillMaxSize()
    ) {
        items(products, key = { it.id }) { product ->
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .clickable { onAdd(product) },
                shape = RoundedCornerShape(14.dp)
            ) {
                Column(modifier = Modifier.padding(12.dp)) {
                    Text(
                        product.name,
                        fontWeight = FontWeight.SemiBold,
                        maxLines = 2,
                        overflow = TextOverflow.Ellipsis
                    )
                    product.category?.let {
                        Text(it, fontSize = 11.sp, color = EPayMediumGray)
                    }
                    Spacer(Modifier.height(6.dp))
                    Text("₱${"%.2f".format(product.price)}", color = EPayGreen, fontWeight = FontWeight.Bold)
                    Text("Stock: ${product.stock}", fontSize = 11.sp, color = EPayMediumGray)
                }
            }
        }
    }
}
