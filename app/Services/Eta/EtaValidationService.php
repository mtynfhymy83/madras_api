<?php

namespace App\Services\Eta;

class EtaValidationService
{
    /**
     * Validate ETA init data
     * 
     * ETA sends data as URL-encoded query string:
     * auth_date=...&device_id=...&query_id=...&user={"id":...}&hash=...
     * 
     * @param string $eitaaData URL-encoded query string from ETA
     * @param string $token ETA bot token
     * @return bool
     */
    public function validateEitaData(string $eitaaData, string $token): bool
    {
        try {
            // URL decode first if needed (in case it's double-encoded)
            $eitaaData = urldecode($eitaaData);
            
            // Parse URL-encoded query string
            parse_str($eitaaData, $data);
            
            if (empty($data) || !isset($data['hash'])) {
                \Log::warning('ETA validation: Missing hash or empty data', [
                    'data_keys' => array_keys($data ?? []),
                    'has_hash' => isset($data['hash'])
                ]);
                return false;
            }

            // Extract hash
            $hash = $data['hash'];
            unset($data['hash']);

            // Sort data by key
            ksort($data);

            // Create data check string
            // Format: key=value\nkey=value\n...
            $dataCheckString = '';
            foreach ($data as $key => $value) {
                $dataCheckString .= $key . '=' . $value . "\n";
            }
            $dataCheckString = rtrim($dataCheckString);

            // Calculate secret key using HMAC SHA256
            // secret_key = HMAC_SHA256(token, "WebAppData")
            $secretKey = hash_hmac('sha256', $token, 'WebAppData', true);

            // Calculate hash
            // hash = hex(HMAC_SHA256(data_check_string, secret_key))
            $calculatedHash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

            // Debug logging (only in non-production)
            if (config('app.debug')) {
                \Log::debug('ETA validation debug', [
                    'data_check_string' => $dataCheckString,
                    'provided_hash' => $hash,
                    'calculated_hash' => $calculatedHash,
                    'hash_match' => hash_equals($calculatedHash, $hash),
                    'token_length' => strlen($token),
                    'data_keys' => array_keys($data)
                ]);
            }

            // Compare hashes (timing-safe comparison)
            return hash_equals($calculatedHash, $hash);
        } catch (\Exception $e) {
            \Log::error('ETA validation error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Extract user data from ETA init data
     * 
     * @param string $eitaaData URL-encoded query string from ETA
     * @return array
     */
    public function extractData(string $eitaaData): array
    {
        // Parse URL-encoded query string
        parse_str($eitaaData, $data);
        
        // Parse user JSON field
        $userData = [];
        if (isset($data['user'])) {
            $userJson = is_string($data['user']) ? $data['user'] : json_encode($data['user']);
            $userData = json_decode($userJson, true) ?? [];
        }
        
        return [
            'user' => [
                'id' => $userData['id'] ?? null,
                'first_name' => $userData['first_name'] ?? '',
                'last_name' => $userData['last_name'] ?? '',
                'username' => $userData['username'] ?? null,
                'email' => $userData['email'] ?? null,
                'language_code' => $userData['language_code'] ?? null,
                'allows_write_to_pm' => $userData['allows_write_to_pm'] ?? false,
            ],
            'auth_date' => $data['auth_date'] ?? null,
            'device_id' => $data['device_id'] ?? null,
            'query_id' => $data['query_id'] ?? null,
            'hash' => $data['hash'] ?? null,
        ];
    }
}

