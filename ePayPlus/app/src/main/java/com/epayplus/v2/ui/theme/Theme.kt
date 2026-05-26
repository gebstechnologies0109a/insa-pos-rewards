package com.epayplus.v2.ui.theme

import android.app.Activity
import android.os.Build
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.runtime.SideEffect
import androidx.compose.ui.graphics.toArgb
import androidx.compose.ui.platform.LocalView
import androidx.core.view.WindowCompat

private val LightColorScheme = lightColorScheme(
    primary = EPayGreen,
    onPrimary = EPayWhite,
    primaryContainer = EPayGreenLight,
    onPrimaryContainer = EPayGreenDark,
    secondary = EPayBlue,
    onSecondary = EPayWhite,
    secondaryContainer = EPayBlueLight,
    onSecondaryContainer = EPayBlueDark,
    tertiary = EPayOrange,
    onTertiary = EPayWhite,
    background = EPayWhite,
    onBackground = EPayDarkGray,
    surface = EPayWhite,
    onSurface = EPayDarkGray,
    surfaceVariant = EPayLightGray,
    onSurfaceVariant = EPayMediumGray,
    error = StatusError,
    onError = EPayWhite
)

private val DarkColorScheme = darkColorScheme(
    primary = EPayGreenLight,
    onPrimary = EPayGreenDark,
    primaryContainer = EPayGreen,
    onPrimaryContainer = EPayWhite,
    secondary = EPayBlueLight,
    onSecondary = EPayBlueDark,
    secondaryContainer = EPayBlue,
    onSecondaryContainer = EPayWhite,
    tertiary = EPayOrange,
    onTertiary = EPayDarkGray,
    background = DarkBackground,
    onBackground = EPayWhite,
    surface = DarkSurface,
    onSurface = EPayWhite,
    surfaceVariant = DarkCard,
    onSurfaceVariant = EPayLightGray,
    error = StatusError,
    onError = EPayWhite
)

@Composable
fun EPayPlusTheme(
    darkTheme: Boolean = isSystemInDarkTheme(),
    dynamicColor: Boolean = true,
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
            window.statusBarColor = colorScheme.primary.toArgb()
            WindowCompat.getInsetsController(window, view).isAppearanceLightStatusBars = !darkTheme
        }
    }

    MaterialTheme(
        colorScheme = colorScheme,
        typography = Typography,
        content = content
    )
}
