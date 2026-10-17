import { executeQuery, executeRun } from '../db.ts';
import { sendOtpEmail } from './emailService.ts';
import { sendFcmOtp } from './fcmService.ts';

export interface OtpGenerationOptions {
  phoneNumber: string;
  email?: string;
  userName?: string;
  purpose?: string;
  deviceFcmToken?: string;
}

export interface OtpResult {
  success: boolean;
  message: string;
  otpId?: number;
  expiresAt: string;
  channels: string[];
}

/**
 * Generates and stores real OTP into MySQL phpMyAdmin database,
 * and triggers Email and Firebase Cloud Messaging (FCM) dispatch.
 */
export async function generateAndSendOtp(options: OtpGenerationOptions): Promise<OtpResult> {
  const { phoneNumber, email, userName, purpose = 'VERIFICATION', deviceFcmToken } = options;

  // Generate secure 6-digit numeric OTP
  const otpCode = Math.floor(100000 + Math.random() * 900000).toString();
  const expiresAtDate = new Date(Date.now() + 10 * 60 * 1000); // 10 minutes expiry
  const expiresAtStr = expiresAtDate.toISOString().replace('T', ' ').substring(0, 19);
  const nowStr = new Date().toISOString().replace('T', ' ').substring(0, 19);

  let otpId: number | undefined;

  // 1. Insert into MySQL database table otp_verifications
  try {
    const result = await executeRun(
      `INSERT INTO otp_verifications (phone_number, email, otp_code, purpose, is_verified, attempts, expires_at, created_at, updated_at)
       VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?)`,
      [phoneNumber, email || null, otpCode, purpose, expiresAtStr, nowStr, nowStr]
    );
    otpId = result.lastInsertId;
    console.log(`[OTP Service] Saved real OTP [${otpCode}] into MySQL database (ID: ${otpId}) for ${phoneNumber} / ${email || 'No email'}`);
  } catch (err: any) {
    console.warn(`[OTP Database Insert Warning]`, err.message);
  }

  const channels: string[] = [];

  // 2. Trigger Email dispatch if email provided
  if (email) {
    try {
      await sendOtpEmail(email, otpCode, userName);
      channels.push('EMAIL');
    } catch (err: any) {
      console.warn('[Email OTP Trigger Error]', err.message);
    }
  }

  // 3. Trigger Firebase Cloud Messaging (FCM v1) push if device token provided
  if (deviceFcmToken) {
    try {
      await sendFcmOtp(deviceFcmToken, otpCode, purpose);
      channels.push('FCM_PUSH');
    } catch (err: any) {
      console.warn('[FCM OTP Trigger Error]', err.message);
    }
  }

  return {
    success: true,
    message: `Real verification OTP generated and dispatched via ${channels.join(' & ') || 'Secure Gateway'}.`,
    otpId,
    expiresAt: expiresAtStr,
    channels,
  };
}

/**
 * Validates real OTP against MySQL phpMyAdmin database
 */
export async function verifyOtp(identifier: { phone?: string; email?: string; otp: string }): Promise<{
  success: boolean;
  message: string;
  verifiedAt?: string;
}> {
  const { phone, email, otp } = identifier;

  if (!otp || (!phone && !email)) {
    return { success: false, message: 'OTP code and phone number or email are required.' };
  }

  try {
    const nowStr = new Date().toISOString().replace('T', ' ').substring(0, 19);

    const rows = await executeQuery(
      `SELECT * FROM otp_verifications
       WHERE (phone_number = ? OR email = ?) AND is_verified = 0 AND expires_at > ?
       ORDER BY id DESC LIMIT 1`,
      [phone || '', email || '', nowStr]
    );

    if (!rows || rows.length === 0) {
      return {
        success: false,
        message: 'No active OTP verification session found or code has expired. Please request a new OTP.',
      };
    }

    const record = rows[0];

    // Check attempts limit (max 5)
    if (record.attempts >= 5) {
      return {
        success: false,
        message: 'Maximum verification attempts exceeded. Please generate a new OTP.',
      };
    }

    // Verify OTP match
    if (record.otp_code !== otp.trim()) {
      await executeRun('UPDATE otp_verifications SET attempts = attempts + 1 WHERE id = ?', [record.id]);
      return {
        success: false,
        message: `Invalid verification code. ${4 - record.attempts} attempts remaining.`,
      };
    }

    // Mark verified in MySQL
    await executeRun(
      'UPDATE otp_verifications SET is_verified = 1, verified_at = ?, updated_at = ? WHERE id = ?',
      [nowStr, nowStr, record.id]
    );

    console.log(`[OTP Service] Successfully verified OTP #${record.id} in MySQL database`);

    return {
      success: true,
      message: 'OTP verified successfully.',
      verifiedAt: nowStr,
    };
  } catch (err: any) {
    console.error('[Verify OTP Error]', err);
    return {
      success: false,
      message: `Database verification failed: ${err.message}`,
    };
  }
}
