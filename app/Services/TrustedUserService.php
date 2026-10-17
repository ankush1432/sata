<?php

namespace App\Services;

use App\Enums\TrustedUserStatus;
use App\Models\TrustedUser;
use App\Models\TrustedUserVerification;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class TrustedUserService
{
    /**
     * Maximum allowed trusted users per main user.
     */
    public const MAX_TRUSTED_USERS = 2;

    /**
     * Add a trusted user for a given main user.
     * Enforces the maximum 2 limit at the service level.
     */
    public function addTrustedUser(User $user, array $data): TrustedUser
    {
        $currentCount = $user->trustedUsers()->count();
        if ($currentCount >= self::MAX_TRUSTED_USERS) {
            throw new DomainException('Cannot add more than ' . self::MAX_TRUSTED_USERS . ' trusted users per account.');
        }

        $phone = $data['phone'] ?? $data['phone_number'] ?? null;

        // Determine available priority slot (1 or 2)
        $existingPriorities = $user->trustedUsers()->pluck('priority')->all();
        $priority = in_array(1, $existingPriorities, true) ? 2 : 1;

        $verificationToken = Str::random(32);
        $verificationRef = 'TRU-' . strtoupper(Str::random(8));

        $trustedUser = $user->trustedUsers()->create([
            'name' => $data['name'],
            'phone' => $phone,
            'phone_number' => $phone,
            'relationship' => $data['relationship'] ?? null,
            'status' => TrustedUserStatus::PENDING,
            'priority' => $priority,
            'is_active' => true,
            'verification_token' => $verificationToken,
            'verification_reference' => $verificationRef,
        ]);

        TrustedUserVerification::create([
            'trusted_user_id' => $trustedUser->id,
            'token' => $verificationToken,
            'verification_code' => $verificationRef,
            'is_verified' => false,
            'expires_at' => now()->addDays(7),
        ]);

        return $trustedUser;
    }

    /**
     * Get all trusted users for a main user.
     */
    public function getTrustedUsers(User $user): Collection
    {
        return $user->trustedUsers()->orderBy('priority')->get();
    }

    /**
     * Update trusted user details.
     */
    public function updateTrustedUser(User $user, int $trustedUserId, array $data): TrustedUser
    {
        $trustedUser = $user->trustedUsers()->findOrFail($trustedUserId);

        $updates = [];
        if (isset($data['name'])) {
            $updates['name'] = $data['name'];
        }
        if (isset($data['relationship'])) {
            $updates['relationship'] = $data['relationship'];
        }

        $newPhone = $data['phone'] ?? $data['phone_number'] ?? null;
        if ($newPhone && $newPhone !== $trustedUser->phone) {
            $updates['phone'] = $newPhone;
            $updates['phone_number'] = $newPhone;
            // Phone change resets verification
            $updates['status'] = TrustedUserStatus::PENDING;
            $updates['verified_at'] = null;
            $updates['verification_token'] = Str::random(32);
            $updates['verification_reference'] = 'TRU-' . strtoupper(Str::random(8));
        }

        $trustedUser->update($updates);

        return $trustedUser->fresh();
    }

    /**
     * Remove a trusted user.
     */
    public function removeTrustedUser(User $user, int $trustedUserId): bool
    {
        $trustedUser = $user->trustedUsers()->findOrFail($trustedUserId);
        return (bool) $trustedUser->delete();
    }

    /**
     * Verify a trusted user.
     */
    public function verifyTrustedUser(User $user, int $trustedUserId, ?string $token = null): TrustedUser
    {
        $trustedUser = $user->trustedUsers()->findOrFail($trustedUserId);

        if ($token !== null) {
            $validToken = $trustedUser->verification_token === $token
                || $trustedUser->verification_reference === $token
                || $trustedUser->verifications()->where('token', $token)->orWhere('verification_code', $token)->exists();

            if (! $validToken) {
                throw new DomainException('Invalid verification token.');
            }
        }

        $trustedUser->verify();

        return $trustedUser->fresh();
    }
}
