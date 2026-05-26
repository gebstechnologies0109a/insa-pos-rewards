# Keep the JS bridge interface visible to WebView
-keepclassmembers class com.insapos.light.MainActivity$JsBridge {
    @android.webkit.JavascriptInterface <methods>;
}
-keepattributes JavascriptInterface
