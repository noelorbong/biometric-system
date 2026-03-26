<?php

namespace App\Http\Controllers;

use App\Models\License;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseController extends Controller
{
    public function __construct(private LicenseService $licenseService) {}

    private function getOrCreateLicense(): License
    {
        $license = License::first();

        if (!$license) {
            $license = License::create([
                'machine_fingerprint' => $this->licenseService->generateFingerprint(),
            ]);
        }

        return $license;
    }

    public function status(Request $request): JsonResponse
    {
        $license   = $this->getOrCreateLicense();
        $trialDays = (int) config('keygen.trial_days', 7);

        // Valid stored license → licensed
        if ($license->license_key && $license->license_id) {
            $licenseExpiry   = $license->license_expiry;
            $licenseDaysLeft = null;

            if ($licenseExpiry) {
                $diff = (int) ceil(now()->diffInSeconds($licenseExpiry, false) / 86400);
                $licenseDaysLeft = max(0, $diff);
            }

            return response()->json([
                'status'            => 'licensed',
                'license_key'       => $license->license_key,
                'license_expiry'    => $licenseExpiry?->toISOString(),
                'license_days_left' => $licenseDaysLeft,
            ]);
        }

        // No license → expired
        return response()->json([
            'status'      => 'expired',
            'license_key' => null,
        ]);
    }

    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'min:5'],
        ]);

        $license     = $this->getOrCreateLicense();
        $key         = trim($validated['key']);
        $fingerprint = $license->machine_fingerprint ?: $this->licenseService->generateFingerprint();

        // Ensure fingerprint is persisted before we use it
        if (!$license->machine_fingerprint) {
            $license->update(['machine_fingerprint' => $fingerprint]);
        }

        $validation = $this->licenseService->validateKey($key, $fingerprint);

        if (!$validation['valid']) {
            $message = match ($validation['code']) {
                'NOT_FOUND'                              => 'License key not found.',
                'SUSPENDED'                              => 'This license has been suspended.',
                'EXPIRED'                                => 'This license has expired.',
                'FINGERPRINT_SCOPE_REQUIRED'             => 'This license requires machine validation. Please try again.',
                'FINGERPRINT_SCOPE_MISMATCH'             => 'This license is already activated on a different machine.',
                'TOO_MANY_MACHINES'                      => 'This license has reached its machine activation limit.',
                'CONNECTION_ERROR'                       => 'Unable to reach the licensing server. Please check your internet connection and try again.',
                default                                  => 'Invalid license key (' . $validation['code'] . ').',
            };

            return response()->json(['message' => $message], 422);
        }

        $licenseId  = $validation['license_id'];

        $activation = $this->licenseService->activateMachine($licenseId, $fingerprint);

        if (!$activation['success']) {
            return response()->json(['message' => $activation['error'] ?? 'Machine activation failed.'], 422);
        }

        $expiryRaw    = $validation['expiry'] ?? null;
        $licenseExpiry = $expiryRaw ? \Carbon\Carbon::parse($expiryRaw) : null;

        $license->update([
            'license_key'         => $key,
            'license_id'          => $licenseId,
            'machine_id'          => $activation['machine_id'] ?? $license->machine_id,
            'machine_fingerprint' => $fingerprint,
            'license_expiry'      => $licenseExpiry,
        ]);

        $licenseDaysLeft = null;
        if ($licenseExpiry) {
            $diff = (int) ceil(now()->diffInSeconds($licenseExpiry, false) / 86400);
            $licenseDaysLeft = max(0, $diff);
        }

        return response()->json([
            'message'           => 'License activated successfully.',
            'status'            => 'licensed',
            'license_key'       => $key,
            'license_expiry'    => $licenseExpiry?->toISOString(),
            'license_days_left' => $licenseDaysLeft,
        ]);
    }

    public function deactivate(Request $request): JsonResponse
    {
        $license = License::first();

        if ($license) {
            $license->update([
                'license_key' => null,
                'license_id'  => null,
                'machine_id'  => null,
            ]);
        }

        return response()->json(['message' => 'License removed successfully.']);
    }
}
