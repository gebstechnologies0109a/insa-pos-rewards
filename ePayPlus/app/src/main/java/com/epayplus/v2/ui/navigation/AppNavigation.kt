package com.epayplus.v2.ui.navigation

import androidx.compose.runtime.Composable
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.navArgument
import com.epayplus.v2.ui.screens.*

@Composable
fun AppNavigation(navController: NavHostController) {
    NavHost(
        navController = navController,
        startDestination = NavRoutes.Home.route
    ) {
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
                navArgument("productId") { type = NavType.LongType },
                navArgument("phoneNumber") { type = NavType.StringType }
            )
        ) { backStackEntry ->
            val productId = backStackEntry.arguments?.getLong("productId") ?: 0L
            val phoneNumber = backStackEntry.arguments?.getString("phoneNumber") ?: ""
            ELoadProcessScreen(navController = navController, productId = productId, phoneNumber = phoneNumber)
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
            arguments = listOf(navArgument("billerCode") { type = NavType.StringType })
        ) { backStackEntry ->
            val billerCode = backStackEntry.arguments?.getString("billerCode") ?: ""
            BillsProcessScreen(navController = navController, billerCode = billerCode)
        }

        composable(NavRoutes.ECashProviders.route) {
            ECashProvidersScreen(navController = navController)
        }

        composable(
            route = NavRoutes.ECashProcess.route,
            arguments = listOf(navArgument("providerCode") { type = NavType.StringType })
        ) { backStackEntry ->
            val providerCode = backStackEntry.arguments?.getString("providerCode") ?: ""
            ECashProcessScreen(navController = navController, providerCode = providerCode)
        }

        composable(NavRoutes.Sales.route) {
            SalesScreen(navController = navController)
        }

        composable(NavRoutes.TransactionHistory.route) {
            TransactionHistoryScreen(navController = navController)
        }

        composable(
            route = NavRoutes.TransactionResult.route,
            arguments = listOf(navArgument("transactionId") { type = NavType.LongType })
        ) { backStackEntry ->
            val transactionId = backStackEntry.arguments?.getLong("transactionId") ?: 0L
            TransactionResultScreen(navController = navController, transactionId = transactionId)
        }

        composable(NavRoutes.Settings.route) {
            SettingsScreen(navController = navController)
        }

        composable(NavRoutes.About.route) {
            AboutScreen(navController = navController)
        }
    }
}
