package com.epayplus.v2.ui.navigation

import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material.icons.outlined.*
import androidx.compose.ui.graphics.vector.ImageVector

sealed class BottomNavItem(
    val route: String,
    val title: String,
    val icon: ImageVector,
    val selectedIcon: ImageVector
) {
    object Home : BottomNavItem(
        route = NavRoutes.Home.route,
        title = "Home",
        icon = Icons.Outlined.Home,
        selectedIcon = Icons.Filled.Home
    )

    object ELoad : BottomNavItem(
        route = NavRoutes.ELoadProviders.route,
        title = "E-Load",
        icon = Icons.Outlined.PhoneAndroid,
        selectedIcon = Icons.Filled.PhoneAndroid
    )

    object Bills : BottomNavItem(
        route = NavRoutes.BillsCategories.route,
        title = "Bills",
        icon = Icons.Outlined.Receipt,
        selectedIcon = Icons.Filled.Receipt
    )

    object Sales : BottomNavItem(
        route = NavRoutes.Sales.route,
        title = "Sales",
        icon = Icons.Outlined.BarChart,
        selectedIcon = Icons.Filled.BarChart
    )

    object Settings : BottomNavItem(
        route = NavRoutes.Settings.route,
        title = "More",
        icon = Icons.Outlined.MoreHoriz,
        selectedIcon = Icons.Filled.MoreHoriz
    )
}
