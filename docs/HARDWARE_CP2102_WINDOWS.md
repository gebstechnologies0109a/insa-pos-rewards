# CP2102 (VID_10C4 PID_EA60) on Windows

USB-UART bridge used by many ESP32/RS232 adapters. Without the Silicon Labs VCP driver, Device Manager shows **Code 28** (`CM_PROB_FAILED_INSTALL`) and no COM port.

## Driver (official)

- Product page: https://www.silabs.com/developers/usb-to-uart-bridge-vcp-drivers
- Direct ZIP (legacy installer + INF): https://www.silabs.com/documents/public/software/CP210x_Windows_Drivers.zip

Extract and run **`CP210xVCPInstaller_x64.exe`** (64-bit Windows). Installation **requires Administrator** (UAC).

Silent example (elevated PowerShell):

```powershell
$zip = "$env:TEMP\CP210x_Windows_Drivers.zip"
Invoke-WebRequest -Uri "https://www.silabs.com/documents/public/software/CP210x_Windows_Drivers.zip" -OutFile $zip
Expand-Archive $zip -DestinationPath "$env:TEMP\CP210x_extract" -Force
Start-Process "$env:TEMP\CP210x_extract\CP210xVCPInstaller_x64.exe" -ArgumentList "/S" -Verb RunAs -Wait
```

Optional INF-only install (also elevated):

```powershell
pnputil /add-driver "$env:TEMP\CP210x_extract\slabvcp.inf" /install
pnputil /scan-devices
```

If the device still shows Error after install, **unplug and replug** the adapter, or disable/enable it in Device Manager.

## Verify COM port

```powershell
Get-PnpDevice | Where-Object { $_.InstanceId -match "VID_10C4&PID_EA60" }
[System.IO.Ports.SerialPort]::GetPortNames()
```

Expect **Status: OK**, **Class: Ports**, name like `Silicon Labs CP210x USB to UART Bridge (COMx)`.

```powershell
python -m serial.tools.list_ports -v
```

## Python tools (ESP32)

```powershell
pip install pyserial esptool
python -m esptool --port COMx chip-id
```

Put the ESP32 in download mode (BOOT + EN) if `esptool` cannot connect. A working driver still shows COMx even when the chip does not respond.

## Probe results (2026-05-28, COM3)

| Item | Value |
|------|--------|
| PnP | `Silicon Labs CP210x USB to UART Bridge (COM3)` — Status **OK** |
| Chip (esptool, read-only) | **ESP32-D0WD-V3** (rev 3.1), 4MB flash, MAC `b0:cb:d8:8a:68:b8` |
| Firmware banner (9600 8N1) | **DafoxTech ESP Bluetooth module (TOP BA)** v40.1.2, BT name `Fox-B068B8` |
| Typical bill acceptor (TOP BA path) | **TP70** class — pulse output, **high-level anti-fake (counterfeit) detection**; tablet sees totals via Fox SPP, not direct acceptor serial |
| App UART baud | **9600** (115200 shows ROM boot log only) |
| Console | Send `?` + CRLF for banner; `HELP`/`PING`/`AT` not documented on UART |

Re-run: `python scripts/cp210x_serial_probe.py COM3`

## Notes

- **winget**: no CP210x VCP package found in community catalog (as of 2026).
- **Chocolatey**: `silicon-labs-vcp-driver` package not on community feed; use Silicon Labs installer above.
- Service name when loaded: **`silabser`**
