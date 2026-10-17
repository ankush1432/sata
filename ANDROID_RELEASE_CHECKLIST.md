# Android Production Release Checklist

## 1. Build & SDK Specifications
- [x] **Target SDK**: Android 14 (API level 34) / Android 15 (API level 35 compliant).
- [x] **Minimum SDK**: Android 8.0 (API level 26).
- [x] **Build Tool**: Gradle 8.x with Android Gradle Plugin (AGP) 8.3+.
- [x] **Java Version**: Java 17 (OpenJDK 17 LTS).
- [x] **NDK**: 26.x LTS for Flutter engine compatibility.

---

## 2. Manifest Permissions & Foreground Services
The following permissions are registered in `mobile/android/app/src/main/AndroidManifest.xml`:
- [x] `android.permission.INTERNET`
- [x] `android.permission.ACCESS_NETWORK_STATE`
- [x] `android.permission.ACCESS_FINE_LOCATION`
- [x] `android.permission.ACCESS_COARSE_LOCATION`
- [x] `android.permission.ACCESS_BACKGROUND_LOCATION` (Required for armed background tracking)
- [x] `android.permission.FOREGROUND_SERVICE`
- [x] `android.permission.FOREGROUND_SERVICE_LOCATION`
- [x] `android.permission.FOREGROUND_SERVICE_SPECIAL_USE` (Security & anti-theft monitoring)
- [x] `android.permission.POST_NOTIFICATIONS` (Android 13+ runtime permission)
- [x] `android.permission.RECEIVE_BOOT_COMPLETED` (Auto-recovers armed protection on device reboot)
- [x] `android.permission.CAMERA` (Intruder selfie capture)
- [x] `android.permission.READ_PHONE_STATE` (SIM slot ICCID change detection)
- [x] `android.permission.REQUEST_IGNORE_BATTERY_OPTIMIZATIONS` (Avoids OS Doze killing when armed)
- [x] `android.permission.WAKE_LOCK`

---

## 3. Google Play Store Compliance & Policy Disclosures
- [x] **Prominent In-App Background Location Disclosure**:
  Displayed prior to requesting `ACCESS_BACKGROUND_LOCATION`. Informs user that location data is collected in the background exclusively to locate lost/stolen hardware and is never sold or shared with third parties.
- [x] **No SMS / Call Log Permissions**:
  Strictly compliant with Google Play restricted permissions policy; SIM change monitoring uses carrier slot metadata without reading SMS or phone logs.
- [x] **Foreground Service Prominent Notification**:
  Ongoing notification displayed while protection is armed (*"SATA Anti-Theft Active"*). Notification is dismissed immediately when protection is turned OFF.
- [x] **Data Safety Form Declaration**:
  - Location: Collected for App Functionality (Lost phone recovery) & Account Management.
  - Photos/Videos: Collected strictly when intruder triggers camera, encrypted on private disk.
  - Personal Info: Name, Phone, Email collected for account and emergency dispatch.

---

## 4. Keystore, Signing & R8 / ProGuard
- [x] **Android Keystore**:
  - `flutter_secure_storage` backed by AES encryption with master key in Android Keystore.
- [x] **Release Keystore Setup**:
  - `android/key.properties` configured with release keystore path, alias, and passwords.
- [x] **R8 Code Shrinking & Obfuscation**:
  - R8 enabled in `android/app/build.gradle` (`minifyEnabled true`, `shrinkResources true`).
  - ProGuard rules retain model serialization (`@Keep` annotations for Dio/Retrofit models).

---

## 5. Artifact Generation
- Command to assemble signed Android App Bundle (AAB):
  ```bash
  flutter build appbundle --release
  ```
- Command to assemble testing APK:
  ```bash
  flutter build apk --release --split-per-abi
  ```
