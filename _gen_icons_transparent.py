"""Generate transparent-background app icons for INSAPOS v3 (INSAPOSv2 module) from the logo."""
from PIL import Image
import os, shutil

LOGO = r"C:\Users\Admin\.cursor\projects\c-laragon-www-INSA-POS\assets\c__Users_Admin_AppData_Roaming_Cursor_User_workspaceStorage_empty-window_images_001a_AO_25_May_2026_INSA_POS_Logo-a17801e2-8c37-4a35-9df6-af755219a87b.png"
INSAPOSV2_RES = r"c:\laragon\www\INSA_POS\INSAPOSv2\app\src\main\res"

SIZES = {
    "mipmap-mdpi":    (48, 108),
    "mipmap-hdpi":    (72, 162),
    "mipmap-xhdpi":   (96, 216),
    "mipmap-xxhdpi":  (144, 324),
    "mipmap-xxxhdpi": (192, 432),
}

def remove_black_bg(img):
    """Replace near-black pixels with transparent."""
    img = img.convert("RGBA")
    data = img.getdata()
    new_data = []
    for r, g, b, a in data:
        if r < 30 and g < 30 and b < 30:
            new_data.append((0, 0, 0, 0))
        else:
            new_data.append((r, g, b, a))
    img.putdata(new_data)
    return img

def center_on_square(img, size):
    """Center image on transparent square canvas, maintaining aspect ratio."""
    canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    img_copy = img.copy()
    img_copy.thumbnail((int(size * 0.85), int(size * 0.85)), Image.LANCZOS)
    x = (size - img_copy.width) // 2
    y = (size - img_copy.height) // 2
    canvas.paste(img_copy, (x, y), img_copy)
    return canvas

print("Loading logo...")
logo = Image.open(LOGO)
print(f"  Original size: {logo.size}")

transparent_logo = remove_black_bg(logo)
print("  Black background removed.")

for folder, (launcher_size, fg_size) in SIZES.items():
    d = os.path.join(INSAPOSV2_RES, folder)
    os.makedirs(d, exist_ok=True)

    ic = center_on_square(transparent_logo, launcher_size)
    ic.save(os.path.join(d, "ic_launcher.png"))

    ic_round = center_on_square(transparent_logo, launcher_size)
    ic_round.save(os.path.join(d, "ic_launcher_round.png"))

    fg = center_on_square(transparent_logo, fg_size)
    fg.save(os.path.join(d, "ic_launcher_foreground.png"))

    print(f"  {folder}: launcher={launcher_size}px, fg={fg_size}px")

print("\nDone! All icons generated with transparent background.")
