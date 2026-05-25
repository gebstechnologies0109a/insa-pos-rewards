# INSA POS — Android WebView App

Landscape-only Android WebView wrapper for the INSA POS web application. Inherits all offline capabilities (IndexedDB + Sync Engine) from the web layer.

## Features

- Full-screen landscape mode for tablet POS terminals
- Loads `https://insapos.diybizrewards.com/pos/cashier`
- JavaScript and DOM storage enabled for IndexedDB support
- Cleartext traffic allowed for INSABuddy local communication (127.0.0.1:18181)
- Keep-screen-on prevents display sleep during operations
- Immersive mode hides navigation/status bars
- Offline error page with retry button

## Requirements

- Android 6.0+ (API 23)
- Android Studio Arctic Fox or later
- JDK 8+

## Build Instructions

### Debug APK

```bash
cd INSAPOS
./gradlew assembleDebug
```

Output: `app/build/outputs/apk/debug/app-debug.apk`

### Release APK

1. Generate a signing key:

```bash
keytool -genkey -v -keystore insapos-release.jks \
  -keyalg RSA -keysize 2048 -validity 10000 \
  -alias insapos
```

2. Create `app/keystore.properties`:

```
storeFile=../insapos-release.jks
storePassword=your_password
keyAlias=insapos
keyPassword=your_password
```

3. Build:

```bash
./gradlew assembleRelease
```

Output: `app/build/outputs/apk/release/app-release.apk`

### Install

```bash
adb install app/build/outputs/apk/debug/app-debug.apk
```

## Notes

- The app works alongside INSABuddy — INSABuddy handles hardware (printers, scanners, cash drawers), while this app provides the POS interface.
- Offline sales are stored in IndexedDB within the WebView and sync automatically when connectivity returns.
