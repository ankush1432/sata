# SATA (Smart Anti-Theft Application) — Complete API Documentation

**Version:** 1.0.0  
**Base URL:** `https://your-domain.com/api/v1`  
**Default Content-Type:** `application/json`  
**Default Accept:** `application/json`  

---

## Table of Contents
1. [Overview & Standards](#1-overview--standards)
2. [Global Error Handling](#2-global-error-handling)
3. [Client API Reference](#3-client-api-reference)
   - [Authentication](#31-authentication)
   - [Activation & Plans](#32-activation--plans)
   - [Subscription](#33-subscription)
   - [Devices](#34-devices)
   - [Trusted Users](#35-trusted-users)
   - [Location & Live Tracking](#36-location--live-tracking)
   - [Security Events & Evidence](#37-security-events--evidence)
   - [Notifications](#38-notifications)
   - [Health & Diagnostics](#39-health--diagnostics)
4. [Admin API Reference](#4-admin-api-reference)
   - [Admin Authentication & Profile](#41-admin-authentication--profile)
   - [User Management](#42-user-management)
   - [Plan Management](#43-plan-management)
   - [Activation Code Management](#44-activation-code-management)
   - [Subscription Management](#45-subscription-management)
   - [Device Oversight & Revocation](#46-device-oversight--revocation)
   - [Trusted User Oversight](#47-trusted-user-oversight)
   - [Security Events & Evidence Review](#48-security-events--evidence-review)
   - [Evidence Download & Signed URLs](#49-evidence-download--signed-urls)
   - [Live Location Session Control](#410-live-location-session-control)
   - [Security Commands & Remote Dispatch](#411-security-commands--remote-dispatch)
   - [System Notifications](#412-system-notifications)
   - [Administrator Accounts & RBAC](#413-administrator-accounts--rbac)
   - [Immutable Audit Logs](#414-immutable-audit-logs)

---

## 1. Overview & Standards

### Authentication Methods
- **Client (Mobile / App):** HTTP Bearer Token issued via Laravel Sanctum upon successful OTP verification or mobile login. Tokens are bound to a specific user and optionally device ID.
  ```http
  Authorization: Bearer <sanctum_personal_access_token>
  ```
- **Admin Control Center:** HTTP Bearer Token issued via Sanctum with admin guard (`auth:sanctum`), requiring `active` admin state and role-based permissions (`SUPER_ADMIN`, `ADMIN`, `SUPPORT`).
  ```http
  Authorization: Bearer <admin_access_token>
  ```
- **Temporary Signed URLs:** HMAC SHA-256 URL signatures generated via `URL::temporarySignedRoute` for secure, time-limited evidence downloading without exposing credentials.

### Standard Request Headers
```http
Content-Type: application/json
Accept: application/json
X-Device-UUID: <optional-client-device-uuid>
Authorization: Bearer <token>
```

---

## 2. Global Error Handling

All SATA endpoints return consistent JSON error envelopes.

| Status Code | Meaning | Description |
| :--- | :--- | :--- |
| `200 OK` | Success | Request succeeded. Returns data payload. |
| `201 Created` | Created | Resource successfully created. |
| `400 Bad Request` | Domain Conflict | Business logic invariant violated (e.g. max trusted users reached). |
| `401 Unauthorized` | Unauthenticated | Missing or expired Bearer token. |
| `403 Forbidden` | Access Denied | Insufficient permissions, protection inactive, or subscription expired. |
| `404 Not Found` | Not Found | Resource or entity does not exist. |
| `422 Unprocessable`| Validation Failure | Input parameters failed form validation rules. |
| `429 Too Many Requests`| Rate Limited | Exceeded per-minute or burst velocity limits. |
| `500 Server Error` | Server Exception | Internal unhandled server error. |

### Error Response Schema Example
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "mobile_number": [
      "The mobile number field is required."
    ]
  }
}
```

---

## 3. Client API Reference

### 3.1 Authentication

#### 1. POST `/api/v1/auth/register`
Initializes user registration and dispatches a 6-digit verification OTP.

- **Auth Required:** No (Public)
- **Rate Limit:** 5 requests / minute per IP
- **Headers:** `Content-Type: application/json`, `Accept: application/json`
- **Request Body:**
  ```json
  {
    "name": "Jane Doe",
    "email": "jane@example.com",
    "mobile_number": "+12025550143",
    "password": "SecurePassword123!",
    "password_confirmation": "SecurePassword123!"
  }
  ```
- **Validation Rules:**
  - `name`: `required|string|max:100`
  - `email`: `required|email|unique:users,email`
  - `mobile_number`: `required|string|regex:/^\+?[1-9]\d{7,14}$/|unique:users,mobile_number`
  - `password`: `required|string|min:8|confirmed`
- **Response `201 Created`:**
  ```json
  {
    "success": true,
    "message": "User registered successfully. Verification OTP dispatched.",
    "data": {
      "user_id": 42,
      "name": "Jane Doe",
      "mobile_number": "+12025550143",
      "status": "PENDING_VERIFICATION",
      "otp_expires_in_seconds": 300
    }
  }
  ```
- **Error Responses:**
  - `422 Unprocessable Entity`: Validation failure or duplicate email/mobile.
  - `429 Too Many Requests`: Rate limit reached.

---

#### 2. POST `/api/v1/auth/verify-otp`
Verifies OTP code to activate the user account and issues a personal access token.

- **Auth Required:** No
- **Rate Limit:** 10 requests / minute per IP (Max 5 failed attempts per OTP record)
- **Headers:** `Content-Type: application/json`
- **Request Body:**
  ```json
  {
    "mobile_number": "+12025550143",
    "otp_code": "849201"
  }
  ```
- **Validation Rules:**
  - `mobile_number`: `required|string`
  - `otp_code`: `required|string|size:6`
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "OTP verified successfully.",
    "data": {
      "token": "1|qX8Z9kL20v...",
      "token_type": "Bearer",
      "user": {
        "id": 42,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "mobile_number": "+12025550143",
        "status": "AWAITING_ACTIVATION"
      }
    }
  }
  ```
- **Error Responses:**
  - `422 Unprocessable Entity`: Invalid or expired OTP.
  - `429 Too Many Requests`: Max attempts exceeded.

---

#### 3. POST `/api/v1/auth/resend-otp`
Invalidates active OTP and dispatches a fresh 6-digit code.

- **Auth Required:** No
- **Rate Limit:** 3 requests / minute per IP & mobile
- **Request Body:**
  ```json
  {
    "mobile_number": "+12025550143"
  }
  ```
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "A fresh verification OTP has been dispatched.",
    "data": {
      "resend_available_in_seconds": 60
    }
  }
  ```

---

#### 4. POST `/api/v1/auth/login`
Authenticates existing user credentials and generates a session Bearer token.

- **Auth Required:** No
- **Rate Limit:** 10 requests / minute per IP
- **Request Body:**
  ```json
  {
    "login": "+12025550143",
    "password": "SecurePassword123!",
    "device_name": "Pixel 8 Pro"
  }
  ```
- **Validation Rules:**
  - `login`: `required|string` (Accepts registered mobile or email)
  - `password`: `required|string`
  - `device_name`: `nullable|string|max:100`
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Login successful.",
    "data": {
      "token": "2|hY71bNcX90...",
      "token_type": "Bearer",
      "user": {
        "id": 42,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "mobile_number": "+12025550143",
        "status": "ACTIVE"
      }
    }
  }
  ```
- **Error Responses:**
  - `401 Unauthorized`: Invalid credentials.
  - `403 Forbidden`: Account is suspended or banned.

---

#### 5. POST `/api/v1/auth/logout`
Revokes the current Bearer token.

- **Auth Required:** Yes (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Successfully logged out. Access token revoked."
  }
  ```

---

#### 6. GET `/api/v1/auth/me`
Retrieves profile, account status, subscription summary, and linked devices.

- **Auth Required:** Yes (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "data": {
      "user": {
        "id": 42,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "mobile_number": "+12025550143",
        "status": "ACTIVE",
        "created_at": "2026-09-20T10:00:00.000000Z"
      },
      "active_subscription": {
        "id": 15,
        "plan_name": "Pro 365 Days",
        "status": "ACTIVE",
        "expires_at": "2027-09-20T10:00:00.000000Z",
        "days_remaining": 357
      },
      "device_count": 1
    }
  }
  ```

---

#### 7. PUT `/api/v1/auth/profile`
Updates authenticated user profile attributes (name, email).

- **Auth Required:** Yes (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`
- **Request Body:**
  ```json
  {
    "name": "Jane Doe Updated",
    "email": "jane.updated@example.com"
  }
  ```
- **Validation Rules:**
  - `name`: `sometimes|required|string|min:2|max:255`
  - `email`: `sometimes|nullable|email|max:255|unique:users,email`
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Profile updated successfully.",
    "data": {
      "user": {
        "id": 42,
        "name": "Jane Doe Updated",
        "email": "jane.updated@example.com",
        "mobile_number": "+12025550143",
        "status": "ACTIVE"
      }
    }
  }
  ```

---

#### 8. POST `/api/v1/auth/change-password`
Updates user account password with current password verification.

- **Auth Required:** Yes (`auth:sanctum`)
- **Headers:** `Authorization: Bearer <token>`
- **Request Body:**
  ```json
  {
    "current_password": "OldPassword123!",
    "new_password": "NewSecurePassword456!",
    "new_password_confirmation": "NewSecurePassword456!"
  }
  ```
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Password changed successfully."
  }
  ```
- **Error Responses:**
  - `422 Unprocessable Entity`: Current password incorrect or new password validation fails.

---

### 3.2 Activation & Plans

#### 1. GET `/api/v1/activation/plans` (alias `/api/v1/plans`)
Lists all publicly available subscription tiers.

- **Auth Required:** No
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 1,
        "name": "30-Day Starter Protection",
        "code": "PLAN-30D",
        "duration_days": 30,
        "price": 4.99,
        "currency": "USD",
        "features": ["Intruder Photo", "Live Location", "Remote Siren"]
      },
      {
        "id": 2,
        "name": "365-Day Annual Shield",
        "code": "PLAN-365D",
        "duration_days": 365,
        "price": 39.99,
        "currency": "USD",
        "features": ["All Features", "Priority FCM", "Unlimited Storage"]
      }
    ]
  }
  ```

---

#### 2. POST `/api/v1/activation/activate`
Redeems an activation code to activate the user's account and initiate protection.

- **Auth Required:** Yes (`auth:sanctum`)
- **Rate Limit:** 10 requests / minute per IP/user
- **Request Body:**
  ```json
  {
    "activation_code": "SATA-9382-ABCD-7712",
    "device_id": 5
  }
  ```
- **Validation Rules:**
  - `activation_code`: `required|string|max:64`
  - `device_id`: `nullable|integer|exists:user_devices,id`
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Activation successful. Device protection initialized.",
    "data": {
      "subscription": {
        "id": 88,
        "plan_code": "PLAN-365D",
        "status": "ACTIVE",
        "starts_at": "2026-09-28T20:25:00.000000Z",
        "expires_at": "2027-09-28T20:25:00.000000Z"
      },
      "user_status": "ACTIVE"
    }
  }
  ```
- **Error Responses:**
  - `422 Unprocessable Entity`: Code already redeemed, expired, or invalid.
  - `429 Too Many Requests`: Rate limit exceeded.

---

### 3.3 Subscription

#### 1. GET `/api/v1/subscription/current`
Returns the active subscription details and feature entitlements.

- **Auth Required:** Yes (`auth:sanctum`)
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "data": {
      "has_active_subscription": true,
      "subscription": {
        "id": 88,
        "plan_name": "365-Day Annual Shield",
        "status": "ACTIVE",
        "starts_at": "2026-09-28T20:25:00.000000Z",
        "expires_at": "2027-09-28T20:25:00.000000Z",
        "days_remaining": 365,
        "auto_renew": false
      }
    }
  }
  ```

---

#### 2. GET `/api/v1/subscription/history`
Returns paginated history of all past and present subscriptions.

- **Auth Required:** Yes (`auth:sanctum`)
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 88,
        "plan_name": "365-Day Annual Shield",
        "status": "ACTIVE",
        "starts_at": "2026-09-28T20:25:00.000000Z",
        "expires_at": "2027-09-28T20:25:00.000000Z"
      },
      {
        "id": 12,
        "plan_name": "30-Day Starter Protection",
        "status": "EXPIRED",
        "starts_at": "2026-08-28T20:25:00.000000Z",
        "expires_at": "2026-09-27T20:25:00.000000Z"
      }
    ]
  }
  ```

---

### 3.4 Devices

#### 1. POST `/api/v1/devices/register`
Registers a new physical mobile device linked to the user account.

- **Auth Required:** Yes (`auth:sanctum`)
- **Request Body:**
  ```json
  {
    "device_uuid": "e5c2b09e-7fa2-4f36-b631-01f2f81498b3",
    "device_name": "Samsung Galaxy S24",
    "platform": "android",
    "os_version": "14.0",
    "app_version": "1.0.4",
    "fcm_token": "fXy1...device_fcm_token"
  }
  ```
- **Validation Rules:**
  - `device_uuid`: `required|string|max:100`
  - `device_name`: `required|string|max:100`
  - `platform`: `required|in:android,ios`
  - `os_version`: `nullable|string|max:30`
  - `app_version`: `nullable|string|max:30`
  - `fcm_token`: `nullable|string|max:512`
- **Response `201 Created`:**
  ```json
  {
    "success": true,
    "message": "Device registered successfully.",
    "data": {
      "device_id": 9,
      "device_uuid": "e5c2b09e-7fa2-4f36-b631-01f2f81498b3",
      "protection_enabled": true,
      "status": "ACTIVE"
    }
  }
  ```

---

#### 2. POST `/api/v1/devices/heartbeat`
Updates device connectivity, battery level, network status, and last seen timestamp.

- **Auth Required:** Yes (`auth:sanctum`)
- **Request Body:**
  ```json
  {
    "device_uuid": "e5c2b09e-7fa2-4f36-b631-01f2f81498b3",
    "battery_level": 84,
    "is_charging": false,
    "network_type": "WIFI",
    "is_gps_enabled": true
  }
  ```
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Heartbeat acknowledged.",
    "data": {
      "server_time": "2026-09-28T20:26:00.000000Z",
      "pending_commands_count": 0
    }
  }
  ```

---

#### 3. POST `/api/v1/devices/fcm-token`
Updates the Firebase Cloud Messaging registration token for push delivery.

- **Auth Required:** Yes (`auth:sanctum`)
- **Request Body:**
  ```json
  {
    "device_uuid": "e5c2b09e-7fa2-4f36-b631-01f2f81498b3",
    "fcm_token": "new_fcm_token_string..."
  }
  ```
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "FCM token updated successfully."
  }
  ```

---

#### 4. DELETE `/api/v1/devices/{id}`
Soft-deletes/unlinks a device from the user account.

- **Auth Required:** Yes (`auth:sanctum`)
- **Authorization:** Authenticated user must own the device record.
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Device unlinked successfully."
  }
  ```

---

### 3.5 Trusted Users

#### 1. POST `/api/v1/trusted-users`
Adds a trusted emergency contact (max 2 active per user).

- **Auth Required:** Yes (`auth:sanctum`)
- **Request Body:**
  ```json
  {
    "name": "Bob Smith",
    "mobile_number": "+12025550199",
    "relationship": "Spouse"
  }
  ```
- **Validation Rules:**
  - `name`: `required|string|max:100`
  - `mobile_number`: `required|string|regex:/^\+?[1-9]\d{7,14}$/`
  - `relationship`: `nullable|string|max:50`
- **Response `201 Created`:**
  ```json
  {
    "success": true,
    "message": "Trusted contact added. Verification code dispatched.",
    "data": {
      "trusted_user_id": 14,
      "slot": 1,
      "name": "Bob Smith",
      "is_verified": false
    }
  }
  ```
- **Error Responses:**
  - `400 Bad Request`: Maximum of 2 trusted users already reached.

---

#### 2. GET `/api/v1/trusted-users`
Lists all trusted users for the authenticated user.

- **Auth Required:** Yes (`auth:sanctum`)
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 14,
        "slot": 1,
        "name": "Bob Smith",
        "mobile_number": "+12025550199",
        "relationship": "Spouse",
        "is_verified": true,
        "created_at": "2026-09-28T19:00:00.000000Z"
      }
    ]
  }
  ```

---

#### 3. PUT `/api/v1/trusted-users/{id}`
Updates details of an existing trusted contact.

- **Auth Required:** Yes (`auth:sanctum`)
- **Request Body:**
  ```json
  {
    "name": "Robert Smith",
    "relationship": "Husband"
  }
  ```
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Trusted contact updated successfully."
  }
  ```

---

#### 4. DELETE `/api/v1/trusted-users/{id}`
Removes a trusted emergency contact.

- **Auth Required:** Yes (`auth:sanctum`)
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Trusted contact removed successfully."
  }
  ```

---

#### 5. POST `/api/v1/trusted-users/{id}/verify`
Verifies the trusted contact via OTP code sent to their mobile number.

- **Auth Required:** Yes (`auth:sanctum`)
- **Request Body:**
  ```json
  {
    "verification_code": "512940"
  }
  ```
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Trusted contact verified successfully.",
    "data": {
      "is_verified": true
    }
  }
  ```

---

### 3.6 Location & Live Tracking

#### 1. POST `/api/v1/location/update`
Transmits periodic or high-accuracy GPS coordinates from a registered device.

- **Auth Required:** Yes (`auth:sanctum`)
- **Authorization:** Device UUID must match a device owned by the authenticated user.
- **Request Body:**
  ```json
  {
    "device_uuid": "e5c2b09e-7fa2-4f36-b631-01f2f81498b3",
    "latitude": 37.774929,
    "longitude": -122.419416,
    "accuracy": 4.5,
    "speed": 1.2,
    "altitude": 15.0,
    "bearing": 180.5,
    "recorded_at": "2026-09-28T20:27:00Z"
  }
  ```
- **Validation Rules:**
  - `device_uuid`: `required|string`
  - `latitude`: `required|numeric|between:-90,90`
  - `longitude`: `required|numeric|between:-180,180`
  - `accuracy`: `nullable|numeric|min:0`
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Location updated successfully.",
    "data": {
      "recorded_at": "2026-09-28T20:27:00.000000Z"
    }
  }
  ```

---

### 3.7 Security Events & Evidence

#### 1. POST `/api/v1/security-events`
Reports a security violation (e.g. `INTRUDER_PHOTO_CAPTURED`, `WRONG_PIN_ATTEMPT`, `SIM_CARD_REMOVED`, `POWER_OFF_ATTEMPT`).

- **Auth Required:** Yes (`auth:sanctum`)
- **Request Body:**
  ```json
  {
    "event_uuid": "9c8a7b6d-5e4f-4a3b-2c1d-0e9f8a7b6c5d",
    "device_uuid": "e5c2b09e-7fa2-4f36-b631-01f2f81498b3",
    "event_type": "INTRUDER_PHOTO_CAPTURED",
    "severity": "CRITICAL",
    "metadata": {
      "wrong_pin_count": 3,
      "camera_used": "FRONT"
    },
    "location": {
      "latitude": 37.774929,
      "longitude": -122.419416,
      "accuracy": 5.0
    }
  }
  ```
- **Response `201 Created`:**
  ```json
  {
    "success": true,
    "message": "Security event reported and emergency notifications queued.",
    "data": {
      "event_id": 104,
      "event_uuid": "9c8a7b6d-5e4f-4a3b-2c1d-0e9f8a7b6c5d",
      "status": "PROCESSED"
    }
  }
  ```

---

#### 2. GET `/api/v1/security-events`
Lists all security events for the authenticated user's account.

- **Auth Required:** Yes (`auth:sanctum`)
- **Query Parameters:** `page`, `per_page`, `event_type`, `severity`
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 104,
        "event_uuid": "9c8a7b6d-5e4f-4a3b-2c1d-0e9f8a7b6c5d",
        "event_type": "INTRUDER_PHOTO_CAPTURED",
        "severity": "CRITICAL",
        "has_evidence": true,
        "created_at": "2026-09-28T20:27:00.000000Z"
      }
    ]
  }
  ```

---

#### 3. GET `/api/v1/security-events/{id}`
Retrieves full details of a specific security event, including linked location and evidence records.

- **Auth Required:** Yes (`auth:sanctum`)
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "data": {
      "id": 104,
      "event_uuid": "9c8a7b6d-5e4f-4a3b-2c1d-0e9f8a7b6c5d",
      "event_type": "INTRUDER_PHOTO_CAPTURED",
      "severity": "CRITICAL",
      "location": {
        "latitude": 37.774929,
        "longitude": -122.419416
      },
      "evidence": [
        {
          "id": 33,
          "file_type": "IMAGE",
          "file_size": 245192,
          "mime_type": "image/jpeg",
          "signed_url": "https://your-domain.com/api/v1/evidence/33/signed-download?expires=1727558820&signature=..."
        }
      ]
    }
  }
  ```

---

### 3.8 Notifications

#### 1. GET `/api/v1/notifications`
Retrieves paginated user notifications.

- **Auth Required:** Yes (`auth:sanctum`)
- **Query Parameters:** `unread_only=true|false`, `page=1`
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 210,
        "title": "Intruder Alert Detected",
        "body": "An intruder photo was captured on your Samsung Galaxy S24.",
        "type": "SECURITY_ALERT",
        "is_read": false,
        "created_at": "2026-09-28T20:27:00.000000Z"
      }
    ]
  }
  ```

---

#### 2. POST `/api/v1/notifications/{id}/read`
Marks a specific notification as read.

- **Auth Required:** Yes (`auth:sanctum`)
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Notification marked as read."
  }
  ```

---

#### 3. POST `/api/v1/notifications/read-all`
Marks all notifications as read for the authenticated user.

- **Auth Required:** Yes (`auth:sanctum`)
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "All notifications marked as read."
  }
  ```

---

### 3.9 Health & Diagnostics

#### 1. GET `/api/v1/health`
System liveness and subsystem health check probe.

- **Auth Required:** No (Public)
- **Response `200 OK`:**
  ```json
  {
    "success": true,
    "message": "Smart Anti-Theft Application (SATA) API is healthy.",
    "data": {
      "status": "ONLINE",
      "services": {
        "database": "connected",
        "storage": "accessible",
        "cache": "operational"
      },
      "version": "1.0.0",
      "environment": "production"
    }
  }
  ```

---

## 4. Admin API Reference

All Admin routes are prefixed with `/api/v1/admin` and require an authenticated Admin token (`auth:sanctum`) with appropriate RBAC roles (`SUPER_ADMIN`, `ADMIN`, `SUPPORT`).

### 4.1 Admin Authentication & Profile

- `POST /api/v1/admin/login`: Authenticates admin credentials (`username`/`password`) and issues admin token.
- `POST /api/v1/admin/logout`: Revokes current admin token.
- `GET /api/v1/admin/profile`: Returns authenticated admin profile, role, and custom permissions.
- `POST /api/v1/admin/password/change`: Updates admin password.

### 4.2 User Management

- `GET /api/v1/admin/users`: Lists users with search (`query`, `status`, `subscription_status`).
- `GET /api/v1/admin/users/{id}`: Returns 360° user profile including devices, subscription, events, and audit trail.
- `PUT /api/v1/admin/users/{id}`: Updates user contact information.
- `POST /api/v1/admin/users/{id}/disable`: Toggles user account active/suspended state.

### 4.3 Plan Management

- `GET /api/v1/admin/plans`: Lists all subscription plans.
- `POST /api/v1/admin/plans`: Creates a new plan (`name`, `code`, `duration_days`, `price`).
- `PUT /api/v1/admin/plans/{id}`: Updates an existing plan.
- `DELETE /api/v1/admin/plans/{id}`: Deletes or archives a plan.

### 4.4 Activation Code Management

- `POST /api/v1/admin/activation-codes/generate`: Batches activation code generation (`plan_id`, `quantity`, `prefix`, `expires_at`).
- `GET /api/v1/admin/activation-codes`: Paginated search with filtering (`status`, `plan_id`, `batch_id`).
- `POST /api/v1/admin/activation-codes/{id}/invalidate`: Revokes an unredeemed activation code.

### 4.5 Subscription Management

- `GET /api/v1/admin/subscriptions`: Lists all user subscriptions.
- `GET /api/v1/admin/subscriptions/{id}`: Detailed view of subscription and renewal history.
- `POST /api/v1/admin/subscriptions/{id}/extend`: Extends validity days for a subscription.
- `POST /api/v1/admin/subscriptions/{id}/cancel`: Immediately cancels an active subscription.

### 4.6 Device Oversight & Revocation

- `GET /api/v1/admin/devices`: Lists all enrolled devices with search and filters.
- `GET /api/v1/admin/devices/{id}`: Full device telemetry, location trail, and command history.
- `POST /api/v1/admin/devices/{id}/revoke`: Immediately revokes device authorization and wipes security keys.

### 4.7 Trusted User Oversight

- `GET /api/v1/admin/trusted-users`: Lists emergency contacts across accounts.
- `POST /api/v1/admin/trusted-users/{id}/verify`: Manually verifies an emergency contact.
- `POST /api/v1/admin/trusted-users/{id}/disable`: Suspends trusted user privileges.

### 4.8 Security Events & Evidence Review

- `GET /api/v1/admin/security-events`: Filtered event feed with high-severity sorting.
- `GET /api/v1/admin/security-events/{id}`: Full forensics package including location and evidence attachments.
- `POST /api/v1/admin/security-events/{id}/review`: Marks incident as reviewed with admin notes.

### 4.9 Evidence Download & Signed URLs

- `GET /api/v1/evidence/{id}/download`: Streams private evidence file with strict authorization checks.
- `POST /api/v1/evidence/{id}/signed-url`: Generates a 15-minute cryptographically signed download URL for authorized viewers.

### 4.10 Live Location Session Control

- `GET /api/v1/admin/users/{id}/location`: Returns current latest coordinates and device battery status.
- `POST /api/v1/admin/users/{id}/location/session`: Initiates an emergency real-time tracking session (TTL: 15–120 minutes).

### 4.11 Security Commands & Remote Dispatch

- `GET /api/v1/admin/security-commands`: Command history and real-time execution statuses.
- `POST /api/v1/admin/users/{user}/devices/{device}/commands`: Dispatches an authoritative remote command (`LOCATE`, `RING_ALARM`, `LOCK_DEVICE`, `UNLOCK_DEVICE`, `CAPTURE_PHOTO`, `FACTORY_RESET`, `WIPE_DATA`).
- `POST /api/v1/admin/security-commands/{command}/transition`: Records device acknowledgment, progress, or failure transitions.

### 4.12 System Notifications

- `GET /api/v1/admin/notifications`: Feed of system notifications dispatched to users.
- `GET /api/v1/admin/notifications/{id}`: Detailed view of delivery status and FCM response codes.

### 4.13 Administrator Accounts & RBAC

- `GET /api/v1/admin/admins`: Lists staff and administrative accounts.
- `POST /api/v1/admin/admins`: Provisions a new admin with specific role and custom permissions.
- `PUT /api/v1/admin/admins/{id}`: Updates admin role or custom permission grants.
- `DELETE /api/v1/admin/admins/{id}`: Revokes administrative access.

### 4.14 Immutable Audit Logs

- `GET /api/v1/admin/audit-logs`: Complete immutable record of all admin actions (`admin_id`, `action`, `resource`, `ip_address`, `changes`).
- `GET /api/v1/admin/audit-logs/{id}`: Single audit trail entry details.
- `GET /api/v1/admin/audit-logs/export`: Exports audit logs as CSV or JSON for compliance.
