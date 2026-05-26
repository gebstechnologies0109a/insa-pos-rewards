package com.epayplus.v2.ui.theme

import android.app.Activity
import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

private val LightColorScheme = lightColorScheme(
    primary = EPayGreen,
    onPrimary = Color.White,
    primaryContainer = EPayGreenSurface,
    onPrimaryContainer = EPayGreenDark,
    secondary = EPayGold,
    onSecondary = Color.White,
    secondaryContainer = EPayGoldSurface,
    onSecondaryContainer = EPayGoldDark,
    tertiary = EPayBlue,
    onTertiary = Color.White,
    background = EPayLightGray,
    onBackground = EPayDarkGray,
    surface = Color.White,
    onSurface = EPayDarkGray,
    surfaceVariant = EPaySurfaceGray,
    onSurfaceVariant = EPayMediumGray,
    error = StatusError,
    onError = Color.White,
    outline = Color(0xFFCAC4D0)
)

private val DarkColorScheme = darkColorScheme(
    primary = EPayGreenLight,
    onPrimary = EPayGreenDark,
    primaryContainer = EPayGreen,
    onPrimaryContainer = Color.White,
    secondary = EPayGoldLight,
    onSecondary = EPayGoldDark,
    secondaryContainer = EPayGold,
    onSecondaryContainer = Color.White,
    tertiary = EPayBlueLight,
    onTertiary = EPayBlueDark,
    background = DarkBackground,
    onBackground = Color.White,
    surface = DarkSurface,
    onSurface = Color.White,
    surfaceVariant = DarkCard,
    onSurfaceVariant = Color(0xFFCAC4D0),
    error = Color(0xFFEF5350),
    onError = Color.White
)

@Composable
fun EPayPlusTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    dynamicColor: Boolean = false,
    content: @Composable () -> Unit
) {
    val colorScheme = when {
        dynamicColor && Build.VERSION.SDK_INT >= Build.VERSION_CODES.S -> {
            val context = LocalView.current.context
            if (darkTheme) dynamicDarkColorScheme(context) else dynamicLightColorScheme(context)
        }
        darkTheme -> DarkColorScheme
        else -> LightColorScheme
    }

    val view = LocalView.current
    if (!view.isInEditMode) {
        SideEffect {
            val window = (view.context as Activity).window
            window.statusBarColor = EPayGreenDark.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = false
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography,
        content = content
    )
}
