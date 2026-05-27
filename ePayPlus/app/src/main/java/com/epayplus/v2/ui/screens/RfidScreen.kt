package com.epayplus.v2.ui.screens

import android.app.Activity
import android.nfc.NfcAdapter
import android.nfc.Tag
import android.nfc.tech.Ndef
import androidx.compose.foundation.Image
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
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavController
import com.epayplus.v2.R
import com.epayplus.v2.ui.theme.*

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RfidScreen(navController: NavController) {
    val context = LocalContext.current
    val activity = context as? Activity
    val nfcAdapter = remember { NfcAdapter.getDefaultAdapter(context) }

    var tagId by remember { mutableStateOf<String?>(null) }
    var statusMessage by remember {
        mutableStateOf(
            when {
                nfcAdapter == null -> "NFC is not available on this device."
                !nfcAdapter.isEnabled -> "Enable NFC in device settings to scan RFID cards."
                else -> "Hold an RFID / NFC card near the device to read its tag ID."
            }
        )
    }

    DisposableEffect(activity, nfcAdapter) {
        if (activity != null && nfcAdapter != null && nfcAdapter.isEnabled) {
            nfcAdapter.enableReaderMode(
                activity,
                { tag: Tag ->
                    val id = tag.id.joinToString(":") { byte -> "%02X".format(byte) }
                    var message = "Tag detected. Full RFID services are coming soon."

                    val ndef = Ndef.get(tag)
                    if (ndef != null) {
                        try {
                            ndef.connect()
                            val ndefMessage = ndef.ndefMessage
                            if (ndefMessage != null && ndefMessage.records.isNotEmpty()) {
                                message = "NDEF record read. Full RFID services are coming soon."
                            }
                        } catch (_: Exception) {
                            // Tag read is best-effort for this placeholder screen.
                        } finally {
                            try {
                                ndef.close()
                            } catch (_: Exception) {
                            }
                        }
                    }

                    activity.runOnUiThread {
                        tagId = id
                        statusMessage = message
                    }
                },
                NfcAdapter.FLAG_READER_NFC_A or
                    NfcAdapter.FLAG_READER_NFC_B or
                    NfcAdapter.FLAG_READER_NFC_F or
                    NfcAdapter.FLAG_READER_NFC_V or
                    NfcAdapter.FLAG_READER_SKIP_NDEF_CHECK,
                null
            )
        }

        onDispose {
            if (activity != null && nfcAdapter != null) {
                nfcAdapter.disableReaderMode(activity)
            }
        }
    }

    Column(modifier = Modifier.fillMaxSize().background(MaterialTheme.colorScheme.background)) {
        TopAppBar(
            title = { Text("RFID", fontWeight = FontWeight.Bold) },
            navigationIcon = {
                IconButton(onClick = { navController.popBackStack() }) {
                    Icon(Icons.Filled.ArrowBack, "Back")
                }
            },
            colors = TopAppBarDefaults.topAppBarColors(
                containerColor = CategoryRfid,
                titleContentColor = Color.White,
                navigationIconContentColor = Color.White
            )
        )

        Column(
            modifier = Modifier
                .fillMaxSize()
                .padding(24.dp),
            horizontalAlignment = Alignment.CenterHorizontally,
            verticalArrangement = Arrangement.Center
        ) {
            Surface(
                modifier = Modifier.size(96.dp),
                shape = CircleShape,
                color = CategoryRfid.copy(alpha = 0.12f)
            ) {
                Box(contentAlignment = Alignment.Center) {
                    Image(
                        painter = painterResource(R.drawable.ic_quick_rfid),
                        contentDescription = "RFID",
                        modifier = Modifier.size(56.dp),
                        contentScale = ContentScale.Fit
                    )
                }
            }

            Spacer(modifier = Modifier.height(24.dp))

            Text(
                "RFID Services",
                fontSize = 22.sp,
                fontWeight = FontWeight.Bold,
                color = EPayDarkGray
            )
            Spacer(modifier = Modifier.height(8.dp))
            Text(
                "Coming soon",
                fontSize = 14.sp,
                color = EPayMediumGray,
                textAlign = TextAlign.Center
            )

            Spacer(modifier = Modifier.height(24.dp))

            Card(
                modifier = Modifier.fillMaxWidth(),
                shape = RoundedCornerShape(16.dp),
                colors = CardDefaults.cardColors(containerColor = CategoryRfid.copy(alpha = 0.08f))
            ) {
                Column(modifier = Modifier.padding(20.dp)) {
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Icon(
                            Icons.Filled.Nfc,
                            contentDescription = null,
                            tint = CategoryRfid,
                            modifier = Modifier.size(22.dp)
                        )
                        Spacer(modifier = Modifier.width(10.dp))
                        Text(
                            "Tap to Read",
                            fontWeight = FontWeight.SemiBold,
                            color = CategoryRfid
                        )
                    }
                    Spacer(modifier = Modifier.height(10.dp))
                    Text(statusMessage, fontSize = 14.sp, color = EPayMediumGray)
                    tagId?.let { id ->
                        Spacer(modifier = Modifier.height(12.dp))
                        Text("Tag ID", fontSize = 12.sp, color = EPayMediumGray)
                        Text(id, fontWeight = FontWeight.Medium, fontSize = 13.sp)
                    }
                }
            }
        }
    }
}
