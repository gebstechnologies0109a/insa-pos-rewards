package com.epayplus.v2.ui.navigation

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavGraph.Companion.findStartDestination
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.navArgument
import com.epayplus.v2.ui.layout.isLandscape
import androidx.hilt.navigation.compose.hiltViewModel
import com.epayplus.v2.ui.screens.*
import com.epayplus.v2.ui.theme.EPayGreen

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun AppNavigation(navController: NavHostController, isLoggedIn: Boolean) {
    val startDestination = if (isLoggedIn) NavRoutes.Home.route else NavRoutes.Login.route

    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route

    val bottomNavItems = listOf(
        BottomNavItem.Home,
        BottomNavItem.ELoad,
        BottomNavItem.Bills,
        BottomNavItem.ECash,
        BottomNavItem.More
    )

    val showNavBar = currentRoute in bottomNavItems.map { it.route }
    val useRail = showNavBar && isLandscape

    Scaffold(
        bottomBar = {
            if (showNavBar && !useRail) {
                AppBottomNavigationBar(
                    navController = navController,
                    currentRoute = currentRoute,
                    items = bottomNavItems
                )
            }
        }
    ) { paddingValues ->
        Row(modifier = Modifier.padding(paddingValues)) {
            if (useRail) {
                AppNavigationRail(
                    navController = navController,
                    currentRoute = currentRoute,
                    items = bottomNavItems
                )
            }
            NavHost(
                navController = navController,
                startDestination = startDestination,
                modifier = Modifier.weight(1f)
            ) {
                composable(NavRoutes.Login.route) {
                    LoginScreen(navController = navController)
                }

                composable(NavRoutes.Home.route) {
                    HomeScreen(navController = navController)
                }

                composable(NavRoutes.ELoadProviders.route) {
                    ELoadProvidersScreen(navController = navController)
                }

                composable(
                    route = NavRoutes.ELoadProducts.route,
                    arguments = listOf(navArgument("providerCode") { type = NavType.StringType })
                ) { backStackEntry ->
                    val providerCode = backStackEntry.arguments?.getString("providerCode") ?: ""
                    ELoadProductsScreen(navController = navController, providerCode = providerCode)
                }

                composable(
                    route = NavRoutes.ELoadProcess.route,
                    arguments = listOf(
                        navArgument("providerCode") { type = NavType.StringType },
                        navArgument("productId") { type = NavType.LongType },
                        navArgument("phoneNumber") { type = NavType.StringType }
                    )
                ) { backStackEntry ->
                    val providerCode = backStackEntry.arguments?.getString("providerCode") ?: ""
                    val productId = backStackEntry.arguments?.getLong("productId") ?: 0L
                    val phoneNumber = backStackEntry.arguments?.getString("phoneNumber") ?: ""
                    ELoadProcessScreen(
                        navController = navController,
                        providerCode = providerCode,
                        productId = productId,
                        phoneNumber = phoneNumber
                    )
                }

                composable(NavRoutes.BillsCategories.route) {
                    BillsCategoriesScreen(navController = navController)
                }

                composable(
                    route = NavRoutes.BillsBillers.route,
                    arguments = listOf(navArgument("category") { type = NavType.StringType })
                ) { backStackEntry ->
                    val category = backStackEntry.arguments?.getString("category") ?: ""
                    BillsBillersScreen(navController = navController, category = category)
                }

                composable(
                    route = NavRoutes.BillsProcess.route,
                    arguments = listOf(
                        navArgument("billerCode") { type = NavType.StringType },
                        navArgument("billerName") { type = NavType.StringType }
                    )
                ) { backStackEntry ->
                    val billerCode = backStackEntry.arguments?.getString("billerCode") ?: ""
                    val billerName = backStackEntry.arguments?.getString("billerName") ?: ""
                    BillsProcessScreen(
                        navController = navController,
                        billerCode = billerCode,
                        billerName = billerName
                    )
                }

                composable(NavRoutes.ECashProviders.route) {
                    ECashProvidersScreen(navController = navController)
                }

                composable(
                    route = NavRoutes.ECashProcess.route,
                    arguments = listOf(
                        navArgument("providerCode") { type = NavType.StringType },
                        navArgument("providerName") { type = NavType.StringType }
                    )
                ) { backStackEntry ->
                    val providerCode = backStackEntry.arguments?.getString("providerCode") ?: ""
                    val providerName = backStackEntry.arguments?.getString("providerName") ?: ""
                    ECashProcessScreen(
                        navController = navController,
                        providerCode = providerCode,
                        providerName = providerName
                    )
                }

                composable(NavRoutes.Sales.route) {
                    SalesScreen(navController = navController)
                }

                composable(NavRoutes.RfidProviders.route) {
                    RfidProvidersScreen(navController = navController)
                }

                composable(
                    route = NavRoutes.RfidProcess.route,
                    arguments = listOf(
                        navArgument("providerCode") { type = NavType.StringType },
                        navArgument("providerName") { type = NavType.StringType }
                    )
                ) { backStackEntry ->
                    val providerCode = backStackEntry.arguments?.getString("providerCode") ?: ""
                    val providerName = backStackEntry.arguments?.getString("providerName") ?: ""
                    RfidProcessScreen(
                        navController = navController,
                        providerCode = providerCode,
                        providerName = providerName
                    )
                }

                composable(NavRoutes.TransactionHistory.route) {
                    TransactionHistoryScreen(navController = navController)
                }

                composable(
                    route = NavRoutes.TransactionResult.route,
                    arguments = listOf(
                        navArgument("transactionId") { type = NavType.LongType },
                        navArgument("type") { type = NavType.StringType }
                    )
                ) { backStackEntry ->
                    val transactionId = backStackEntry.arguments?.getLong("transactionId") ?: 0L
                    val type = backStackEntry.arguments?.getString("type") ?: ""
                    TransactionResultScreen(
                        navController = navController,
                        transactionId = transactionId,
                        transactionType = type
                    )
                }

                composable(NavRoutes.PosMode.route) {
                    PosModeScreen(navController = navController)
                }

                composable(NavRoutes.Settings.route) {
                    SettingsScreen(navController = navController)
                }

                composable(NavRoutes.ChangePin.route) {
                    ChangePinScreen(navController = navController)
                }

                composable(NavRoutes.About.route) {
                    AboutScreen(navController = navController)
                }
            }
        }
    }
}

