package com.epayplus.v2.ui.navigation

import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.material3.Scaffold
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.ui.Modifier
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.navArgument
import com.epayplus.v2.ui.screens.*

@Composable
fun KioskNavigation(
    navController: NavHostController,
    onAdminExitRequested: () -> Unit
) {
    val navBackStackEntry by navController.currentBackStackEntryAsState()
    val currentRoute = navBackStackEntry?.destination?.route
    val onKioskRoot = isKioskRootRoute(currentRoute)

    Scaffold(
        bottomBar = {
            if (!onKioskRoot) {
                KioskNavBar(
                    showBack = navController.previousBackStackEntry != null,
                    onBack = { navController.popBackStack() },
                    onHome = { navigateToKioskHome(navController) }
                )
            }
        }
    ) { paddingValues ->
        NavHost(
            navController = navController,
            startDestination = NavRoutes.KioskHome.route,
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues)
        ) {
        val kioskHome: @Composable () -> Unit = {
            KioskHomeScreen(
                navController = navController,
                onAdminExitRequested = onAdminExitRequested
            )
        }

        composable(NavRoutes.KioskHome.route) { kioskHome() }
        // Service flows navigate to "home" after completion — map to kiosk home.
        composable(NavRoutes.Home.route) { kioskHome() }

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

        composable(NavRoutes.MayaNegosyo.route) {
            MayaNegosyoHubScreen(navController = navController)
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
        }
    }
}
