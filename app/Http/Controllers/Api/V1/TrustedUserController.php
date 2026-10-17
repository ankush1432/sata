<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\TrustedUserStatus;
use App\Http\Controllers\Controller;
use App\Models\TrustedUser;
use App\Models\TrustedUserVerification;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class TrustedUserController extends Controller
{
    /**
     * List trusted users for the authenticated user.
     * Rule: User cannot access another user's trusted users.
     * Rule: Do not expose private account data.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $trustedUsers = $user->trustedUsers()
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        return $this->successResponse([
            'trusted_users' => $trustedUsers,
            'count' => $trustedUsers->count(),
            'max_allowed' => 2,
        ]);
    }

    /**
     * Add a new trusted user.
     * Rules:
     * - Maximum 2 trusted users per main user.
     * - Phone validation.
     * - Linked to authenticated main user.
     * - Do not expose private account data.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // Rule: Maximum 2 trusted users
        if ($user->trustedUsers()->count() >= 2) {
            return $this->errorResponse(
                'Maximum limit of 2 trusted users reached. Please remove an existing trusted user first.',
                null,
                422,
                'MAX_TRUSTED_USERS_EXCEEDED'
            );
        }

        // Rule: Phone validation & required fields
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required_without:phone_number', 'nullable', 'string', 'min:8', 'max:20', 'regex:/^\+?[0-9\-\s\(\)]{8,20}$/'],
            'phone_number' => ['required_without:phone', 'nullable', 'string', 'min:8', 'max:20', 'regex:/^\+?[0-9\-\s\(\)]{8,20}$/'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'integer', 'between:1,2'],
        ], [
            'phone.regex' => 'The phone number format is invalid.',
            'phone_number.regex' => 'The phone number format is invalid.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422, 'VALIDATION_FAILED');
        }

        $phone = $request->input('phone') ?? $request->input('phone_number');
        // Clean phone digits & optional leading plus
        $cleanPhone = preg_replace('/[^\+0-9]/', '', $phone);
        if (strlen(preg_replace('/[^\d]/', '', $cleanPhone)) < 7) {
            return $this->errorResponse('Invalid phone number provided.', ['phone' => ['The phone number must contain at least 7 digits.']], 422, 'INVALID_PHONE');
        }

        // Check if phone number is already registered for this user
        $alreadyRegistered = $user->trustedUsers()
            ->where(function ($q) use ($phone, $cleanPhone) {
                $q->where('phone', $phone)
                    ->orWhere('phone_number', $phone)
                    ->orWhere('phone', $cleanPhone)
                    ->orWhere('phone_number', $cleanPhone);
            })
            ->exists();

        if ($alreadyRegistered) {
            return $this->errorResponse(
                'This phone number is already registered as your trusted contact.',
                null,
                422,
                'DUPLICATE_PHONE'
            );
        }

        try {
            $verificationToken = Str::random(32);
            $verificationRef = 'TRU-' . strtoupper(Str::random(8));

            $trustedUser = $user->trustedUsers()->create([
                'name' => $request->input('name'),
                'phone' => $phone,
                'phone_number' => $phone,
                'relationship' => $request->input('relationship'),
                'priority' => $request->input('priority', $user->trustedUsers()->count() + 1),
                'status' => TrustedUserStatus::PENDING,
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

            return $this->successResponse([
                'trusted_user' => $trustedUser->fresh(),
            ], 'Trusted user added successfully.', 201);
        } catch (DomainException $e) {
            return $this->errorResponse($e->getMessage(), null, 422, 'MAX_TRUSTED_USERS_EXCEEDED');
        }
    }

    /**
     * Update trusted user.
     * Rules:
     * - Ownership validation (User cannot access another user's trusted users).
     * - Phone validation.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $trustedUser = TrustedUser::find($id);

        if (! $trustedUser) {
            return $this->errorResponse('Trusted user not found.', null, 404, 'NOT_FOUND');
        }

        // Rule: Ownership validation
        if ($trustedUser->user_id !== $user->id) {
            return $this->errorResponse(
                'Unauthorized. You cannot access another user\'s trusted contact.',
                null,
                403,
                'UNAUTHORIZED_ACCESS'
            );
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'min:8', 'max:20', 'regex:/^\+?[0-9\-\s\(\)]{8,20}$/'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'min:8', 'max:20', 'regex:/^\+?[0-9\-\s\(\)]{8,20}$/'],
            'relationship' => ['nullable', 'string', 'max:50'],
            'priority' => ['nullable', 'integer', 'between:1,2'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422, 'VALIDATION_FAILED');
        }

        $newPhone = $request->input('phone') ?? $request->input('phone_number');
        if ($newPhone) {
            $cleanPhone = preg_replace('/[^\+0-9]/', '', $newPhone);
            if (strlen(preg_replace('/[^\d]/', '', $cleanPhone)) < 7) {
                return $this->errorResponse('Invalid phone number provided.', null, 422, 'INVALID_PHONE');
            }

            // Ensure not conflicting with user's other trusted contact
            $duplicate = $user->trustedUsers()
                ->where('id', '!=', $trustedUser->id)
                ->where(function ($q) use ($newPhone, $cleanPhone) {
                    $q->where('phone', $newPhone)
                        ->orWhere('phone_number', $newPhone)
                        ->orWhere('phone', $cleanPhone)
                        ->orWhere('phone_number', $cleanPhone);
                })
                ->exists();

            if ($duplicate) {
                return $this->errorResponse('This phone number is already used by another trusted contact.', null, 422, 'DUPLICATE_PHONE');
            }
        }

        $updates = [];
        if ($request->has('name')) {
            $updates['name'] = $request->input('name');
        }
        if ($request->has('relationship')) {
            $updates['relationship'] = $request->input('relationship');
        }
        if ($request->has('priority')) {
            $updates['priority'] = $request->input('priority');
        }

        if ($newPhone && $newPhone !== $trustedUser->phone) {
            $updates['phone'] = $newPhone;
            $updates['phone_number'] = $newPhone;
            // Phone update requires re-verification
            $updates['status'] = TrustedUserStatus::PENDING;
            $updates['verified_at'] = null;
            $updates['verification_token'] = Str::random(32);
            $updates['verification_reference'] = 'TRU-' . strtoupper(Str::random(8));
        }

        $trustedUser->update($updates);

        return $this->successResponse([
            'trusted_user' => $trustedUser->fresh(),
        ], 'Trusted user updated successfully.');
    }

    /**
     * Delete a trusted user.
     * Rules:
     * - Ownership validation.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $trustedUser = TrustedUser::find($id);

        if (! $trustedUser) {
            return $this->errorResponse('Trusted user not found.', null, 404, 'NOT_FOUND');
        }

        // Rule: Ownership validation
        if ($trustedUser->user_id !== $user->id) {
            return $this->errorResponse(
                'Unauthorized. You cannot delete another user\'s trusted contact.',
                null,
                403,
                'UNAUTHORIZED_ACCESS'
            );
        }

        $trustedUser->delete();

        return $this->successResponse([
            'id' => $id,
            'deleted' => true,
        ], 'Trusted user removed successfully.');
    }

    /**
     * Verify a trusted user.
     * Rules:
     * - Ownership validation.
     * - Sets verified_at timestamp and status = VERIFIED.
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $trustedUser = TrustedUser::find($id);

        if (! $trustedUser) {
            return $this->errorResponse('Trusted user not found.', null, 404, 'NOT_FOUND');
        }

        // Rule: Ownership validation
        if ($trustedUser->user_id !== $user->id) {
            return $this->errorResponse(
                'Unauthorized. You cannot verify another user\'s trusted contact.',
                null,
                403,
                'UNAUTHORIZED_ACCESS'
            );
        }

        $token = $request->input('token') ?? $request->input('code') ?? $request->input('verification_reference');

        if ($token !== null) {
            $isValid = $trustedUser->verification_token === $token
                || $trustedUser->verification_reference === $token
                || $trustedUser->verifications()
                    ->where(function ($q) use ($token) {
                        $q->where('token', $token)->orWhere('verification_code', $token);
                    })
                    ->exists();

            if (! $isValid) {
                return $this->errorResponse('Invalid verification token or code.', null, 422, 'INVALID_VERIFICATION_TOKEN');
            }
        }

        $trustedUser->verify();

        return $this->successResponse([
            'trusted_user' => $trustedUser->fresh(),
        ], 'Trusted user verified successfully.');
    }
}
