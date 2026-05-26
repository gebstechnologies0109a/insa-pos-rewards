# ePayPlus V2.0

All-in-One Mobile Loading, Bills Payment & E-Cash Platform for Android

## Features

### Core Services
- **E-Load** - Prepaid mobile loading for all Philippine networks (Globe, Smart, TNT, DITO, TM)
- **Bills Payment** - Pay utility bills (Meralco, Maynilad, PLDT, Sky, SSS, Pag-IBIG, PhilHealth)
- **E-Cash / Cash-In** - Wallet top-ups (GCash, Maya, Coins.ph, GrabPay, ShopeePay)
- **WiFi Vendo** - Piso WiFi voucher generation and management

### Business Features
- **Kiosk Mode** - Self-service terminal with device lock-down
- **Sales Reports** - Daily/weekly/monthly sales tracking and analytics
- **Transaction History** - Complete transaction records with search and filter
- **Bluetooth Printer** - ESC/POS thermal receipt printing
- **SMS Integration** - SMS-based loading with auto-response parsing
- **Multi-SIM Support** - Select SIM slot for SMS sending

### Technical Features
- **Offline-First** - Local Room database with server sync
- **Background Sync** - Automatic transaction sync when connectivity returns
- **Device Admin** - Kiosk lock-down with device admin receiver
- **Boot Persistence** - Auto-start service on device boot

## Tech Stack

| Component | Technology |
|-----------|-----------|
| Language | Kotlin 1.9.22 |
| UI Framework | Jetpack Compose + Material 3 |
| Architecture | MVVM + Clean Architecture |
| DI | Hilt (Dagger) |
| Database | Room |
| Networking | Retrofit + OkHttp |
| Navigation | Navigation Compose |
| Async | Kotlin Coroutines + Flow |
| Build | Gradle 8.5 + AGP 8.2.2 |
| Min SDK | 24 (Android 7.0) |
| Target SDK | 34 (Android 14) |

## Project Structure

```
app/src/main/java/com/epayplus/v2/
├── EPayPlusApp.kt              # Application class
├── di/                         # Dependency Injection
│   └── AppModule.kt
├── data/
│   ├── local/
│   │   ├── EPayDatabase.kt    # Room database
│   │   ├── dao/               # Data Access Objects
│   │   └── entity/            # Database entities
│   ├── remote/
│   │   └── EPayApiService.kt  # Retrofit API
│   └── repository/            # Repository pattern
├── domain/
│   └── model/                 # API/Domain models
├── ui/
│   ├── MainActivity.kt
│   ├── KioskActivity.kt
│   ├── navigation/            # Navigation routes
│   ├── screens/               # Compose screens
│   ├── viewmodel/             # ViewModels
│   ├── theme/                 # Material theme
│   └── components/            # Reusable composables
├── service/                   # Background services
├── receiver/                  # Broadcast receivers
└── util/                      # Utilities
    ├── BluetoothPrinter.kt
    ├── SmsHelper.kt
    ├── WifiVendoHelper.kt
    └── KioskManager.kt
```

## Building

1. Open project in Android Studio Hedgehog or later
2. Sync Gradle files
3. Build → Make Project
4. Run → Run 'app'

### Release APK
```bash
./gradlew assembleRelease
```

## Configuration

### API Server
Update the base URL in `AppModule.kt`:
```kotlin
.baseUrl("https://your-api-server.com/v2/")
```

### Default Products
Products are pre-loaded in `ProductRepository.insertDefaultProducts()`. Modify as needed.

## Version History

| Version | Date | Notes |
|---------|------|-------|
| 2.0.0 | 2026 | Complete rewrite with Jetpack Compose |

## License

Proprietary - ePayPlus Technologies
