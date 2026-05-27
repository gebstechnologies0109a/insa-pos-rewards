package com.epayplus.v2.ui.layout

import android.content.res.Configuration
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.BoxScope
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.widthIn
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalConfiguration
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp

val isLandscape: Boolean
    @Composable get() = LocalConfiguration.current.orientation == Configuration.ORIENTATION_LANDSCAPE

@Composable
fun providerGridColumns(minSize: Dp = 180.dp): GridCells {
    return if (isLandscape) {
        GridCells.Adaptive(minSize = minSize)
    } else {
        GridCells.Fixed(3)
    }
}

@Composable
fun kioskGridColumns(): GridCells {
    return if (isLandscape) {
        GridCells.Adaptive(minSize = 160.dp)
    } else {
        GridCells.Fixed(2)
    }
}

@Composable
fun productGridColumns(): GridCells {
    return if (isLandscape) {
        GridCells.Adaptive(minSize = 120.dp)
    } else {
        GridCells.Fixed(4)
    }
}

@Composable
fun CenteredContent(
    maxWidth: Dp = 520.dp,
    modifier: Modifier = Modifier,
    content: @Composable BoxScope.() -> Unit
) {
    Box(
        modifier = modifier.fillMaxWidth(),
        contentAlignment = Alignment.TopCenter
    ) {
        Box(
            modifier = Modifier
                .fillMaxWidth()
                .widthIn(max = maxWidth),
            content = content
        )
    }
}
