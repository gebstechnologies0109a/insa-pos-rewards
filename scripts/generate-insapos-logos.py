#!/usr/bin/env python3
"""Generate Android/WebView logo assets from the official INSA POS artwork."""
from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "tools" / "branding" / "insa-pos-logo-source.png"
RES = ROOT / "INSAPOSv2" / "app" / "src" / "main" / "res"
ASSETS = ROOT / "INSAPOSv2" / "app" / "src" / "main" / "assets"

# Legacy launcher icon sizes (px)
LAUNCHER_SIZES = {
    "mipmap-mdpi": 48,
    "mipmap-hdpi": 72,
    "mipmap-xhdpi": 96,
    "mipmap-xxhdpi": 144,
    "mipmap-xxxhdpi": 192,
}
# Adaptive foreground layer canvas sizes (px) — icon in ~66% safe zone
FOREGROUND_SIZES = {
    "mipmap-mdpi": 108,
    "mipmap-hdpi": 162,
    "mipmap-xhdpi": 216,
    "mipmap-xxhdpi": 324,
    "mipmap-xxxhdpi": 432,
}
SAFE_ZONE_RATIO = 0.66


def load_source() -> Image.Image:
    if not SOURCE.exists():
        raise SystemExit(f"Official logo not found: {SOURCE}")
    return Image.open(SOURCE).convert("RGBA")


def remove_outer_white(img: Image.Image, threshold: int = 248) -> Image.Image:
    """Make near-white pixels transparent (outside metallic frame)."""
    px = img.load()
    w, h = img.size
    for y in range(h):
        for x in range(w):
            r, g, b, a = px[x, y]
            if r >= threshold and g >= threshold and b >= threshold:
                px[x, y] = (r, g, b, 0)
    return img


def fit_center(img: Image.Image, canvas: int, scale_ratio: float = 1.0) -> Image.Image:
    target = max(1, int(canvas * scale_ratio))
    fitted = img.copy()
    fitted.thumbnail((target, target), Image.Resampling.LANCZOS)
    out = Image.new("RGBA", (canvas, canvas), (0, 0, 0, 0))
    ox = (canvas - fitted.width) // 2
    oy = (canvas - fitted.height) // 2
    out.paste(fitted, (ox, oy), fitted)
    return out


def scale_square(img: Image.Image, size: int) -> Image.Image:
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    fitted = img.copy()
    fitted.thumbnail((size, size), Image.Resampling.LANCZOS)
    ox = (size - fitted.width) // 2
    oy = (size - fitted.height) // 2
    out.paste(fitted, (ox, oy), fitted)
    return out


def padded_logo(img: Image.Image, size: int, pad_ratio: float = 0.08) -> Image.Image:
    inner = max(1, int(size * (1 - pad_ratio * 2)))
    fitted = img.copy()
    fitted.thumbnail((inner, inner), Image.Resampling.LANCZOS)
    out = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    ox = (size - fitted.width) // 2
    oy = (size - fitted.height) // 2
    out.paste(fitted, (ox, oy), fitted)
    return out


def save_png(img: Image.Image, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    if img.mode != "RGBA":
        img = img.convert("RGBA")
    img.save(path, "PNG", optimize=True)


def main() -> None:
    raw = load_source()
    transparent = remove_outer_white(raw.copy())

    for folder, size in LAUNCHER_SIZES.items():
        icon = scale_square(raw, size)
        save_png(icon, RES / folder / "ic_launcher.png")
        save_png(icon, RES / folder / "ic_launcher_round.png")

    for folder, size in FOREGROUND_SIZES.items():
        fg = fit_center(transparent, size, SAFE_ZONE_RATIO)
        save_png(fg, RES / folder / "ic_launcher_foreground.png")

    splash_size = 512
    splash = padded_logo(transparent, splash_size, pad_ratio=0.06)
    save_png(splash, RES / "drawable" / "splash_logo.png")
    save_png(splash, RES / "drawable-night" / "splash_logo.png")

    save_png(padded_logo(transparent, 256, 0.08), ASSETS / "pos-ui" / "img" / "logo.png")
    save_png(padded_logo(transparent, 220, 0.10), ASSETS / "customer-display" / "img" / "logo.png")

    print(f"Generated logo assets from {SOURCE}")


if __name__ == "__main__":
    main()
