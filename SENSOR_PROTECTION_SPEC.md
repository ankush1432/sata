# Phase 11: Motion, Proximity & Local Alarm Protection Specification

## Overview
Phase 11 introduces hardware sensor defenses for SATA (Smart Anti-Theft Application):
1. **MotionProtectionService**: Detects unauthorized movement or pickup from surfaces using device accelerometers.
2. **ProximityProtectionService**: Detects unauthorized pocket extraction or bag withdrawal using proximity hardware.
3. **AlarmService**: Synthesizes and sounds a local audible siren upon breach, subject to OS volume limits.

---

## 1. Operating Modes
Both sensor protection services follow a deterministic 3-mode state machine:

```
[ DISABLED ]
     │
     │ arm() (Grace period & baseline calibration)
     ▼
  [ ARMED ]
     │
     │ Threshold validation (Sustained delta / Pocket extraction)
     ▼
[ TRIGGERED ]
     │
     │ Emergency Pipeline:
     │ 1. Record security event (MOTION_DETECTED or PROXIMITY_ALERT)
     │ 2. Capture GPS location if permitted
     │ 3. Trigger local audible siren
     │ 4. Notify backend (POST /api/v1/security-events)
     │ 5. Backend notifies main user & verified trusted contacts
```

---

## 2. Configurable Settings & Thresholds

### 1. Motion Detection
- **Modes**: `DISABLED`, `ARMED`, `TRIGGERED`
- **Arming Delay / Grace Period**: 2-second initial settling delay so the user can place the phone down without tripping the alarm immediately.
- **Configurable Sensitivity Thresholds**:
  - **LOW**: Delta threshold = $4.5\text{ m/s}^2$ (Resistant to desk bumps; triggers on forceful pickup or vehicular movement).
  - **MEDIUM**: Delta threshold = $2.5\text{ m/s}^2$ (Standard; triggers upon lifting phone from table or surface).
  - **HIGH**: Delta threshold = $1.2\text{ m/s}^2$ (Sensitive; triggers upon subtle nudges or tilts).

### 2. Proximity Detection (Anti-Pocket-Pick)
- **Modes**: `DISABLED`, `ARMED`, `TRIGGERED`
- **False Alarm Prevention Architecture**:
  *"Proximity logic should not create false alarms from normal usage."*
  - **Grace Period**: 4-second initial delay allowing the user to insert the phone into a pocket or purse.
  - **Baseline Settling**: The service requires the device to confirm a stable `NEAR` (covered) state for $\ge 1.5$ seconds before the extraction trigger primes.
  - **Extraction Validation**: An alarm is only fired when an armed, settled device is suddenly uncovered (`isNear: false`) without an authorized device unlock.

### 3. Alarm Volume & Acoustic Reality
- **Target Volume**: Configurable slider from $10\%$ to $100\%$ ($0.1$ to $1.0$).
- **Volume Disclaimer (Required)**:
  > *"Local alarm is requested at target volume, subject to OS and hardware limitations. 100% volume cannot be guaranteed due to system Do Not Disturb (DND), EU acoustic hearing protection limits, or hardware silent switches."*

---

## 3. Platform Capabilities & Limitations

| Capability | Android Implementation | Apple iOS Implementation |
| :--- | :--- | :--- |
| **Motion Hardware** | Native hardware accelerometer (`Sensor.TYPE_ACCELEROMETER`). Continuous vector magnitude monitoring. | Apple `CMMotionManager`. Requires foreground or background audio entitlement to poll continuously. |
| **Proximity Hardware** | Hardware IR proximity sensor (`Sensor.TYPE_PROXIMITY`). Reports binary near/far and physical distance in cm. | `UIDevice.isProximityMonitoringEnabled`. Apple restricts proximity sensor to active phone call / audio sessions; non-iPhone iPads have NO proximity hardware. |
| **Audible Alarm Siren** | Streams audio via `AudioManager.STREAM_ALARM`. Subject to system DND rules and EU acoustic limiter directives. | Plays audio via `AVAudioSession` category `.playback`. Subject to physical mute switch and iOS Focus modes. |

---

## 4. Emergency Trigger Pipeline
Upon validated breach:
1. **Local Siren**: `AlarmService.triggerAlarm(volume: targetVolume)` starts synthesized siren loop.
2. **GPS Acquisition**: High-accuracy coordinates queried from `LocationService`.
3. **Payload Formulation**: Event type `MOTION_DETECTED` or `PROXIMITY_ALERT` with measured sensor deltas and volume disclaimers.
4. **Backend Transmission**: `POST /api/v1/security-events`.
5. **Contact Alerts**: Backend dispatches FCM notifications to main user and verified trusted users.
