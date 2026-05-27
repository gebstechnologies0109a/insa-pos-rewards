package com.epayplus.v2.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
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
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.data.local.entity.ProductEntity
import com.epayplus.v2.ui.navigation.NavRoutes
import androidx.compose.foundation.lazy.grid.LazyGridScope
import com.epayplus.v2.ui.layout.isLandscape
import com.epayplus.v2.ui.layout.productGridColumns
import com.epayplus.v2.ui.theme.*
import com.epayplus.v2.ui.viewmodel.ELoadViewModel

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ELoadProductsScreen(
    navController: NavController,
    providerCode: String,
    viewModel: ELoadViewModel = hiltViewModel()
) {
    val products by viewModel.getProductsByProvider(providerCode).collectAsState(initial = emptyList())
    val regularProducts = remember(products) {
        products.filter { it.category != "Promo" }
    }
    val promoProducts = remember(products) {
        products.filter { it.category == "Promo" }
    }
    var phoneNumber by remember { mutableStateOf("") }
    var selectedProduct by remember { mutableStateOf<ProductEntity?>(null) }
    var showConfirmDialog by remember { mutableStateOf(false) }

    val landscape = isLandscape

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .background(
                    brush = Brush.verticalGradient(
                        colors = listOf(EPayGreenDark, EPayGreen)
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
                    Text(providerCode, fontSize = 20.sp, fontWeight = FontWeight.Bold, color = Color.White)
                    Text("Select load amount", fontSize = 13.sp, color = Color.White.copy(alpha = 0.8f))
                }
            }
        }

        if (landscape) {
            Row(
                modifier = Modifier
                    .fillMaxSize()
                    .padding(16.dp),
                horizontalArrangement = Arrangement.spacedBy(16.dp)
            ) {
                Column(modifier = Modifier.weight(0.35f)) {
                    ELoadPhoneInput(
                        phoneNumber = phoneNumber,
                        onPhoneChange = { if (it.length <= 11 && it.all { c -> c.isDigit() }) phoneNumber = it },
                        onClear = { phoneNumber = "" }
                    )
                    if (phoneNumber.length < 10 && selectedProduct != null) {
                        Spacer(modifier = Modifier.height(12.dp))
                        PhoneWarningCard()
                    }
                }
                Column(modifier = Modifier.weight(0.65f)) {
                    ELoadProductSections(
                        regularProducts = regularProducts,
                        promoProducts = promoProducts,
                        selectedProduct = selectedProduct,
                        phoneNumber = phoneNumber,
                        onProductSelected = { product ->
                            selectedProduct = product
                            if (phoneNumber.length >= 10) showConfirmDialog = true
                        },
                        modifier = Modifier.fillMaxSize()
                    )
                }
            }
        } else {
            Column(modifier = Modifier.padding(16.dp)) {
                ELoadPhoneInput(
                    phoneNumber = phoneNumber,
                    onPhoneChange = { if (it.length <= 11 && it.all { c -> c.isDigit() }) phoneNumber = it },
                    onClear = { phoneNumber = "" }
                )
                Spacer(modifier = Modifier.height(16.dp))
                ELoadProductSections(
                    regularProducts = regularProducts,
                    promoProducts = promoProducts,
                    selectedProduct = selectedProduct,
                    phoneNumber = phoneNumber,
                    onProductSelected = { product ->
                        selectedProduct = product
                        if (phoneNumber.length >= 10) showConfirmDialog = true
                    }
                )
                if (phoneNumber.length < 10 && selectedProduct != null) {
                    Spacer(modifier = Modifier.height(12.dp))
                    PhoneWarningCard()
                }
            }
        }
    }

    if (showConfirmDialog && selectedProduct != null) {
        AlertDialog(
            onDismissRequest = { showConfirmDialog = false },
            icon = { Icon(Icons.Filled.PhoneAndroid, "E-Load", tint = EPayGreen) },
            title = { Text("Confirm E-Load", fontWeight = FontWeight.Bold) },
            text = {
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    ConfirmRow("Network", selectedProduct!!.providerName)
                    ConfirmRow("Amount", "₱${String.format("%,.2f", selectedProduct!!.amount)}")
                    ConfirmRow("Number", phoneNumber)
                    Divider()
                    ConfirmRow("Total", "₱${String.format("%,.2f", selectedProduct!!.amount)}")
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        showConfirmDialog = false
                        navController.navigate(
                            NavRoutes.ELoadProcess.createRoute(
                                providerCode, selectedProduct!!.id, phoneNumber
                            )
                        )
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = EPayGreen),
                    shape = RoundedCornerShape(12.dp)
                ) { Text("Confirm & Load") }
            },
            dismissButton = {
                TextButton(onClick = { showConfirmDialog = false }) { Text("Cancel") }
            },
            shape = RoundedCornerShape(20.dp)
        )
    }
}

