<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class LicenseService
{
    private string $accountId;
    private string $productToken;

    public function __construct()
    {
        $this->accountId    = config('keygen.account_id');
        $this->productToken = config('keygen.product_token');
    }

    /**
     * Validate a license key against keygen.sh.
     *
     * @return array{valid: bool, code: string, license_id: string|null, expiry: string|null}
     */
    public function validateKey(string $key, string $fingerprint = ''): array
    {
        try {
            $meta = ['key' => $key];
            if ($fingerprint !== '') {
                $meta['scope'] = ['fingerprint' => $fingerprint];
            }

            $response = Http::timeout(15)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->productToken,
                    'Accept'        => 'application/vnd.api+json',
                    'Content-Type'  => 'application/vnd.api+json',
                ])
                ->post("https://api.keygen.sh/v1/accounts/{$this->accountId}/licenses/actions/validate-key", [
                    'meta' => $meta,
                ]);

            $body = $response->json();
            $meta = $body['meta'] ?? [];
            $data = $body['data'] ?? null;
            $code = $meta['code'] ?? 'UNKNOWN';

            // Some keygen responses report no bound machine as NO_MACHINES or NO_MACHINE.
            // Both indicate the key can continue to machine activation.
            $valid = (bool) ($meta['valid'] ?? false) || in_array($code, ['NO_MACHINES', 'NO_MACHINE'], true);

            return [
                'valid'      => $valid,
                'code'       => $code,
                'license_id' => $data['id'] ?? null,
                'expiry'     => $data['attributes']['expiry'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'valid'      => false,
                'code'       => 'CONNECTION_ERROR',
                'license_id' => null,
                'expiry'     => null,
            ];
        }
    }

    /**
     * Activate (bind) this machine to a keygen.sh license.
     *
     * @return array{success: bool, machine_id: string|null, error?: string}
     */
    public function activateMachine(string $licenseId, string $fingerprint, string $name = 'BiometricSystem'): array
    {
        try {
            $response = Http::timeout(15)
                ->withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->productToken,
                    'Accept'        => 'application/vnd.api+json',
                    'Content-Type'  => 'application/vnd.api+json',
                ])
                ->post("https://api.keygen.sh/v1/accounts/{$this->accountId}/machines", [
                    'data' => [
                        'type'       => 'machines',
                        'attributes' => [
                            'fingerprint' => $fingerprint,
                            'name'        => $name,
                        ],
                        'relationships' => [
                            'license' => [
                                'data' => [
                                    'type' => 'licenses',
                                    'id'   => $licenseId,
                                ],
                            ],
                        ],
                    ],
                ]);

            $body = $response->json();

            if ($response->successful()) {
                return [
                    'success'    => true,
                    'machine_id' => $body['data']['id'] ?? null,
                ];
            }

            // Already activated on this machine is acceptable
            $code = $body['errors'][0]['code'] ?? '';
            if (in_array($code, ['FINGERPRINT_TAKEN', 'TOO_MANY_MACHINES'], true)) {
                return ['success' => true, 'machine_id' => null];
            }

            return [
                'success' => false,
                'error'   => $body['errors'][0]['detail'] ?? 'Machine activation failed.',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Generate a deterministic fingerprint for this machine.
     */
    public function generateFingerprint(): string
    {
        return md5(php_uname('n') . php_uname('s') . php_uname('m'));
    }
}
