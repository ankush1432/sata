# Phase 13: Secure Command Receiver & Platform Capability Matrix Specification

## Overview
Phase 13 establishes the secure client-side command execution engine within SATA (Smart Anti-Theft Application). It receives and executes administrative and emergency security commands dispatched by authorized portal administrators:
- `FACTORY_RESET`
- `KIOSK_LOCK`
- `KIOSK_UNLOCK`
- `WIPE_DATA`

---

## 1. Core Security Commandments
1. **Never claim commands are supported unless the device/platform actually supports them.**
2. **Never fake:**
   - Factory Reset
   - Kiosk Lock
   - Kiosk Unlock
   - Wipe Data
3. **Never execute a command directly from an untrusted notification payload.**
4. **FCM must act as a delivery/wake-up mechanism only, never the sole authorization mechanism.**
5. **For unsupported commands: Return `UNSUPPORTED` with detailed mechanism notes.**

---

## 2. Platform Capability Matrix

| Platform | Privilege Mode | Command Type | Supported? | Native Mechanism / Policy Restriction |
| :--- | :--- | :--- | :--- | :--- |
| **Android** | `DEVICE_OWNER` | `FACTORY_RESET` | **YES** | `DevicePolicyManager.wipeData(0)` |
| **Android** | `DEVICE_OWNER` | `KIOSK_LOCK` | **YES** | `DevicePolicyManager.setLockTaskPackages()` & `startLockTask()` |
| **Android** | `DEVICE_OWNER` | `KIOSK_UNLOCK` | **YES** | `Activity.stopLockTask()` |
| **Android** | `DEVICE_OWNER` | `WIPE_DATA` | **YES** | `DevicePolicyManager.wipeData(WIPE_EXTERNAL_STORAGE)` |
| **Android** | `DEVICE_ADMIN` | `FACTORY_RESET` | **YES** | `DevicePolicyManager.wipeData(0)` (Legacy Device Admin) |
| **Android** | `DEVICE_ADMIN` | `KIOSK_LOCK` | **NO** | `UNSUPPORTED`: LockTask screen pinning requires Device Owner provisioning, not granted by legacy Device Admin. |
| **Android** | `DEVICE_ADMIN` | `KIOSK_UNLOCK` | **NO** | `UNSUPPORTED`: Not available without Device Owner privileges. |
| **Android** | `DEVICE_ADMIN` | `WIPE_DATA` | **YES** | `DevicePolicyManager.wipeData(WIPE_EXTERNAL_STORAGE)` |
| **Android** | `STANDARD_APP` | All 4 Commands | **NO** | `UNSUPPORTED`: Standard Android user-space sandboxed apps cannot perform factory reset, lock the screen, or erase partitions. Returns `UNSUPPORTED`. |
| **iOS** | `SUPERVISED_MDM`| `FACTORY_RESET` | **NO** | `UNSUPPORTED IN-APP`: Apple iOS prohibits user-space apps from initiating device wipe. Must be sent via Apple MDM APNs `EraseDevice` command to `mdmd` daemon. |
| **iOS** | `SUPERVISED_MDM`| `KIOSK_LOCK` | **YES** | `UIAccessibility.requestGuidedAccessSession(true)` & Autonomous Single App Mode profile. |
| **iOS** | `SUPERVISED_MDM`| `KIOSK_UNLOCK` | **YES** | `UIAccessibility.requestGuidedAccessSession(false)` |
| **iOS** | `SUPERVISED_MDM`| `WIPE_DATA` | **NO** | `UNSUPPORTED IN-APP`: Apple sandbox prevents external partition erasure from application processes. |
| **iOS** | `STANDARD_APP` | All 4 Commands | **NO** | `UNSUPPORTED`: Apple iOS sandbox strictly forbids third-party unmanaged applications from locking or resetting the device. |

---

## 3. The 10-Step Secure Command Pipeline

Every command received by `SecurityCommandService` passes through the following verification sequence:

```
[ Push / Wake-Up (command_uuid reference only) ]
                        │
                        ▼
 1. Authenticate Command (Pull verified payload from GET /api/v1/commands/{uuid} via TLS)
                        │
                        ▼
 2. Verify Device ID (Compare command device_id against local registered device_uuid)
                        │
                        ▼
 3. Verify Command UUID (Validate UUID format and non-empty string)
                        │
                        ▼
 4. Verify Expiry (now() <= expires_at; reject and mark EXPIRED if elapsed)
                        │
                        ▼
 5. Verify Nonce / Replay Protection (Nonce must be unconsumed and command not terminal)
                        │
                        ▼
 6. Verify Subscription / Security State (Device must not be REVOKED or inactive)
                        │
                        ▼
 7. Check Platform Capability (Cross-reference OS platform and privilege mode)
            ├── UNSUPPORTED ──> 9. Send Ack ──> 10. Send Result (status: UNSUPPORTED, faked: false)
            └── SUPPORTED
                        │
                        ▼
 9. Send Acknowledgement (POST /api/v1/commands/{uuid}/acknowledge)
                        │
                        ▼
 8. Execute Supported Action (Invoke native DeviceManagementDriver)
                        │
                        ▼
 10. Send Result (POST /api/v1/commands/{uuid}/result with COMPLETED or FAILED)
```

---

## 4. Command Lifecycle States
- `PENDING`: Created in database, waiting for delivery.
- `SENT`: Dispatched to FCM / Apple APNs.
- `ACKNOWLEDGED`: Received by client device and validated through steps 1–7.
- `EXECUTING`: Hardware driver actively executing policy.
- `COMPLETED`: Hardware driver finished execution successfully.
- `FAILED`: Execution attempted but OS error occurred.
- `EXPIRED`: Command reached client after its expiration timestamp.
- `UNSUPPORTED`: Device platform or privilege level lacks required OS authority; no fake actions executed.

---

## 5. Test Suite Verification
The integration test suite in `mobile/test/unit/phase13_security_commands_integration_test.dart` verifies:
1. Standard app mode returns `UNSUPPORTED` for all 4 commands and records acknowledgments/results without faking.
2. Android `DEVICE_OWNER` mode executes all commands with genuine hardware driver calls.
3. iOS `SUPERVISED_MDM` executes `KIOSK_LOCK`/`KIOSK_UNLOCK`, but safely returns `UNSUPPORTED` for in-app wipe/reset.
4. Step 4 Expiry rejection marks command as `EXPIRED`.
5. Step 5 Replay protection rejects duplicate nonce invocations.
6. FCM wake-up triggers authenticated TLS pull rather than trusting push payload contents.
