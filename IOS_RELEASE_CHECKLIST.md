# iOS Production Release Checklist

## 1. Build & OS Deployment Target
- [x] **Deployment Target**: iOS 15.0+ (iOS 16, 17, and iOS 18 compliant).
- [x] **Architecture**: `arm64`.
- [x] **Xcode Version**: Xcode 15.4+ / Xcode 16.
- [x] **Swift Version**: Swift 5.9+.

---

## 2. Background Modes & Capabilities
Configured in `mobile/ios/Runner/Info.plist` and `Runner.entitlements`:
- [x] **Background Modes (`UIBackgroundModes`)**:
  - `location`: Continuous GPS updates when armed for emergency recovery.
  - `remote-notification`: Apple Push Notification service (APNs) silent wake-up payloads.
  - `fetch`: Periodic background synchronization.
- [x] **Push Notifications**:
  - Registered with Apple Developer portal; APNs auth key configured in backend.
  - `aps-environment` set to `production` for release builds.
- [x] **Keychain Sharing**:
  - Hardware-backed iOS Keychain used by `flutter_secure_storage` with `kSecAttrAccessibleAfterFirstUnlockThisDeviceOnly`.

---

## 3. Info.plist Privacy Usage Descriptions
All Apple required user-facing disclosure strings configured:
- [x] `NSLocationWhenInUseUsageDescription`:
  *"SATA requires your location to display your device position on the secure map and verify emergency recovery coordinates."*
- [x] `NSLocationAlwaysAndWhenInUseUsageDescription`:
  *"SATA requires background location access to transmit emergency beacons and help you recover your stolen device even when the app is closed."*
- [x] `NSCameraUsageDescription`:
  *"SATA requires front camera access to photograph unauthorized intruders attempting to unlock your device."*
- [x] `NSMotionUsageDescription`:
  *"SATA requires accelerometer motion access to detect unauthorized device movements when anti-theft defense is armed."*

---

## 4. App Store Review Guidelines Compliance
- [x] **Guideline 2.5.4 (Multitasking & Background Execution)**:
  - SATA only utilizes background location while anti-theft defense is explicitly armed by the user.
  - When protection is turned OFF, background location streaming is stopped immediately to respect Apple battery guidelines.
- [x] **Guideline 5.1.1 (Data Privacy & Collection)**:
  - In-app disclosures clearly inform users how telemetry and evidence are captured.
  - Short-lived signed URLs protect evidence photos; data is stored on private disks.
- [x] **No Private API Violations**:
  - Uses standard CoreLocation, CoreMotion, and UserNotifications APIs.
  - Respects iOS sandbox boundaries without attempting prohibited background screen recording or lock-screen camera access.

---

## 5. Artifact Generation
- Command to assemble signed iOS IPA for App Store Connect / TestFlight:
  ```bash
  flutter build ipa --release
  ```
- Command to assemble iOS archive:
  ```bash
  flutter build ios --release --no-codesign
  ```
