package com.epayplus.v2.ui.screens

import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.navigation.NavController
import com.epayplus.v2.data.local.entity.ProductEntity
import com.epayplus.v2.ui.navigation.NavRoutes
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
    var phoneNumber by remember { mutableStateOf("") }
    var showPhoneDialog by remember { mutableStateOf(false) }
    var selectedProduct by remember { mutableStateOf<ProductEntity?>(null) }

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text(providerCode, fontWeight = FontWeight.Bold) },
                navigationIcon = {
                    IconButton(onClick = { navController.popBackStack() }) {
                        Icon(Icons.Filled.ArrowBack, "Back")
                    }
                },
                colors = TopAppBarDefaults.topAppBarColors(
                    containerColor = EPayGreen,
                    titleContentColor = Color.White,
                    navigationIconContentColor = Color.White
                )
            )
        }
    ) { paddingValues ->
        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
            // Phone Number Input
            OutlinedTextField(
                value = phoneNumber,
                onValueChange = { if (it.length <= 11) phoneNumber = it },
                label = { Text("Mobile Number") },
                placeholder = { Text("09xxxxxxxxx") },
                leadingIcon = { Icon(Icons.Filled.Phone, "Phone") },
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Phone),
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(16.dp),
                shape = RoundedCornerShape(12.dp),
                singleLine = true
            )

            Text(
                "Select Load Amount",
                style = MaterialTheme.typography.titleMedium,
                fontWeight = FontWeight.SemiBold,
                modifier = Modifier.padding(horizontal = 16.dp)
            )

            Spacer(modifier = Modifier.height(8.dp))

            LazyColumn(
                contentPadding = PaddingValues(16.dp),
                verticalArrangement = Arrangement.spacedBy(8.dp)
            ) {
                items(products) { product ->
                    ProductCard(product) {
                        if (phoneNumber.length >= 10) {
                            selectedProduct = product
                            showPhoneDialog = true
                        }
                    }
                }
            }
        }
    }

    if (showPhoneDialog && selectedProduct != null) {
        AlertDialog(
            onDismissRequest = { showPhoneDialog = false },
            title = { Text("Confirm E-Load") },
            text = {
                Column {
                    Text("Provider: ${selectedProduct!!.providerName}")
                    Text("Product: ${selectedProduct!!.productName}")
                    Text("Amount: ₱${String.format("%,.2f", selectedProduct!!.amount)}")
                    Text("Number: $phoneNumber")
                }
            },
            confirmButton = {
                Button(
                    onClick = {
                        showPhoneDialog = false
                        navController.navigate(
                            NavRoutes.ELoadProcess.createRoute(selectedProduct!!.id, phoneNumber)
                        )
                    },
                    colors = ButtonDefaults.buttonColors(containerColor = EPayGreen)
                ) { Text("Confirm") }
            },
            dismissButton = {
                TextButton(onClick = { showPhoneDialog = false }) { Text("Cancel") }
            }
        )
    }
}

@Composable
private fun ProductCard(product: ProductEntity, onClick: () -> Unit) {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .clickable(onClick = onClick),
        shape = RoundedCornerShape(12.dp),
        elevation = CardDefaults.cardElevation(defaultElevation = 1.dp)
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .padding(16.dp),
            verticalAlignment = Alignment.CenterVertically,
            horizontalArrangement = Arrangement.SpaceBetween
        ) {
            Column {
                Text(product.productName, fontWeight = FontWeight.Medium)
                if (product.description.isNotEmpty()) {
                    Text(
                        product.description,
                        style = MaterialTheme.typography.bodySmall,
                        color = Color.Gray
                    )
                }
            }
            Text(
                "₱${String.format("%,.0f", product.amount)}",
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp,
                color = EPayGreen
            )
        }
    }
}
