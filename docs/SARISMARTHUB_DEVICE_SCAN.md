# SariSmartHub — device scan (2026-05-28)

## Workspace

| Item | Value |
|------|--------|
| **Source repo** | `C:\laragon\www\SariSmartHub` (Android, **not** inside `ePay Plus`) |
| **ePay Plus** | Laravel + `ePayPlus/` Android; no `com.sarismarthub` sources |

## ADB connection

| Method | Result |
|--------|--------|
| WiFi `10.139.7.209:5555` | Failed (timeout) |
| USB | `MTK0002601041044200` — Tab 17 Pro Max |

## Installed app

| Field | Value |
|-------|--------|
| Package | `com.sarismarthub.app` |
| versionName | `1.0` |
| versionCode | `1` |
| Main activity | `com.sarismarthub.app/.MainActivity` |
| Label | SariSmartHub |

### Permissions

- `CAMERA`, `SEND_SMS`, `INTERNET`, `POST_NOTIFICATIONS`, `WAKE_LOCK`, `ACCESS_NETWORK_STATE`, `RECEIVE_BOOT_COMPLETED`, `FOREGROUND_SERVICE`

## Artifacts (ePay Plus repo)

| File | Description |
|------|-------------|
| `docs/device-scans/sarismarthub-screenshot.png` | Home screen capture |
| `docs/device-scans/sarismarthub-logcat.txt` | Last 200 log lines after launch |
| `docs/device-scans/sarismarthub-base.apk` | Pulled APK (~164 MB) |

## Logcat (launch)

- `MainActivity` brought to foreground successfully.
- No app-specific crashes in the captured window.

## Report periods (Android)

Implemented in **SariSmartHub** source (`ui/reports/`):

- Hub: **Reports** from dashboard drawer → period cards `1d` … `12m` + **Customer** report.
- Detail: `period_report/{periodKey}` — sales, profit, utang, chart.
- Customer: `customer_report` — filter by customer + period chips.

Rebuild and install from `C:\laragon\www\SariSmartHub`:

```powershell
cd C:\laragon\www\SariSmartHub
.\gradlew assembleDebug
adb install -r app\build\outputs\apk\debug\app-debug.apk
```

## Deploy note

SariSmartHub is **offline-first** (Room + optional Firestore). No Laravel host in ePay Plus for this package. ePay Plus web reports remain at `/reports` on the epayplus admin host.
