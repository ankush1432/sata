# Phase 12: Security Events & Evidence Lifecycle Specification

## Overview
Phase 12 delivers the full lifecycle for Security Events and Private Evidence Artifacts within SATA (Smart Anti-Theft Application).

---

## 1. REST API Endpoints Integrated

| Method | Endpoint | Description |
| :--- | :--- | :--- |
| `POST` | `/api/v1/security-events` | Ingests new security telemetry (Intruder, SIM, Motion, Proximity) with optional multipart photo evidence. |
| `GET` | `/api/v1/security-events` | Lists security events for authenticated user with pagination, category filter (`event_type`), and search. |
| `GET` | `/api/v1/security-events/{id}` | Retrieves detailed event profile including device info, location coordinates, address, and evidence list. |
| `POST` | `/api/v1/security-events/{id}/evidence` | Secure upload of additional evidence artifacts with upload progress and private storage disk writing. |
| `GET` | `/api/v1/evidence/{id}/signed-url` | Generates short-lived, cryptographically signed URLs for private authenticated viewing. |
| `GET` | `/api/v1/evidence/{id}` | Streams encrypted evidence directly to authorized users (owner/admin) via Bearer token authentication. |

---

## 2. Event Types & Representation

The Security Events Screen (`/security-events`) supports the 6 core security event types:

1. **Intruder Detected** (`INTRUDER_DETECTED` / `INTRUDER_PHOTO_CAPTURED`): Lock-screen PIN failure or unauthorized physical intrusion.
2. **SIM Changed** (`SIM_CHANGED`): Unauthorized SIM extraction or SIM swap.
3. **Motion Detected** (`MOTION_DETECTED`): Accelerometer delta threshold breach while armed.
4. **Remote Command** (`REMOTE_COMMAND`): Siren or emergency lock triggered via trusted portal.
5. **Protection Enabled** (`PROTECTION_ENABLED`): Anti-theft protection armed by the user.
6. **Protection Disabled** (`PROTECTION_DISABLED`): Anti-theft protection disarmed with authorization.

Each event card displays:
- **Event Type** (dedicated icon, formatted title, and severity pill: `CRITICAL`, `HIGH`, `MEDIUM`, `LOW`)
- **Date / Time** (human-readable and relative format)
- **Location** (GPS coordinates and address if captured)
- **Device** (Hardware model, platform, and name)
- **Status** (`REPORTED`, `INVESTIGATING`, `RESOLVED`)
- **Evidence Indicator** (camera badge showing attached evidence count)

---

## 3. Evidence Lifecycle, Upload, and Local Cleanup Strategy

### Secure Upload & Progress
- Uses `FormData` multipart uploads with Dio `onSendProgress` callbacks.
- Real-time linear progress indicator ($0\%$ to $100\%$) rendered in the UI.

### Failure Handling & Retry
- Catches network drops and storage exceptions.
- Displays descriptive error messages with an immediate **Retry** action preserving upload buffers.

### Private Access & Authenticated Viewing
- **Private Disk Storage**: Artifacts are written to `private_evidence` storage disk—never public web-accessible folders.
- **No Permanent URLs**: Permanent public photo URLs are strictly forbidden. Access is only permitted via short-lived temporary signed URLs or authenticated streams (`Authorization: Bearer <token>`).

### Local Cleanup Strategy (No Indefinite Photo Caching)
- `TemporaryEvidenceService`:
  1. **Immediate Post-Upload Wipe**: Temporary image files captured by sensors or camera are deleted immediately after server upload verification (`File.delete()`).
  2. **Memory Sanitization**: In-memory byte buffers are zero-filled before garbage collection.
  3. **30-Minute Eviction Policy**: Background cleanup purges any local cached files older than 30 minutes.
  4. **Session Purge**: Wipes local evidence cache on user logout, application termination, or manual "Purge Cache" button tap.

---

## 4. Notification Deep Link Architecture

When a critical breach occurs, the backend delivers a private FCM push notification containing reference identifiers only:
```
FCM Message (Contains event_id)
      │
      ▼
Notification Tap (`handleNotificationTap`)
      │
      ▼
Resolve Deep Link (`/security-events/${eventId}`)
      │
      ▼
Open Security Event Detail Screen
```

---

## 5. Test Suite Verification
The automated integration test suite in `mobile/test/unit/phase12_security_events_integration_test.dart` verifies:
- `GET /api/v1/security-events` list retrieval and title formatting for all 6 required event types.
- `GET /api/v1/security-events/{id}` detail retrieval with location, device, and evidence data.
- Evidence upload with progress callback tracking.
- Failed upload error handling and retry mechanism.
- FCM notification deep link routing (`FCM -> event_id -> /security-events/:id`).
- Local evidence cache sanitization and zero-fill cleanup strategy.
