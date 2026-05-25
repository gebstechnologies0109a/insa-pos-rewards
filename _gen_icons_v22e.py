"""Generate app icons from the new INSA POS logo for all Android apps."""
from PIL import Image
import os

LOGO = r"C:\Users\Admin\.cursor\projects\c-laragon-www-INSA-POS\assets\c__Users_Admin_AppData_Roaming_Cursor_User_workspaceStorage_empty-window_images_Copilot_20260525_233837-aa084b03-55b7-4c7a-928f-11909b7f3669.png"

APPS = {
    "INSAPOSv2": r"c:\laragon\www\INSA_POS\INSAPOSv2\app\src\main\res",
    "INSABuddy": r"c:\laragon\www\INSA_POS\INSABuddy\app\src\main\res",
    "INSAPOS":   r"c:\laragon\www\INSA_POS\INSAPOS\app\src\main\res",
}

SIZES = {
    "mipmap-mdpi":    (48, 108),
    "mipmap-hdpi":    (72, 162),
    "mipmap-xhdpi":   (96, 216),
    "mipmap-xxhdpi":  (144, 324),
    "mipmap-xxxhdpi": (192, 432),
}

def resize_square(img, size):
    return img.resize((size, size), Image.LANCZOS)

def center_on_canvas(img, size):
    canvas = Image.new("RGBA", (size, size), (0, 0, 0, 0))
    img_copy = img.copy()
    img_copy.thumbnail((int(size * 0.80), int(size * 0.80)), Image.LANCZOS)
    x = (size - img_copy.width) // 2
    y = (size - img_copy.height) // 2
    canvas.paste(img_copy, (x, y), img_copy)
    return canvas

print("Loading logo...")
logo = Image.open(LOGO).convert("RGBA")
print(f"  Size: {logo.size}")

for app_name, res_dir in APPS.items():
    print(f"\n--- {app_name} ---")
    for folder, (launcher_size, fg_size) in SIZES.items():
        d = os.path.join(res_dir, folder)
        os.makedirs(d, exist_ok=True)

        ic = resize_square(logo, launcher_size)
        ic.save(os.path.join(d, "ic_launcher.png"))
        ic.save(os.path.join(d, "ic_launcher_round.png"))

        fg = center_on_canvas(logo, fg_size)
        fg.save(os.path.join(d, "ic_launcher_foreground.png"))

        print(f"  {folder}: launcher={launcher_size}px, fg={fg_size}px")

print("\nDone! New logo applied to all apps.")
