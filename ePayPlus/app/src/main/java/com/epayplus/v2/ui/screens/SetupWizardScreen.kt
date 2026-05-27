package com.epayplus.v2.ui.screens

import androidx.compose.animation.AnimatedContent
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.hilt.navigation.compose.hiltViewModel
import com.epayplus.v2.ui.layout.CenteredContent
import com.epayplus.v2.ui.layout.isLandscape
import com.epayplus.v2.ui.viewmodel.SetupWizardViewModel

@Composable
fun SetupWizardScreen(
    viewModel: SetupWizardViewModel = hiltViewModel(),
    onSetupComplete: () -> Unit = {}
) {
    val currentStep by viewModel.currentStep.collectAsState()
    val isLoading by viewModel.isLoading.collectAsState()
    val errorMessage by viewModel.errorMessage.collectAsState()
    val serverUrl by viewModel.serverUrl.collectAsState()
    val licenseCode by viewModel.licenseCode.collectAsState()
    val machineUid by viewModel.machineUid.collectAsState()
    val accountId by viewModel.accountId.collectAsState()
    val pin by viewModel.pin.collectAsState()
    val deviceMode by viewModel.deviceMode.collectAsState()

    val landscape = isLandscape

    Surface(
        modifier = Modifier.fillMaxSize(),
        color = MaterialTheme.colorScheme.background
    ) {
        CenteredContent(maxWidth = if (landscape) 720.dp else 520.dp) {
            Column(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(horizontal = if (landscape) 32.dp else 24.dp, vertical = if (landscape) 16.dp else 24.dp),
                horizontalAlignment = Alignment.CenterHorizontally
            ) {
                Spacer(modifier = Modifier.height(if (landscape) 16.dp else 48.dp))

            Text(
                "ePayPlus Setup",
                fontSize = 28.sp,
                fontWeight = FontWeight.Bold,
                color = MaterialTheme.colorScheme.primary
            )

            Spacer(modifier = Modifier.height(8.dp))

            // Step indicator (4 steps)
            Row(
                horizontalArrangement = Arrangement.spacedBy(8.dp),
                verticalAlignment = Alignment.CenterVertically
            ) {
                repeat(4) { index ->
                    Box(
                        modifier = Modifier
                            .size(if (index == currentStep) 12.dp else 8.dp)
                            .background(
                                if (index <= currentStep) MaterialTheme.colorScheme.primary
                                else Color.Gray.copy(alpha = 0.3f),
                                RoundedCornerShape(50)
                            )
                    )
                }
            }

            Spacer(modifier = Modifier.height(if (landscape) 24.dp else 48.dp))

            AnimatedContent(targetState = currentStep, label = "wizard_step") { step ->
                when (step) {
                    0 -> ServerSetupStep(
                        serverUrl = serverUrl,
                        onUrlChange = { viewModel.updateServerUrl(it) },
                        onNext = { viewModel.testConnection() },
                        isLoading = isLoading,
                        error = errorMessage
                    )
                    1 -> LicenseActivationStep(
                        licenseCode = licenseCode,
                        machineUid = machineUid,
                        onLicenseChange = { viewModel.updateLicenseCode(it) },
                        onMachineUidChange = { viewModel.updateMachineUid(it) },
                        onNext = { viewModel.activateLicense() },
                        isLoading = isLoading,
                        error = errorMessage
                    )
                    2 -> AccountActivationStep(
                        accountId = accountId,
                        pin = pin,
                        onAccountIdChange = { viewModel.updateAccountId(it) },
                        onPinChange = { viewModel.updatePin(it) },
                        onNext = { viewModel.activateAccount() },
                        isLoading = isLoading,
                        error = errorMessage
                    )
                    3 -> ModeConfigStep(
                        selectedMode = deviceMode,
                        onModeSelect = { viewModel.updateDeviceMode(it) },
                        onComplete = {
                            viewModel.completeSetup()
                            onSetupComplete()
                        },
                        isLoading = isLoading
                    )
                }
            }
            }
        }
    }
}

@Composable
private fun ServerSetupStep(
    serverUrl: String,
    onUrlChange: (String) -> Unit,
    onNext: () -> Unit,
    isLoading: Boolean,
    error: String?
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Icon(
            Icons.Default.Cloud,
            contentDescription = null,
            modifier = Modifier.size(64.dp),
            tint = MaterialTheme.colorScheme.primary
        )
        Spacer(modifier = Modifier.height(16.dp))
        Text("Step 1: Server Connection", fontSize = 20.sp, fontWeight = FontWeight.SemiBold)
        Spacer(modifier = Modifier.height(8.dp))
        Text(
            "Enter the server URL to connect this device",
            textAlign = TextAlign.Center,
            color = Color.Gray
        )
        Spacer(modifier = Modifier.height(24.dp))

        OutlinedTextField(
            value = serverUrl,
            onValueChange = onUrlChange,
            label = { Text("Server URL") },
            placeholder = { Text("https://epayplus.example.com/api") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true
        )

        if (error != null) {
            Spacer(modifier = Modifier.height(8.dp))
            Text(error, color = MaterialTheme.colorScheme.error, fontSize = 13.sp)
        }

        Spacer(modifier = Modifier.height(24.dp))

        Button(
            onClick = onNext,
            modifier = Modifier.fillMaxWidth().height(50.dp),
            enabled = serverUrl.isNotBlank() && !isLoading
        ) {
            if (isLoading) CircularProgressIndicator(modifier = Modifier.size(20.dp), color = Color.White)
            else Text("Test & Continue")
        }
    }
}

