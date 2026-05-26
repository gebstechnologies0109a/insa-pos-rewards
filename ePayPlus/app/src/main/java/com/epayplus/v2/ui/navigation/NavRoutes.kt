package com.epayplus.v2.ui.navigation

sealed class NavRoutes(val route: String) {
    object Splash : NavRoutes("splash")
    object Setup : NavRoutes("setup")
    object Login : NavRoutes("login")
    object Home : NavRoutes("home")
    object ELoad : NavRoutes("eload")
    object ELoadProviders : NavRoutes("eload/providers")
    object ELoadProducts : NavRoutes("eload/products/{providerCode}") {
        fun createRoute(providerCode: String) = "eload/products/$providerCode"
    }
    object ELoadProcess : NavRoutes("eload/process/{productId}/{phoneNumber}") {
        fun createRoute(productId: Long, phoneNumber: String) = "eload/process/$productId/$phoneNumber"
    }
    object Bills : NavRoutes("bills")
    object BillsCategories : NavRoutes("bills/categories")
    object BillsBillers : NavRoutes("bills/billers/{category}") {
        fun createRoute(category: String) = "bills/billers/$category"
    }
    object BillsProcess : NavRoutes("bills/process/{billerCode}") {
        fun createRoute(billerCode: String) = "bills/process/$billerCode"
    }
    object ECash : NavRoutes("ecash")
    object ECashProviders : NavRoutes("ecash/providers")
    object ECashProcess : NavRoutes("ecash/process/{providerCode}") {
        fun createRoute(providerCode: String) = "ecash/process/$providerCode"
    }
    object Sales : NavRoutes("sales")
    object TransactionHistory : NavRoutes("transactions")
    object TransactionDetail : NavRoutes("transactions/{transactionId}") {
        fun createRoute(transactionId: Long) = "transactions/$transactionId"
    }
    object Settings : NavRoutes("settings")
    object KioskSettings : NavRoutes("settings/kiosk")
    object PrinterSettings : NavRoutes("settings/printer")
    object AccountSettings : NavRoutes("settings/account")
    object About : NavRoutes("about")
    object Kiosk : NavRoutes("kiosk")
    object TransactionResult : NavRoutes("result/{transactionId}") {
        fun createRoute(transactionId: Long) = "result/$transactionId"
    }
}
