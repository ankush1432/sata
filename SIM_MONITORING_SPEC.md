# Phase 10: SIM Security Monitoring & Platform Capability Specification

## Overview
Phase 10 introduces platform-supported SIM and telephony security monitoring for SATA (Smart Anti-Theft Application).

Unauthorized SIM card extraction and SIM swaps are primary threat vectors when a device is lost or stolen. Perpetrators frequently eject the owner's SIM card to prevent SMS/cellular tracking or insert an unauthorized SIM to hijack accounts.

---

## 1. Platform Capability & Privacy Architecture

### Strict Privacy Guarantee (No Raw IMSI / ICCID / Serial Numbers)
* **Pre-Android 10**: Legacy systems permitted apps with `READ_PHONE_STATE` to read IMSI (`getSubscriberId()`) and ICCID (`getSimSerialNumber()`).
* **Modern Android (Android 10 / API 29 through Android 14+ / API 34+)**:
  * Non-carrier consumer applications are **strictly blocked** by the Android OS from accessing raw IMSI, ICCID, or hardware serial numbers/IMEI.
  * Attempting to invoke `getSubscriberId()` or `getSimSerialNumber()` results in a fatal `SecurityException`.
* **Apple iOS (All Versions & iOS 16.4+)**:
  * Apple’s application sandbox has **never** exposed IMSI, ICCID, MSISDN (phone number), or hardware serials to third-party applications. No public APIs exist.
  * In iOS 16.4+, Apple further deprecates and anonymizes `CTCarrier` data to safeguard user privacy.

### Privacy-Preserving Cryptographic Baseline
Instead of forbidden hardware identifiers, SATA monitors permitted telephony signals and derives an un-reversible **SHA-256 fingerprint**:
$$\text{SIM Fingerprint} = \text{SHA-256}(\text{slotIndex} \parallel \text{carrierName} \parallel \text{countryIso} \parallel \text{mcc} \parallel \text{mnc} \parallel \text{isEmbedded} \parallel \text{state})$$

When a SIM card is swapped or ejected, the composite fingerprint changes, triggering an authenticated `SIM_CHANGED` event without ever compromising user or carrier secrets.

---

## 2. Platform Implementations & Limitations Matrix

| Capability / Scenario | Android OS (`AndroidSimMonitoringService`) | Apple iOS (`IOSSimMonitoringService`) |
| :--- | :--- | :--- |
| **Underlying Framework** | Android `TelephonyManager` + `SubscriptionManager` | Apple `CoreTelephony` (`CTTelephonyNetworkInfo`) |
| **Dual SIM Support** | **Full Support**: `SubscriptionManager.getActiveSubscriptionInfoList()` monitors Slot 0 and Slot 1 independently (eSIM + physical). | **Full Support**: `serviceSubscriberCellularProviders` dictionary provides multi-slot provider metadata. |
| **Permission Handling** | Requires `READ_PHONE_STATE` runtime permission. Reports `SimMonitoringStatus.permissionDenied` if refused. | Implicit permission via `CoreTelephony`. No user prompt required. |
| **SIM Swap Detection** | Listens for `ACTION_SIM_CARD_STATE_CHANGED` & `CARRIER_CONFIG_CHANGED`. | Listens for `serviceSubscriberCellularProvidersDidUpdateNotifier`. |
| **SIM Unavailable / Absent** | Detects `SIM_STATE_ABSENT`. Reports `simUnavailable`. | Detects empty provider dictionary or nil carrier. Reports `simUnavailable`. |
| **Airplane Mode** | Listens for `Intent.ACTION_AIRPLANE_MODE_CHANGED`. Suppresses false positive swap alarms while radio is offline. | Detects `serviceCurrentRadioAccessTechnology == nil`. Suppresses false positive swap alarms while radio is offline. |
| **Device Reboot Handling** | Reads pre-reboot fingerprint baseline from encrypted storage (`StorageKeys.lastSimFingerprint`). If baseline matches post-reboot, verifies legitimate boot with no alarm. If fingerprint altered while powered off, dispatches alert with `is_device_reboot: true`. | Reads pre-reboot fingerprint from Keychain/secure storage upon app launch. |
| **Terminated App Limitation** | Manifest-registered `SimStateChangeReceiver` (`ACTION_SIM_STATE_CHANGED`, `BOOT_COMPLETED`) allows background reception on supported OEM configurations. | **Major Limitation**: Apple sandbox prevents terminated consumer apps from waking solely upon SIM card ejection without a background push or user launch. |
| **Non-Cellular Devices** | Wi-Fi-only tablets report `SimMonitoringStatus.unsupported`. | Wi-Fi-only iPads / iPods report `SimMonitoringStatus.unsupported`. |

---

## 3. Emergency Event Payload (`SIM_CHANGED`)

When an unauthorized SIM swap or removal is detected, SATA executes the following pipeline:
1. Gathers current GPS coordinates (`latitude`, `longitude`, `accuracy`) if permissions allow.
2. Resolves unique `device_uuid` from secure storage.
3. Formulates permitted telemetry payload.
4. Transmits `POST /api/v1/security-events`.
5. Backend notifies main user and verified trusted contacts via private FCM alerts.

### Sample Transmitted Telemetry
```json
{
  "event_type": "SIM_CHANGED",
  "severity": "CRITICAL",
  "description": "Unauthorized SIM card swap or reconfiguration detected",
  "device_uuid": "935021df-8414-4f8a-96f8-853f72b80217",
  "latitude": 40.7128,
  "longitude": -74.0060,
  "accuracy": 6.5,
  "metadata": {
    "detection_platform": "android",
    "change_type": "SIM_SWAP_DETECTED",
    "is_dual_sim": true,
    "is_airplane_mode": false,
    "is_device_reboot": false,
    "active_sim_count": 2,
    "sim_slots": [
      {
        "slot_index": 0,
        "carrier_name": "T-Mobile US",
        "country_iso": "us",
        "mcc": "310",
        "mnc": "260",
        "is_embedded": false,
        "state": "READY",
        "slot_fingerprint_hash": "a8f3b20c91de..."
      },
      {
        "slot_index": 1,
        "carrier_name": "Vodafone",
        "country_iso": "gb",
        "mcc": "234",
        "mnc": "15",
        "is_embedded": true,
        "state": "READY",
        "slot_fingerprint_hash": "5c9d11ea84b0..."
      }
    ],
    "sim_fingerprint_hash": "e7b891ac3045...",
    "previous_fingerprint_hash": "91ab76ce004f...",
    "privacy_guarantee": "No raw IMSI, ICCID, or MSISDN exposed or queried.",
    "detected_at": "2026-10-05T15:42:00Z"
  }
}
```

---

## 4. Test Verification Summary
All automated integration tests in `mobile/test/unit/phase10_sim_monitoring_integration_test.dart` verify:
1. `AndroidSimMonitoringService` handles permission denial, arming, baseline storage, and Dual SIM slots.
2. `SIM_CHANGED` event transmission with permitted telemetry and strict exclusion of raw IMSI/ICCID.
3. Airplane mode toggling suppresses false alarms.
4. Device reboot verifies baseline without false alert.
5. Non-cellular hardware correctly identified as `unsupported`.
6. `SimNotifier` UI state management and emergency simulation triggers.
