# NanoHTTPD
-keep class fi.iki.elonen.** { *; }
-dontwarn fi.iki.elonen.**

# ZXing
-keep class com.google.zxing.** { *; }
-dontwarn com.google.zxing.**

# JS Interface
-keepclassmembers class com.insapos.v2.AndroidBridge {
    @android.webkit.JavascriptInterface <methods>;
}

# Offline DB & Sync
-keep class com.insapos.v2.db.** { *; }
-keep class com.insapos.v2.sync.** { *; }

# Printer reflections (BuiltInPrinter)
-keepclassmembers class android.os.** { *; }
