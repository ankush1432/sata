# Phase 14: Subscription Lifecycle & Authoritative Protection Reconciliation Specification

## Overview
Phase 14 delivers authoritative server-side subscription lifecycle gating for SATA (Smart Anti-Theft Application). The backend is the single source of truth for protection capability, enforcing that protection services are strictly disabled when a subscription is expired or inactive, while guaranteeing user account and archive data integrity.

---

## 1. Authoritative Backend States (`GET /api/v1/subscription/current`)

The application consumes `GET /api/v1/subscription/current` and evaluates the 4 authoritative states:

| State | Status | Protection Status | Description |
| :--- | :--- | :--- | :--- |
| **`ACTIVE`** | Active | `ACTIVE` | Full anti-theft defense operational. All services, sensors, alarms, and tracking permitted. |
| **`EXPIRING`** | Expiring | `ACTIVE` | Days remaining $\le 7$. Protection remains active, but proactive renewal warnings are presented. |
| **`EXPIRED`** | Expired | `INACTIVE` | Current timestamp past `expires_at`. Protection services are forced off and cannot be armed. |
| **`INACTIVE`** | Inactive | `INACTIVE` | No valid subscription plan exists or subscription has been revoked/disabled. |

---

## 2. Data Preservation & Non-Deletion Invariant

**CRITICAL RULE: When a subscription expires, user account/data must NOT be deleted.**

Under no circumstances does expiration trigger deletion of:
- **User Account**: Profile, phone number, email, credentials remain valid.
- **Trusted Contacts**: Up to 2 verified emergency contacts remain saved and accessible.
- **Registered Devices**: Device hardware reference, push token, and model remain linked.
- **Security Events**: Incident logs, GPS telemetry, timestamps are preserved.
- **Evidence Archives**: Private disk encrypted front camera photos and audio clips are intact.
- **Subscription History**: Historical receipts, plans, and activation codes are readable.

Only real-time background protection services (motion sensor, proximity monitoring, automated sirens, emergency beacons) become inactive.

---

## 3. Permissions Matrix: Allowed vs Blocked Operations

| User Operation | `ACTIVE` / `EXPIRING` | `EXPIRED` / `INACTIVE` |
| :--- | :---: | :---: |
| **Login & Session Management** | Allowed | **Allowed** |
| **User Account & Profile Access** (`/profile`) | Allowed | **Allowed** |
| **Subscription History Review** (`/settings`, `/subscription/history`) | Allowed | **Allowed** |
| **Plan Activation & Code Renewal** (`/activate`) | Allowed | **Allowed** |
| **Existing Security Events & Evidence Access** (`/security-events`) | Allowed | **Allowed** |
| **Trusted Contacts Management** (`/trusted-users`) | Allowed | **Allowed** |
| **Starting / Arming Protection Services** (`toggleProtection`) | Allowed | **BLOCKED** |
| **Arming Motion & Pocket Sensors** (`arm()`) | Allowed | **BLOCKED** |
| **Dispatched Remote Commands** (`FACTORY_RESET`, `KIOSK_LOCK`) | Evaluated | **BLOCKED** (`DEVICE_INACTIVE`) |

---

## 4. Expired Screen & UI Representation

### Dedicated Screen (`/subscription-expired`)
- **Headline**: `Your protection plan has expired.`
- **Expiry Date**: `Expiry Date: 15 Oct 2026`
- **Primary CTA**: `[ Activate Now ]` (Navigates to `/activate`)
- **Guarantees Card**: Explicitly communicates that account, devices, contacts, and evidence history are safe and preserved.
- **Quick Links**: Direct navigation to `/profile`, `/security-events`, `/trusted-users`, `/settings`.

### Dashboard Banner (`/home`)
When on the dashboard with an expired plan:
- Protection toggle is disabled and displays forced `OFF`.
- Prominent banner displays:
  - *"Your protection plan has expired."*
  - *"Expiry Date: 15 Oct 2026"*
  - `[ Activate Now ]` button immediately navigating to `/activate`.

---

## 5. Renewal Lifecycle Sequence

```
User Enters Activation Code
            │
            ▼
POST /api/v1/activation/activate
            │
            ▼
Backend transitions status to ACTIVE
            │
            ▼
App Refresh (GET /api/v1/subscription/current)
            │
            ▼
State updated to ACTIVE
            │
            ▼
Protection Services become available and toggleable
```

---

## 6. Offline Expiry & Server Reconciliation
- **Offline Persistence**: Secure storage records `StorageKeys.activeSubscriptionStatus = 'expired'` and `subscription_expiry_date = '15 Oct 2026'`.
- **Offline Defense**: If the device boots without internet connectivity, local cache prevents arming protection services.
- **Server Authority**: If local device previously claimed protection was `ON`, but the server indicates `EXPIRED`, the server authoritatively overrides local state, switches protection to `false`, and displays the expired banner.

---

## 7. Automated Test Suite Verification
The integration test suite in `mobile/test/unit/phase14_subscription_lifecycle_integration_test.dart` verifies:
1. **Expired State**: Protection disabled, data preserved, allowed routes accessible.
2. **Expiring State**: Protection remains active while providing renewal notice.
3. **Renewed State**: Backend `ACTIVE` allows immediate resumption of protection on app refresh.
4. **App Restart While Expired**: User account is preserved while forced inactive state is enforced.
5. **Offline Expiry State**: Local storage prevents protection bypass without network connectivity.
6. **Server Status Reconciliation**: Server expiration timestamp authoritatively overrides local device state.
