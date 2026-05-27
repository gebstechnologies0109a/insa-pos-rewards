package com.epayplus.v2.ui.nfc

import android.app.Activity
import android.content.Context
import android.nfc.NfcAdapter
import android.nfc.Tag
import android.nfc.tech.Ndef
import androidx.compose.runtime.*
import androidx.compose.ui.platform.LocalContext

data class NfcAvailability(
    val adapterPresent: Boolean,
    val enabled: Boolean
) {
    val canReadTags: Boolean get() = adapterPresent && enabled
}

fun nfcAvailability(context: Context): NfcAvailability {
    val adapter = NfcAdapter.getDefaultAdapter(context)
    return NfcAvailability(
        adapterPresent = adapter != null,
        enabled = adapter?.isEnabled == true
    )
}

/**
 * Optional NFC reader for RFID tag IDs. Does not block manual entry when NFC is absent.
 */
@Composable
fun rememberRfidTagReader(
    onTagRead: (String) -> Unit
): NfcAvailability {
    val context = LocalContext.current
    val activity = context as? Activity
    val availability = remember { nfcAvailability(context) }

    DisposableEffect(activity, availability.canReadTags) {
        val adapter = NfcAdapter.getDefaultAdapter(context)
        if (activity != null && adapter != null && availability.canReadTags) {
            adapter.enableReaderMode(
                activity,
                { tag: Tag ->
                    val id = tag.id.joinToString(":") { byte -> "%02X".format(byte) }
                    activity.runOnUiThread { onTagRead(id) }
                    val ndef = Ndef.get(tag)
                    if (ndef != null) {
                        try {
                            ndef.connect()
                            ndef.ndefMessage
                        } catch (_: Exception) {
                        } finally {
                            try {
                                ndef.close()
                            } catch (_: Exception) {
                            }
                        }
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
            if (activity != null && adapter != null) {
                adapter.disableReaderMode(activity)
            }
        }
    }

    return availability
}