@Composable
private fun AppBottomNavigationBar(
    navController: NavHostController,
    currentRoute: String?,
    items: List<BottomNavItem>
) {
    NavigationBar(
        containerColor = MaterialTheme.colorScheme.surface,
        tonalElevation = NavigationBarDefaults.Elevation
    ) {
        items.forEach { item ->
            AppNavItem(
                item = item,
                selected = currentRoute == item.route,
                onClick = {
                    if (currentRoute != item.route) {
                        navController.navigate(item.route) {
                            popUpTo(navController.graph.findStartDestination().id) {
                                saveState = true
                            }
                            launchSingleTop = true
                            restoreState = true
                        }
                    }
                }
            )
        }
    }
}

@Composable
private fun AppNavigationRail(
    navController: NavHostController,
    currentRoute: String?,
    items: List<BottomNavItem>
) {
    NavigationRail(
        containerColor = MaterialTheme.colorScheme.surface,
        modifier = Modifier.fillMaxHeight()
    ) {
        Spacer(modifier = Modifier.height(8.dp))
        items.forEach { item ->
            NavigationRailItem(
                icon = {
                    Icon(
                        if (currentRoute == item.route) item.selectedIcon else item.icon,
                        contentDescription = item.title
                    )
                },
                label = {
                    Text(
                        item.title,
                        fontSize = 11.sp,
                        fontWeight = if (currentRoute == item.route) FontWeight.SemiBold else FontWeight.Normal
                    )
                },
                selected = currentRoute == item.route,
                onClick = {
                    if (currentRoute != item.route) {
                        navController.navigate(item.route) {
                            popUpTo(navController.graph.findStartDestination().id) {
                                saveState = true
                            }
                            launchSingleTop = true
                            restoreState = true
                        }
                    }
                },
                colors = NavigationRailItemDefaults.colors(
                    selectedIconColor = EPayGreen,
                    selectedTextColor = EPayGreen,
                    indicatorColor = EPayGreen.copy(alpha = 0.12f)
                )
            )
        }
    }
}

@Composable
private fun RowScope.AppNavItem(
    item: BottomNavItem,
    selected: Boolean,
    onClick: () -> Unit
) {
    NavigationBarItem(
        icon = {
            Icon(
                if (selected) item.selectedIcon else item.icon,
                contentDescription = item.title
            )
        },
        label = {
            Text(
                item.title,
                fontSize = 11.sp,
                fontWeight = if (selected) FontWeight.SemiBold else FontWeight.Normal
            )
        },
        selected = selected,
        onClick = onClick,
        colors = NavigationBarItemDefaults.colors(
            selectedIconColor = EPayGreen,
            selectedTextColor = EPayGreen,
            indicatorColor = EPayGreen.copy(alpha = 0.12f)
        )
    )
}
