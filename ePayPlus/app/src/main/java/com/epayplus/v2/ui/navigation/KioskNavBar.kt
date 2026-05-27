package com.epayplus.v2.ui.navigation

import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.layout.width
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.Home
import androidx.compose.material3.ButtonDefaults
import androidx.compose.material3.FilledTonalButton
import androidx.compose.material3.Icon
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Surface
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import com.epayplus.v2.ui.layout.isLandscape
import com.epayplus.v2.ui.theme.EPayGreen

/**
 * Persistent kiosk navigation bar shown on every sub-screen (hidden on kiosk home).
 *
 * **Back** — pops one level via [NavHostController.popBackStack]. Does not exit lock task
 * or leave kiosk mode. Hardware BACK / HOME / RECENTS keys remain blocked in [KioskActivity].
 *
 * **Home** — returns to [NavRoutes.KioskHome] and clears the in-app back stack. Stays inside
 * kiosk mode; does not trigger admin exit.
 *
 * To fully exit kiosk, long-press the **ePayPlus** title on kiosk home and enter the kiosk PIN
 * (see [com.epayplus.v2.ui.screens.KioskHomeScreen]).
 */
@Composable
fun KioskNavBar(
    showBack: Boolean,
    onBack: () -> Unit,
    onHome: () -> Unit,
    modifier: Modifier = Modifier
) {
    val horizontalPadding = if (isLandscape) 48.dp else 24.dp
    val verticalPadding = if (isLandscape) 16.dp else 12.dp

    Surface(
        modifier = modifier.fillMaxWidth(),
        color = MaterialTheme.colorScheme.surface,
        tonalElevation = 3.dp,
        shadowElevation = 6.dp
    ) {
        Row(
            modifier = Modifier
                .fillMaxWidth()
                .navigationBarsPadding()
                .padding(horizontal = horizontalPadding, vertical = verticalPadding),
            horizontalArrangement = Arrangement.SpaceBetween,
            verticalAlignment = Alignment.CenterVertically
        ) {
            if (showBack) {
                KioskNavButton(
                    label = "Back",
                    icon = {
                        Icon(
                            Icons.AutoMirrored.Filled.ArrowBack,
                            contentDescription = "Back",
                            modifier = Modifier.size(22.dp)
                        )
                    },
                    onClick = onBack
                )
            } else {
                Spacer(modifier = Modifier.weight(1f))
            }

            KioskNavButton(
                label = "Home",
                icon = {
                    Icon(
                        Icons.Filled.Home,
                        contentDescription = "Home",
                        modifier = Modifier.size(22.dp)
                    )
                },
                onClick = onHome
            )
        }
    }
}

@Composable
private fun KioskNavButton(
    label: String,
    icon: @Composable () -> Unit,
    onClick: () -> Unit
) {
    FilledTonalButton(
        onClick = onClick,
        shape = RoundedCornerShape(12.dp),
        colors = ButtonDefaults.filledTonalButtonColors(
            containerColor = EPayGreen.copy(alpha = 0.12f),
            contentColor = EPayGreen
        ),
        contentPadding = ButtonDefaults.ContentPadding
    ) {
        icon()
        Spacer(modifier = Modifier.width(8.dp))
        Text(label, fontSize = 15.sp, fontWeight = FontWeight.SemiBold)
    }
}

fun isKioskRootRoute(route: String?): Boolean =
    route == NavRoutes.KioskHome.route || route == NavRoutes.Home.route

fun navigateToKioskHome(navController: NavHostController) {
    navController.navigate(NavRoutes.KioskHome.route) {
        popUpTo(navController.graph.findStartDestination().id) {
            inclusive = false
        }
        launchSingleTop = true
    }
}