@Composable
private fun ELoadPhoneInput(
    phoneNumber: String,
    onPhoneChange: (String) -> Unit,
    onClear: () -> Unit
) {
    OutlinedTextField(
        value = phoneNumber,
        onValueChange = onPhoneChange,
        label = { Text("Mobile Number") },
        placeholder = { Text("09xxxxxxxxx") },
        leadingIcon = { Icon(Icons.Filled.Phone, "Phone", tint = EPayGreen) },
        trailingIcon = {
            if (phoneNumber.isNotEmpty()) {
                IconButton(onClick = onClear) {
                    Icon(Icons.Filled.Clear, "Clear", modifier = Modifier.size(18.dp))
                }
            }
        },
        keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(14.dp),
        singleLine = true,
        colors = OutlinedTextFieldDefaults.colors(
            focusedBorderColor = EPayGreen,
            focusedLabelColor = EPayGreen
        )
    )
}

@Composable
private fun PhoneWarningCard() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(10.dp),
        colors = CardDefaults.cardColors(containerColor = StatusWarning.copy(alpha = 0.1f))
    ) {
        Row(modifier = Modifier.padding(12.dp), verticalAlignment = Alignment.CenterVertically) {
            Icon(Icons.Filled.Info, "Info", tint = StatusWarning, modifier = Modifier.size(18.dp))
            Spacer(modifier = Modifier.width(8.dp))
            Text("Please enter a valid mobile number first", fontSize = 13.sp, color = StatusWarning)
        }
    }
}

@Composable
private fun ELoadProductSections(
    regularProducts: List<ProductEntity>,
    promoProducts: List<ProductEntity>,
    selectedProduct: ProductEntity?,
    phoneNumber: String,
    onProductSelected: (ProductEntity) -> Unit,
    modifier: Modifier = Modifier
) {
    if (regularProducts.isEmpty() && promoProducts.isEmpty()) {
        Box(modifier = modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
            Text("No load products available. Pull to refresh from home.", color = EPayMediumGray, fontSize = 14.sp)
        }
        return
    }

    Column(modifier = modifier) {
        if (regularProducts.isNotEmpty()) {
            Text("Regular Load", fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = EPayMediumGray)
            Spacer(modifier = Modifier.height(8.dp))
            LazyVerticalGrid(
                columns = productGridColumns(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp),
                modifier = Modifier.heightIn(max = if (promoProducts.isEmpty()) 600.dp else 280.dp)
            ) {
                productGridItems(regularProducts, selectedProduct, phoneNumber, onProductSelected)
            }
        }
        if (promoProducts.isNotEmpty()) {
            Spacer(modifier = Modifier.height(16.dp))
            Text("Promos", fontWeight = FontWeight.SemiBold, fontSize = 14.sp, color = EPayMediumGray)
            Spacer(modifier = Modifier.height(8.dp))
            LazyVerticalGrid(
                columns = productGridColumns(),
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                productGridItems(promoProducts, selectedProduct, phoneNumber, onProductSelected, showPromoLabel = true)
            }
        }
    }
}

private fun LazyGridScope.productGridItems(
    products: List<ProductEntity>,
    selectedProduct: ProductEntity?,
    phoneNumber: String,
    onProductSelected: (ProductEntity) -> Unit,
    showPromoLabel: Boolean = false
) {
    items(products) { product ->
        val isSelected = selectedProduct == product
        Card(
            modifier = Modifier
                .fillMaxWidth()
                .clickable { onProductSelected(product) },
            shape = RoundedCornerShape(12.dp),
            colors = CardDefaults.cardColors(
                containerColor = if (isSelected) EPayGreen else Color.White
            ),
            elevation = CardDefaults.cardElevation(
                defaultElevation = if (isSelected) 4.dp else 1.dp
            )
        ) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 14.dp, horizontal = 8.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Text(
                    "₱${String.format("%,.0f", product.amount)}",
                    fontWeight = FontWeight.Bold,
                    fontSize = 16.sp,
                    color = if (isSelected) Color.White else EPayGreenDark,
                    textAlign = TextAlign.Center
                )
                if (showPromoLabel) {
                    Text(
                        product.productName,
                        fontSize = 10.sp,
                        color = if (isSelected) Color.White.copy(alpha = 0.9f) else EPayMediumGray,
                        textAlign = TextAlign.Center,
                        maxLines = 2
                    )
                }
            }
        }
    }
}

@Composable
internal fun ConfirmRow(label: String, value: String) {
    Row(
        modifier = Modifier.fillMaxWidth(),
        horizontalArrangement = Arrangement.SpaceBetween
    ) {
        Text(label, color = EPayMediumGray, fontSize = 14.sp)
        Text(value, fontWeight = FontWeight.SemiBold, fontSize = 14.sp)
    }
}
