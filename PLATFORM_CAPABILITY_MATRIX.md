# SATA Mobile Platform Capability Matrix

## Authoritative Classification Guide
- **`PASS`**: Feature fully implemented, verified, and operational within platform security model.
- **`PLATFORM_LIMITATION`**: OS/vendor sandboxing restricts or prohibits behavior; graceful degradation implemented according to Apple/Android security rules without faking.
- **`UNSUPPORTED`**: OS privilege boundary restricts action; returned as `UNSUPPORTED` to caller without faking.
- **`BLOCKED`**: Operation intentionally gated (e.g. expired subscription, revoked permission, missing hardware).
- **`FAIL`**: Genuine bug or failure (Zero occurrences in release candidate).

---

## 1. Security Commands Matrix

| Command | Android: Standard App | Android: Device Admin | Android: Device Owner | iOS: Standard App | iOS: Supervised MDM | Implementation Mechanism / Limitation Note |
| :--- | :---: | :---: | :---: | :---: | :---: | :--- |
| **`FACTORY_RESET`** | `UNSUPPORTED` | `PASS` | `PASS` | `UNSUPPORTED` | `PLATFORM_LIMITATION` | **Android**: `DevicePolicyManager.wipeData(0)`.<br>**iOS**: Apple strictly forbids third-party in-app wipe. Must be issued by Apple MDM server to `mdmd` daemon via APNs `EraseDevice`. |
| **`KIOSK_LOCK`** | `UNSUPPORTED` | `UNSUPPORTED` | `PASS` | `UNSUPPORTED` | `PASS` | **Android**: `DevicePolicyManager.setLockTaskPackages()` & `startLockTask()` (Requires Device Owner).<br>**iOS**: `UIAccessibility.requestGuidedAccessSession(true)` under Supervised MDM profile. |
| **`KIOSK_UNLOCK`** | `UNSUPPORTED` | `UNSUPPORTED` | `PASS` | `UNSUPPORTED` | `PASS` | **Android**: `Activity.stopLockTask()`.<br>**iOS**: `UIAccessibility.requestGuidedAccessSession(false)`. |
| **`WIPE_DATA`** | `UNSUPPORTED` | `PASS` | `PASS` | `UNSUPPORTED` | `PLATFORM_LIMITATION` | **Android**: `DevicePolicyManager.wipeData(WIPE_EXTERNAL_STORAGE)`.<br>**iOS**: Sandbox strictly isolates app storage; external wiping prohibited. |

---

## 2. Sensor & Anti-Theft Hardware Protection Matrix

| Capability | Android | iOS | Classification / Hardware Policy |
| :--- | :---: | :---: | :--- |
| **Intruder Selfie Photo** | `PASS` | `PLATFORM_LIMITATION` | **Android**: Headless front camera capture via `CameraX` / Android Camera2 API.<br>**iOS**: Apple sandbox forbids capturing camera images in the background or lock-screen without user interactive UI. |
| **SIM Card Swap Detection** | `PASS` | `PLATFORM_LIMITATION` | **Android**: Dual-SIM slot ICCID/IMSI tracking via `SubscriptionManager`.<br>**iOS**: Apple CoreTelephony redacted ICCID/IMSI access since iOS 12 for privacy; app detects carrier name/cellular connectivity transitions. |
| **Motion Accelerometer Protection** | `PASS` | `PASS` (Foreground) / `PLATFORM_LIMITATION` (Background) | **Android**: Continuous accelerometer monitoring in Foreground Service.<br>**iOS**: CoreMotion streaming in foreground; background streaming suspended by iOS unless paired with significant motion or audio mode. |
| **Pocket Extraction Proximity** | `PASS` | `PLATFORM_LIMITATION` | **Android**: Dedicated hardware infrared/capacitive `Sensor.TYPE_PROXIMITY`.<br>**iOS**: `UIDevice.current.isProximityMonitoringEnabled` is restricted to active screen foreground sessions; disabled by iOS when screen is locked. |
| **Continuous Location Tracking** | `PASS` | `PASS` | **Android**: Foreground Service with sticky notification (`ACCESS_BACKGROUND_LOCATION`).<br>**iOS**: CoreLocation `allowsBackgroundLocationUpdates` with background location blue indicator. |
| **Device Reboot Recovery** | `PASS` | `PLATFORM_LIMITATION` | **Android**: `android.intent.action.BOOT_COMPLETED` BroadcastReceiver auto-recovers armed protection on boot.<br>**iOS**: No general boot broadcast receiver; woken up via APNs push or significant location visits. |

---

## 3. Core Application & Lifecycle Matrix

| Module / Feature | Android | iOS | Status | Notes |
| :--- | :---: | :---: | :---: | :--- |
| **OTP Auth & Session Restore** | `PASS` | `PASS` | `PASS` | TLS Bearer token securely persisted in Android Keystore / iOS Keychain. |
| **Plan Activation & Renewal** | `PASS` | `PASS` | `PASS` | Full code validation: `POST /api/v1/activation/activate`. |
| **Authoritative Subscription Gating** | `PASS` | `PASS` | `PASS` | `GET /api/v1/subscription/current` dictates protection state. Data never deleted on expiry. |
| **Trusted Emergency Contacts** | `PASS` | `PASS` | `PASS` | Strictly capped at maximum 2 verified contacts. |
| **Private Evidence Storage** | `PASS` | `PASS` | `PASS` | Short-lived signed URLs; zero-fill memory sanitization; temporary local cache wiped post-upload. |
| **Offline Telemetry Queue** | `PASS` | `PASS` | `PASS` | Persistent queue with duplicate event prevention. |
| **Exponential Backoff Retry** | `PASS` | `PASS` | `PASS` | Jitter-enabled retry manager prevents server overload. |
