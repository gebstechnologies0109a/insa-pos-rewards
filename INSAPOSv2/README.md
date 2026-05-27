# INSA POS v3 (Android)

INSAPOSv2 is the Android WebView shell for INSA POS. **Version 3.0** aligns with the web POS v3 feature set (same `applicationId` `com.insapos.v2` for in-place upgrades from v2 installs).

## Build

```bash
./gradlew assembleDebug
./gradlew assembleRelease
```

APKs are published by GitHub Actions as `INSAPOS-v3.0-debug` and `INSAPOS-v3.0-release`.

## Runtime

- `INSAPOS_DEVICE.version` — from `BuildConfig.VERSION_NAME` (currently `3.0.0`)
- User-Agent suffix: `INSAPOSv3/<version> Android/<release>`
