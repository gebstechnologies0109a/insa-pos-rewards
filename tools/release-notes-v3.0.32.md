# INSAPOS v3.0.32

## Allow minimize (leave app)

- Default `insapos_allow_minimize=true`: status and navigation bars stay visible; no `IMMERSIVE_STICKY` re-hide loop.
- Lock task is not entered unless strict kiosk mode (`allow_minimize=false`) and the app was already screen-pinned.
- HOME launcher and Innohi `key_launcher` boot routing unchanged — device still boots into INSAPOS; staff can open Recents or switch apps.

## Kiosk mode (optional)

Set `insapos_allow_minimize` to `false` in app prefs (or via future POS settings UI) to restore sticky fullscreen.
