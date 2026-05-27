package com.epayplus.v2.ui.components

import androidx.annotation.DrawableRes
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Surface
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import coil.compose.AsyncImage

@Composable
fun ProviderIcon(
    providerCode: String,
    providerName: String = "",
    logoUrl: String? = null,
    modifier: Modifier = Modifier,
    size: Dp = 44.dp,
    fallbackIcon: ImageVector? = null,
    fallbackTint: Color = Color.Gray,
    backgroundColor: Color = Color.Transparent,
    contentPadding: Dp = 6.dp,
    rounded: Boolean = true
) {
    val shape = if (rounded) CircleShape else RoundedCornerShape(12.dp)
    val localRes = ProviderIcons.resolve(providerCode, providerName)

    Surface(
        modifier = modifier.size(size),
        shape = shape,
        color = backgroundColor
    ) {
        Box(contentAlignment = Alignment.Center) {
            when {
                !logoUrl.isNullOrBlank() -> {
                    AsyncImage(
                        model = logoUrl,
                        contentDescription = providerName.ifEmpty { providerCode },
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(contentPadding)
                            .clip(RoundedCornerShape(8.dp)),
                        contentScale = ContentScale.Fit
                    )
                }
                localRes != null -> {
                    Image(
                        painter = painterResource(localRes),
                        contentDescription = providerName.ifEmpty { providerCode },
                        modifier = Modifier
                            .fillMaxSize()
                            .padding(contentPadding),
                        contentScale = ContentScale.Fit
                    )
                }
                fallbackIcon != null -> {
                    Icon(
                        fallbackIcon,
                        contentDescription = providerName.ifEmpty { providerCode },
                        tint = fallbackTint,
                        modifier = Modifier.size(size * 0.5f)
                    )
                }
            }
        }
    }
}

@Composable
fun ProviderIconFromRes(
    @DrawableRes resId: Int,
    contentDescription: String,
    modifier: Modifier = Modifier,
    size: Dp = 44.dp,
    backgroundColor: Color = Color.Transparent,
    contentPadding: Dp = 6.dp
) {
    Surface(
        modifier = modifier.size(size),
        shape = CircleShape,
        color = backgroundColor
    ) {
        Image(
            painter = painterResource(resId),
            contentDescription = contentDescription,
            modifier = Modifier
                .fillMaxSize()
                .padding(contentPadding),
            contentScale = ContentScale.Fit
        )
    }
}
