package com.epayplus.v2.ui.navigation

sealed class NavRoutes(val route: String) {
    object Login : NavRoutes("login")
    object Home : NavRoutes("home")
    object KioskHome : NavRoutes("kiosk_home")
    object ELoadProviders : NavRoutes("eload/providers")
    object ELoadProducts : NavRoutes("eload/products/{providerCode}") {
        fun createRoute(providerCode: String) = "eload/products/$providerCode"
    }
    object ELoadProcess : NavRoutes("eload/process/{providerCode}/{productId}/{phoneNumber}") {
        fun createRoute(providerCode: String, productId: Long, phoneNumber: String) =
            "eload/process/$providerCode/$productId/$phoneNumber"
    }
    object BillsCategories : NavRoutes("bills/categories")
    object BillsBillers : NavRoutes("bills/billers/{category}") {
        fun createRoute(category: String) = "bills/billers/$category"
    }
    object BillsProcess : NavRoutes("bills/process/{billerCode}/{billerName}") {
        fun createRoute(billerCode: String, billerName: String) =
            "bills/process/$billerCode/$billerName"
    }
    object ECashProviders : NavRoutes("ecash/providers")
    object ECashProcess : NavRoutes("ecash/process/{providerCode}/{providerName}") {
        fun createRoute(providerCode: String, providerName: String) =
            "ecash/process/$providerCode/$providerName"
    }
    object Sales : NavRoutes("sales")
    object Rfid : NavRoutes("rfid")
    object TransactionHistory : NavRoutes("transactions")
    object TransactionResult : NavRoutes("result/{transactionId}/{type}") {
        fun createRoute(transactionId: Long, type: String) = "result/$transactionId/$type"
    }
    object Settings : NavRoutes("settings")
    object ChangePin : NavRoutes("settings/change_pin")
    object About : NavRoutes("about")
}