@Composable
private fun LicenseActivationStep(
    licenseCode: String,
    machineUid: String,
    onLicenseChange: (String) -> Unit,
    onMachineUidChange: (String) -> Unit,
    onNext: () -> Unit,
    isLoading: Boolean,
    error: String?
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Icon(Icons.Default.VpnKey, contentDescription = null, modifier = Modifier.size(64.dp), tint = MaterialTheme.colorScheme.primary)
        Spacer(modifier = Modifier.height(16.dp))
        Text("Step 2: Machine Activation", fontSize = 20.sp, fontWeight = FontWeight.SemiBold)
        Spacer(modifier = Modifier.height(8.dp))
        Text("Enter your license key and verify machine UID", textAlign = TextAlign.Center, color = Color.Gray)
        Spacer(modifier = Modifier.height(24.dp))

        OutlinedTextField(
            value = licenseCode,
            onValueChange = onLicenseChange,
            label = { Text("License Code") },
            placeholder = { Text("EPAY-XXXX-XXXX") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true
        )
        Spacer(modifier = Modifier.height(12.dp))
        OutlinedTextField(
            value = machineUid,
            onValueChange = onMachineUidChange,
            label = { Text("Machine UID") },
            placeholder = { Text("EPAY... or 09NET...") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true
        )

        if (error != null) {
            Spacer(modifier = Modifier.height(8.dp))
            Text(error, color = MaterialTheme.colorScheme.error, fontSize = 13.sp)
        }

        Spacer(modifier = Modifier.height(24.dp))
        Button(
            onClick = onNext,
            modifier = Modifier.fillMaxWidth().height(50.dp),
            enabled = licenseCode.isNotBlank() && machineUid.isNotBlank() && !isLoading
        ) {
            if (isLoading) CircularProgressIndicator(modifier = Modifier.size(20.dp), color = Color.White)
            else Text("Activate Machine")
        }
    }
}

@Composable
private fun AccountActivationStep(
    accountId: String,
    pin: String,
    onAccountIdChange: (String) -> Unit,
    onPinChange: (String) -> Unit,
    onNext: () -> Unit,
    isLoading: Boolean,
    error: String?
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Icon(
            Icons.Default.Person,
            contentDescription = null,
            modifier = Modifier.size(64.dp),
            tint = MaterialTheme.colorScheme.primary
        )
        Spacer(modifier = Modifier.height(16.dp))
        Text("Step 3: Account Activation", fontSize = 20.sp, fontWeight = FontWeight.SemiBold)
        Spacer(modifier = Modifier.height(8.dp))
        Text("Enter your retailer account credentials", textAlign = TextAlign.Center, color = Color.Gray)
        Spacer(modifier = Modifier.height(24.dp))

        OutlinedTextField(
            value = accountId,
            onValueChange = onAccountIdChange,
            label = { Text("Account ID") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true
        )
        Spacer(modifier = Modifier.height(12.dp))
        OutlinedTextField(
            value = pin,
            onValueChange = onPinChange,
            label = { Text("PIN") },
            modifier = Modifier.fillMaxWidth(),
            singleLine = true
        )

        if (error != null) {
            Spacer(modifier = Modifier.height(8.dp))
            Text(error, color = MaterialTheme.colorScheme.error, fontSize = 13.sp)
        }

        Spacer(modifier = Modifier.height(24.dp))

        Button(
            onClick = onNext,
            modifier = Modifier.fillMaxWidth().height(50.dp),
            enabled = accountId.isNotBlank() && pin.isNotBlank() && !isLoading
        ) {
            if (isLoading) CircularProgressIndicator(modifier = Modifier.size(20.dp), color = Color.White)
            else Text("Activate & Continue")
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ModeConfigStep(
    selectedMode: String,
    onModeSelect: (String) -> Unit,
    onComplete: () -> Unit,
    isLoading: Boolean
) {
    Column(
        modifier = Modifier.fillMaxWidth(),
        horizontalAlignment = Alignment.CenterHorizontally
    ) {
        Icon(
            Icons.Default.Settings,
            contentDescription = null,
            modifier = Modifier.size(64.dp),
            tint = MaterialTheme.colorScheme.primary
        )
        Spacer(modifier = Modifier.height(16.dp))
        Text("Step 4: Device Mode", fontSize = 20.sp, fontWeight = FontWeight.SemiBold)
        Spacer(modifier = Modifier.height(8.dp))
        Text("Choose how this device will operate", textAlign = TextAlign.Center, color = Color.Gray)
        Spacer(modifier = Modifier.height(24.dp))

        val modes = listOf(
            "retailer" to "Retailer Mode" to "Normal app with manual operation",
            "kiosk" to "Kiosk Mode" to "Locked tablet with customer-facing UI"
        )

        modes.forEach { (modeInfo, description) ->
            val (mode, label) = modeInfo
            Card(
                modifier = Modifier
                    .fillMaxWidth()
                    .padding(vertical = 4.dp),
                colors = CardDefaults.cardColors(
                    containerColor = if (selectedMode == mode)
                        MaterialTheme.colorScheme.primaryContainer
                    else MaterialTheme.colorScheme.surface
                ),
                onClick = { onModeSelect(mode) }
            ) {
                Row(
                    modifier = Modifier.padding(16.dp),
                    verticalAlignment = Alignment.CenterVertically
                ) {
                    RadioButton(selected = selectedMode == mode, onClick = { onModeSelect(mode) })
                    Spacer(modifier = Modifier.width(12.dp))
                    Column {
                        Text(label, fontWeight = FontWeight.Medium)
                        Text(description, fontSize = 13.sp, color = Color.Gray)
                    }
                }
            }
        }

        Spacer(modifier = Modifier.height(32.dp))

        Button(
            onClick = onComplete,
            modifier = Modifier.fillMaxWidth().height(50.dp),
            enabled = !isLoading
        ) {
            Text("Complete Setup")
        }
    }
}
