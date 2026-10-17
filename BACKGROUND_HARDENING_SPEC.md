# Phase 15: Background Hardening & Service Manager Specification

## Overview
Phase 15 hardens background service execution, resilience, battery safety, and lifecycle recovery across Android and iOS platforms for SATA (Smart Anti-Theft Application).

---

## 1. Coordinated Services

`ServiceManager` acts as the single central background orchestrator controlling 7 core services:

1. **Location**: Continuous GPS tracking when armed, relaxed interval when stationary, battery-conscious breadcrumbs.
2. **Motion**: Accelerometer threshold listener via `MotionProtectionService` with resting gravity baseline calibration.
3. **Proximity**: Infrared/capacitive proximity detection via `ProximityProtectionService` for pocket extraction defense.
4. **Security Monitoring**: Automated incident pipeline streaming alerts to `POST /api/v1/security-events`.
5. **Heartbeat**: Periodic telemetry heartbeat pinging `POST /api/v1/devices/heartbeat`.
6. **FCM**: Firebase Cloud Messaging listening for silent wake-up payloads and security alarms.
7. **Command Receiver**: Authenticated 10-step remote command engine (`FACTORY_RESET`, `KIOSK_LOCK`, `WIPE_DATA`).

---

## 2. Protection Lifecycle Pipeline

Every app launch, reboot, or state toggle runs through the strict sequential lifecycle:

```
APP START
    │
    ▼
Authenticate (Check valid Sanctum TLS session token)
    │
    ▼
Subscription Check (Authoritative GET /api/v1/subscription/current; halt if EXPIRED or INACTIVE)
    │
    ▼
Device Check (Verify local hardware device UUID is registered & active)
    │
    ▼
Permission Check (Verify Location, Sensor, Notification permissions)
    │
    ▼
Protection State (Check persistent StorageKeys.isProtectionActive)
    ├── OFF ──> Stop Non-Required Services (Sensors, High-Accuracy GPS, Foreground notification)
    └── ON
          │
          ▼
Start Supported Services
  • Start Android Foreground Service with sticky notification
  • Request Ignore Battery Optimizations (Doze avoidance)
  • Arm Motion & Proximity sensors
  • Start High-Accuracy Location tracking
  • Start Heartbeat timer & flush Offline Queue
```

---

## 3. Platform Boundaries & Battery Discipline

### Android Foreground Service & Battery Optimization
- On Android, active protection requires an ongoing Foreground Service notification (*"SATA Anti-Theft Active: Real-time defense operational"*).
- Prompts for `ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS` to prevent aggressive OS Doze mode killing.
- Reboots listen for `android.intent.action.BOOT_COMPLETED` and auto-recover armed defense via `onDeviceReboot()`.

### iOS Background Limitations
- iOS strictly prohibits arbitrary background execution loops and third-party persistent foreground notification services.
- Background execution is constrained to Apple APNs silent push wakeups and Significant Location Change visits.
- `DefaultForegroundServiceAdapter` eliminates illegal background loops on iOS.

### Battery Conservation & No Infinite Loops
- **When Protection is OFF**:
  - Motion accelerometer stream is disarmed.
  - Proximity sensor is stopped.
  - High-accuracy GPS is stopped.
  - Foreground notification is dismissed.
  - FCM and Command Receiver remain in low-power standby.
- **When Subscription Expires**:
  - All protection services are immediately halted.

---

## 4. Resilience, Offline Queue & Duplicate Prevention

### Network Retry & Exponential Backoff (`RetryManager`)
- Implements exponential backoff: $T_{\text{delay}} = \min(T_{\text{max}}, T_{\text{initial}} \cdot 2^{\text{attempt}-1}) + \text{jitter}$.
- Full jitter ($50\%–100\%$) prevents thundering herd on server reconnection.

### Offline Queue & Duplicate Event Prevention (`OfflineTelemetryQueue`)
- When network drops, incident alerts and location breadcrumbs are staged in persistent local storage (`StorageKeys.pendingOfflineSecurityEvents`).
- **Duplicate Prevention**: Ring buffer of sent event UUIDs (`StorageKeys.sentEventUuids`) ensures identical events are never processed or sent twice.
- Automatically flushes upon `onNetworkRestored()`.

---

## 5. Automated Test Suite Verification

The integration test suite in `mobile/test/unit/phase15_background_hardening_integration_test.dart` verifies:
1. **App Killed & Restarted**: Restores persistent protection state and runs lifecycle.
2. **Device Reboot (`BOOT_COMPLETED`)**: Auto-arms services when boot recovery is enabled.
3. **Network Off & Restored**: Queues events offline, enforces duplicate prevention, flushes on reconnection.
4. **Permission Revoked**: Degrades affected service without crashing remaining services.
5. **Subscription Expired**: Immediately disarms all protection services and dismisses notifications.
6. **FCM Unavailable**: Gracefully degrades push delivery to scheduled polling.
7. **Protection OFF Battery Conservation**: Halts active sensors and loops when disarmed.
