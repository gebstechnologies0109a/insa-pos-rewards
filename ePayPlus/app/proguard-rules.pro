# ePayPlus V3.0 ProGuard Rules — Comprehensive Retrofit/R8 fix

# Keep ALL attributes
-keepattributes Signature,InnerClasses,EnclosingMethod,*Annotation*,Exceptions

# Retrofit - CRITICAL: keep all retrofit interfaces and their methods
-keep,allowobfuscation,allowshrinking interface retrofit2.Call
-keep,allowobfuscation,allowshrinking class retrofit2.Response
-keep,allowobfuscation,allowshrinking class kotlin.coroutines.Continuation
-keep class retrofit2.** { *; }
-keepclassmembers,allowshrinking,allowobfuscation interface * {
    @retrofit2.http.* <methods>;
}

# Keep the ENTIRE API service interface
-keep interface com.epayplus.v2.data.remote.EPayApiService { *; }
-keep class com.epayplus.v2.data.remote.EPayApiService { *; }

# Keep ALL model/data classes used with Gson/Retrofit
-keep class com.epayplus.v2.domain.model.** { *; }
-keep class com.epayplus.v2.data.remote.** { *; }
-keep class com.epayplus.v2.data.local.entity.** { *; }

# Gson
-keep class com.google.gson.** { *; }
-keepclassmembers class * {
    @com.google.gson.annotations.SerializedName <fields>;
}

# OkHttp
-dontwarn okhttp3.**
-dontwarn okio.**
-keep class okhttp3.** { *; }

# Kotlin coroutines
-keepnames class kotlinx.coroutines.internal.MainDispatcherFactory {}
-keepnames class kotlinx.coroutines.CoroutineExceptionHandler {}
-keepclassmembers class kotlin.coroutines.** { *; }
-keep class kotlin.coroutines.Continuation { *; }

# Hilt / Dagger
-keep class dagger.** { *; }
-keep class javax.inject.** { *; }
-keep class * extends dagger.hilt.android.internal.managers.ViewComponentManager { *; }

# Room
-keep class * extends androidx.room.RoomDatabase { *; }
-keep @androidx.room.Entity class * { *; }
-keep @androidx.room.Dao interface * { *; }

# General Android
-keep class * extends android.app.Activity
-keep class * extends android.app.Service
-keep class * extends android.content.BroadcastReceiver

# Kotlin metadata
-keep class kotlin.Metadata { *; }
-dontwarn kotlin.**
-dontwarn kotlinx.coroutines.**

# Compose
-dontwarn androidx.compose.**

# Provider logo drawables (release shrinkResources)
-keep class com.epayplus.v2.R$drawable { *; }

# Coil
-dontwarn coil.**
-keep class coil.** { *; }
