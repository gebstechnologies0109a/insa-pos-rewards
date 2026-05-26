# INSABuddy

Hardware bridge companion app for **INSA POS**. Runs as an Android foreground service exposing a local HTTP API on port `18181` that the INSA POS web application can call.

## Features

- **Thermal Printing** — Bluetooth, USB, LAN/WiFi ESC/POS, and built-in Android POS printers (Sunmi, iMin, Newland)
- **Barcode/QR Scanning** — Camera-based scanning via ZXing
- **Cash Drawer** — ESC/POS pulse command support
- **Device Info** — Battery, network, model, and app version reporting
- **Auto-reconnect** — Printers reconnect automatically every 30 seconds
- **Boot Start** — Service starts automatically on device boot

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/ping` | Health check |
| POST | `/print` | Print raw (base64) or text data |
| POST | `/drawer/open` | Open cash drawer |
| POST | `/scan` | Trigger barcode/QR scan |
| POST | `/scan/continuous` | Toggle continuous scan mode |
| GET | `/device/info` | Get device information |
| GET | `/printer/status` | Get current printer status |
| GET | `/printer/list` | List available printers |
| POST | `/printer/select` | Select a printer |

## Building

### Prerequisites
- Android Studio Arctic Fox or later
- JDK 17
- Android SDK 35

### Debug APK
```bash
cd INSABuddy
./gradlew assembleDebug
```
Output: `app/build/outputs/apk/debug/app-debug.apk`

### Release APK (Signed)
1. Generate a keystore:
```bash
keytool -genkey -v -keystore insabuddy.jks -keyalg RSA -keysize 2048 -validity 10000 -alias insabuddy
```

2. Create `keystore.properties` in project root:
```properties
storeFile=insabuddy.jks
storePassword=your_store_password
keyAlias=insabuddy
keyPassword=your_key_password
```

3. Build:
```bash
./gradlew assembleRelease
```
Output: `app/build/outputs/apk/release/app-release.apk`

### Install on Device
```bash
adb install -r app/build/outputs/apk/debug/app-debug.apk
```

## Minimum SDK
- API 23 (Android 6.0 Marshmallow)

## Permissions Required
- Bluetooth (connect + scan)
- Camera (barcode scanning)
- USB Host (USB printers)
- Internet (local server)
- Foreground Service
- Boot Completed (auto-start)
