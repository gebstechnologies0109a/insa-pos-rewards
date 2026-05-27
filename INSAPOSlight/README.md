# INSA POS Lite

Minimal WebView shell for the fastest possible cold start. Use this app when you **do not** need native hardware (USB/Bluetooth printers, offline SQLite sync, or the embedded local HTTP bridge).

## When to use Lite vs v2

| Need | Use |
|------|-----|
| Fastest startup, online-only POS | **INSAPOSlight** |
| USB/Bluetooth printer, cash drawer | **INSAPOSv3** or **INSABuddy** + Lite |
| Offline sales + product cache | **INSAPOSv3** |
| HID barcode scanner (keyboard wedge) | Either (Lite passes keys to WebView) |

## Performance profile

- No foreground service — saves ~200–500 ms on cold start
- No local NanoHTTPD server or SQLite init
- Optimistic WebView load (HTTPS first, HTTP fallback in background)
- DNS warm-up + aggressive WebView cache (`LOAD_CACHE_ELSE_NETWORK`)

## Build

```bash
cd INSAPOSlight
./gradlew assembleDebug
```

APK: `app/build/outputs/apk/debug/app-debug.apk`
