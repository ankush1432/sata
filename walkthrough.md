# Walkthrough & Security Audit: SATA Phase 2 - Admin Authentication & RBAC

## 1. Overview & Architecture

Phase 2 builds directly upon the Phase 1 foundation of the Smart Anti-Theft Application (SATA) Backend. It delivers an enterprise-grade administrative authentication lifecycle, role-based access control (RBAC), fine-grained permission enforcement, and immutable audit logging.

### Architectural Highlights
- **Framework & Runtime**: Laravel 12 (PHP 8.2) + React 18 / Vite TypeScript administrative dashboard.
- **Authentication**: Laravel Sanctum Bearer tokens with sha-256 token hashing (`personal_access_tokens`), expiration handling, and multi-session revocation.
- **Brute-Force Defense**: `Illuminate\Support\Facades\RateLimiter` enforces strict throttle limits (5 requests per minute keyed by normalized email and client IP address).
- **Access Control Matrix**:
  - `SUPER_ADMIN`: Omnipotent root authority across all resources, users, activation plans/codes, admins, and remote commands.
  - `ADMIN`: Operational security authority for user management, activation codes, security events, evidence review, and security command dispatches.
  - `SUPPORT`: Restricted read-only and tier-1 support capabilities with an explicit guardrail blocking destructive device commands (`FACTORY_RESET`, `WIPE`, `KIOSK_LOCK`).
- **Audit Logging Engine**: Integrated `AdminAuditLog` recording actor ID, action verb, target model/ID, client IP, user agent, and sanitized state diffs with all credentials stripped.

---

## 2. Security Threat Model Audit & Findings

| Threat Category | Potential Risk | Mitigation in Implementation | Disposition |
|---|---|---|---|
| **Brute Force & Credential Stuffing** (CWE-307) | Attackers guessing admin passwords via automated dictionary attacks. | `RateLimiter::tooManyAttempts` checks IP+email key before password check. Rejects with `429 Too Many Requests` after 5 failed attempts. | **Mitigated** |
| **Broken Object Level Authorization** (CWE-285) | A support agent accessing or modifying resources reserved for super administrators. | `EnsureAdminHasPermission` middleware checks permission before controller execution. Controller-level guards block destructive commands without elevated permission. | **Mitigated** |
| **Broken Authentication / Zombie Sessions** (CWE-613) | An administrator who was deactivated continues using an active token. | `EnsureAdminIsActive` middleware intercepts every request and verifies `$admin->is_active === true`. Returns `403 ACCOUNT_SUSPENDED`. | **Mitigated** |
| **Information Disclosure / Secret Leakage** (CWE-200) | Password hashes or secrets leaking in responses or audit logs. | Eloquent `$hidden = ['password', 'remember_token']` on `Admin` model. Audit logger strips `password`, `token`, and secret fields. | **Mitigated** |
| **Session Invalidation Failure** (CWE-384) | Old tokens remaining valid after password reset. | `POST /admin/password/change` explicitly calls `$admin->tokens()->delete()` revoking all active sessions immediately. | **Mitigated** |

---

## 3. Implemented API Endpoints

### Admin Authentication & Session Management
- `POST /admin/login` (and `/api/v1/admin/login`): Validates email & password with bcrypt, checks account activation, issues Sanctum bearer token and returns permissions list.
- `GET /admin/me` (and `/api/v1/admin/me`): Returns authenticated admin identity and dynamic RBAC permission list.
- `POST /admin/logout` (and `/api/v1/admin/logout`): Deletes current bearer access token and records audit log.
- `POST /admin/password/change` (and `/api/v1/admin/password/change`): Verifies current password, writes new bcrypt hash, revokes all tokens, and logs security event.

### Admin Resource & RBAC Operations
- `GET /api/v1/admin/admins`: Lists administrative staff (Super Admin only).
- `POST /api/v1/admin/admins`: Provisions new administrator account (Super Admin only).
- `POST /api/v1/admin/security-commands`: Issues remote commands with destructive action validation.
- `GET /api/v1/admin/audit-logs`: Streams paginated immutable audit records.
- `POST /api/v1/admin/users/{id}/disable`: Suspends user access upon suspected fraud or security compromise.

---

## 4. Verification & Automated Test Suite

All 39 tests across 12 test suites are passing green.

```
   PASS  Tests\Unit\ExampleTest
   PASS  Tests\Feature\ActivationAndSubscriptionTest
   PASS  Tests\Feature\AdminAuthAndRbacTest
   PASS  Tests\Feature\AdminManagementTest
   PASS  Tests\Feature\AuthAndOtpTest
   PASS  Tests\Feature\ExampleTest
   PASS  Tests\Feature\HealthTest
   PASS  Tests\Feature\RelationshipsTest
   PASS  Tests\Feature\SecurityCommandTest
   PASS  Tests\Feature\SecurityEventAndEvidenceTest
   PASS  Tests\Feature\SecurityEventTest
   PASS  Tests\Feature\SecurityEvidenceTest
   PASS  Tests\Feature\TrustedUsersTest

Tests:    39 passed (179 assertions)
Duration: 1.16s
```

### Key Verification Cases in `AdminAuthAndRbacTest`
1. `admin can login with valid credentials` -> 200 OK, returns bearer token & permissions array, creates `ADMIN_LOGIN` audit log.
2. `inactive admin cannot login` -> 403 Forbidden with `ADMIN_ACCOUNT_SUSPENDED`.
3. `admin login fails with invalid credentials` -> 401 Unauthorized with `INVALID_CREDENTIALS`.
4. `admin can fetch own identity at admin me` -> 200 OK, returns admin details and assigned role.
5. `admin can change password and old tokens revoked` -> 200 OK, verifies old token rejected on next request.
6. `suspended admin is blocked by active admin middleware` -> 403 Forbidden with `ACCOUNT_SUSPENDED`.
7. `super admin has full access` -> Super Admin can access audit logs, admin creation, and sensitive commands.
8. `rbac permission check blocks unauthorized role` -> Support role blocked from audit logs (403 FORBIDDEN).
9. `custom permission grants access override` -> Custom permissions attached to individual admins allow granular access.
10. `support role cannot execute destructive commands without permission` -> Blocked with 403 when attempting `FACTORY_RESET`.
11. `super admin can execute destructive commands` -> 201 Created with dispatched status.
12. `support with destructive permission can execute` -> 201 Created when explicit override permission is assigned.
13. `super admin can manage admins` -> Admin creation and status toggle successfully audited.
14. `admin direct endpoints login and me` -> Direct `/admin/login`, `/admin/me`, `/admin/logout` routes verify successfully.
