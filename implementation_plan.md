# Implementation Plan: SATA Phase 2 - Secure Admin Authentication & RBAC

## User Intent and Scope
Implement Phase 2 of the Smart Anti-Theft Application (SATA) covering Admin Authentication, Role-Based Access Control (RBAC), Permission Enforcement, and Audit Logging.
- Inspect and respect existing Phase 1 implementation without duplicating existing tables (`admins`, `admin_audit_logs`, `users`, etc.) or authentication mechanisms.
- Implement roles: `SUPER_ADMIN`, `ADMIN`, `SUPPORT`.
- Implement endpoints:
  - `POST /admin/login`
  - `POST /admin/logout`
  - `GET /admin/me`
  - `POST /admin/password/change`
- Implement robust security controls:
  - Throttling & rate limiting on login attempts
  - Disabled admin rejection (403 Forbidden)
  - Secure password hashing (`bcrypt` via Laravel Hash facade)
  - Sanctum token expiration & invalidation
  - Audit logging for every sensitive operation
  - Server-side RBAC policy enforcement for all specified permissions:
    `users.view`, `users.manage`, `users.disable`, `activation_plans.view`, `activation_plans.manage`, `activation_codes.view`, `activation_codes.generate`, `activation_codes.disable`, `subscriptions.view`, `subscriptions.manage`, `devices.view`, `devices.revoke`, `trusted_users.view`, `security_events.view`, `security_evidence.view`, `security_commands.view`, `security_commands.execute`, `notifications.view`, `admins.view`, `admins.manage`, `audit_logs.view`.
  - Ensure `SUPPORT` cannot execute destructive security commands unless explicitly granted.
  - Zero password or secret leakage in responses or logs.

---

## Security Threat Model

### Component Overview
The SATA Admin API handles administrative access to critical anti-theft services, sensitive device location history, remote commands (device lock, siren, wipe), activation codes, and user profiles. The API runs in a PHP/Laravel container with SQLite/MySQL database and Sanctum bearer tokens.

### Entry Points and Untrusted Inputs
| Entry Point | Type | Trusted? | Validation |
|---|---|---|---|
| `POST /admin/login` | HTTP JSON | Untrusted | Email format, required string password, rate-limited via Laravel RateLimiter |
| `POST /admin/logout` | HTTP Auth | Authenticated | Token validity check, revokes bearer token |
| `GET /admin/me` | HTTP Auth | Authenticated | Token validity & active admin check |
| `POST /admin/password/change` | HTTP Auth | Authenticated | Current password confirmation check, strong new password rule |
| Sensitive Admin Routes (`/admin/*`, `/api/v1/admin/*`) | HTTP Auth | Authenticated | Enforced by `auth:sanctum`, `active.admin`, and `permission:<perm>` middleware |

### Trust Boundaries and Auth Assumptions
- **Authentication**: Laravel Sanctum bearer tokens issued strictly upon successful email + bcrypt verification of active admin records.
- **Authorization**: Enforced server-side using `AdminPermission` enum, `hasPermission` checks on `Admin` model, and `permission:` middleware / gate authorization.
- **Implicit trust avoided**: Frontend visibility is NEVER trusted. Every sensitive API endpoint validates authorization server-side.
- **Session/Token Expiry**: Revocation on logout and password change; active status verified on every request.

### Sensitive Data Paths
| Data Type | Source | Destination | Protection |
|---|---|---|---|
| Admin Passwords | User Input | Database | Bcrypt hashing, never stored plaintext, hidden in Eloquent models, scrubbed from audit logs |
| Bearer Tokens | Server Token Generator | Client Header | PlainText token returned only once upon login; hashed in `personal_access_tokens` |
| Security Commands (Lock, Wipe) | Admin Request | Device Dispatch | Restricted to authorized roles; SUPPORT blocked from destructive actions |
| Audit Logs | Admin Actions | `admin_audit_logs` | Contextual logging with IP, user agent, action, before/after payloads (secrets stripped) |

### Privileged Actions
| Action | Location | Guard |
|---|---|---|
| Admin Login | `AdminAuthController@login` | RateLimiter throttle (5/min), `is_active` check, Hash verification |
| Password Change | `AdminAuthController@changePassword` | `auth:sanctum`, current password verification, token revocation |
| Remote Security Command | `AdminSecurityCommandController@execute` | `permission:security_commands.execute`, destructive command validation |
| Disable User Account | `AdminUserController@disable` | `permission:users.disable` |
| Manage Activation Codes | `AdminBatchController`, `AdminCodeController` | `permission:activation_codes.generate`, `permission:activation_codes.disable` |
| Manage Admins | `AdminManagementController` | `permission:admins.manage` (SUPER_ADMIN only) |

### Priority Review Areas
1. **Brute-Force & Credential Stuffing Prevention**: Strict rate-limiting on login by IP + email key.
2. **Access Control (Broken Object & Function Level Authorization)**: Rigid server-side permission checks preventing privilege escalation (e.g. SUPPORT executing WIPE or creating Admins).
3. **Information Disclosure Prevention**: Ensuring `password`, `remember_token`, and authorization secrets never appear in API responses or audit log payloads.
4. **Disabled Admin Invalidation**: Immediate session termination and request rejection when `is_active = false`.

---

## Verification Plan

### Automated Test Suite (`tests/Feature/AdminAuthAndRbacTest.php`)
- **Login Success**: Active admin logs in with valid credentials, receives token and sanitized admin profile.
- **Wrong Password**: Invalid credentials return 401 Unauthorized, increment throttle key, and log failure.
- **Rate Limit**: Exceeding maximum failed attempts returns 429 Too Many Requests.
- **Disabled Admin**: Disabled admin (`is_active = false`) login returns 403 Forbidden; existing token returns 403.
- **Permission Denied**: SUPPORT user attempting privileged operations (`users.disable`, `security_commands.execute`) returns 403 Forbidden.
- **Role Access**: SUPER_ADMIN has full permissions, ADMIN has standard operations, SUPPORT has read-only operations.
- **Logout**: Successfully revokes current token and invalidates subsequent requests.
- **Password Change**: Requires correct current password, verifies new password strength, revokes tokens, logs audit entry.
- **Audit Logging**: Verifies comprehensive audit logs are written with sanitized payloads for login, logout, password change, and administrative operations.

### Security Verification
- **Security Scan**: Inspect all newly created and modified files for common CWE vulnerabilities (XSS, injection, exposed secrets, missing auth boundaries). Resolve any detected issues immediately.
- **Security Audit**: Audit the implementation against the component's threat model (`## Security Threat Model`). Document all findings, dispositions, and remediations in `walkthrough.md` using the `generate-security-audit-report` skill.
