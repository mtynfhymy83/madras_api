<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserMeta;
use App\Models\Utm;
use App\Services\Auth\JwtService;
use App\Services\Eta\EtaValidationService;
use App\Services\Eta\EtaMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected $jwtService;
    protected $etaValidationService;
    protected $etaMessageService;

    public function __construct(
        JwtService $jwtService,
        EtaValidationService $etaValidationService,
        EtaMessageService $etaMessageService
    ) {
        $this->jwtService = $jwtService;
        $this->etaValidationService = $etaValidationService;
        $this->etaMessageService = $etaMessageService;
    }

    /**
     * Login or register user via ETA init data (auto auth)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function etaLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'eitaa_data' => 'required|string',
            'utm' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $eitaaData = $request->input('eitaa_data');
            $utm = $request->input('utm');

            // ETA sends data as URL-encoded query string, no need to remove backslashes
            // Format: auth_date=...&device_id=...&user={"id":...}&hash=...

            // Get ETA token from config
            $eitaaToken = config('services.eitaa.token');
            $eitaaToken2 = config('services.eitaa.token2'); // Test token (optional)

            if (!$eitaaToken) {
                return $this->errorResponse('ایتا توکن الزامی می باشد!');
            }

            // Validate ETA data
            $validData = $this->etaValidationService->validateEitaData($eitaaData, $eitaaToken);
            
            if (!$validData && $eitaaToken2) {
                $validData = $this->etaValidationService->validateEitaData($eitaaData, $eitaaToken2);
            }

            if (!$validData) {
                // Log validation failure for debugging
                \Log::warning('ETA validation failed', [
                    'has_token' => !empty($eitaaToken),
                    'has_token2' => !empty($eitaaToken2),
                    'data_length' => strlen($eitaaData),
                    'data_preview' => substr($eitaaData, 0, 100)
                ]);
                
                return $this->errorResponse('دیتا معتبر نمی باشد!');
            }

            // Extract data from ETA init data
            $parsedData = $this->etaValidationService->extractData($eitaaData);
            $userData = $parsedData['user'];

            $eitaaId = $userData['id'] ?? null;
            $firstName = $userData['first_name'] ?? '';
            $lastName = $userData['last_name'] ?? '';
            $username = $userData['username'] ?? 'user_' . $eitaaId;
            $email = $userData['email'] ?? $username . '@eitaa.com';
            $fullName = trim($firstName . ' ' . $lastName);

            if (empty($eitaaId)) {
                return $this->errorResponse('آیدی ایتا معتبر نمی باشد!');
            }

            // Check if user exists by eitaa_id in user_meta
            $userMeta = UserMeta::where('meta_name', 'eitaa_id')
                ->where('meta_value', $eitaaId)
                ->first();

            if ($userMeta && $userMeta->user_id) {
                // User exists - login
                $user = User::find($userMeta->user_id);

                if (!$user) {
                    return $this->errorResponse('User not found');
                }

                // Update last seen
                $user->updateLastSeen();

                // Save UTM if provided (is_registered = 0 for existing users)
                if (!empty($utm)) {
                    $this->saveUtm($user->id, $eitaaId, $utm, false);
                }

                // Generate JWT token pair
                $deviceInfo = $request->input('device_info');
                $tokens = $this->jwtService->generateTokenPair($user, $deviceInfo);

                return $this->successResponse([
                    'login' => true,
                    'user' => $user->makeHidden(['password']),
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'expires_in' => $tokens['expires_in'],
                    'token_type' => $tokens['token_type'],
                ]);

            } else {
                // User doesn't exist - create new user
                // Ensure username is unique
                $counter = 1;
                $originalUsername = $username;
                while (User::where('username', $username)->exists()) {
                    $username = $originalUsername . '_' . $counter;
                    $counter++;
                }

                // Ensure email is unique
                $counter = 1;
                $originalEmail = $email;
                while (User::where('email', $email)->exists()) {
                    // Extract base email and domain
                    $emailParts = explode('@', $originalEmail);
                    $emailBase = $emailParts[0];
                    $emailDomain = $emailParts[1] ?? 'eitaa.com';
                    $email = $emailBase . '_' . $counter . '@' . $emailDomain;
                    $counter++;
                }

                // Create user
                $user = User::create([
                    'username' => $username,
                    'tel' => '',
                    'displayname' => $fullName ?: $username,
                    'name' => $firstName,
                    'family' => $lastName,
                    'email' => $email,
                    'password' => Hash::make(Str::random(32)),
                    'active' => true,
                    'approved' => true,
                    'level' => 'user',
                    'register' => 'done',
                ]);

                // Save eitaa_id in user_meta
                $user->updateMeta(['eitaa_id' => $eitaaId], $user->id);

                // Save UTM if provided (is_registered = 1 for new users)
                if (!empty($utm)) {
                    $this->saveUtm($user->id, $eitaaId, $utm, true);
                }

                // Send welcome message
                if (!empty($fullName)) {
                    $message = "سلام $fullName عزیز! 🎉\nخوشحالیم که به مدرس پیوستی! \nثبت نام شما با موفقیت انجام شد. \nامیدواریم که تجربه‌ای عالی در مدرس داشته باشید. ";
                    $this->etaMessageService->sendMessage($eitaaId, $message);
                }

                // Generate JWT token pair
                $deviceInfo = $request->input('device_info');
                $tokens = $this->jwtService->generateTokenPair($user, $deviceInfo);

                return $this->successResponse([
                    'register' => true,
                    'user' => $user->makeHidden(['password']),
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'expires_in' => $tokens['expires_in'],
                    'token_type' => $tokens['token_type'],
                ], 201);
            }

        } catch (\Exception $e) {
            \Log::error('ETA Login Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponse('خطا در انجام عملیات', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Save UTM tracking data
     */
    private function saveUtm(int $userId, string $eitaaId, string $utm, bool $isRegistered): void
    {
        Utm::create([
            'user_id' => $userId,
            'eitaa_id' => $eitaaId,
            'is_registered' => $isRegistered,
            'utm' => $utm,
            'created_at' => now(),
        ]);
    }

    /**
     * Success response helper (matches original format)
     */
    private function successResponse($data, int $status = 200)
    {
        // Return data directly (matches original outS format)
        return response()->json($data, $status);
    }

    /**
     * Error response helper (matches original format)
     */
    private function errorResponse(string $message, $errors = null, int $status = 400)
    {
        // Return error in original format
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ], $status);
    }


    /**
     * Refresh access token using refresh token
     */
    public function refresh(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $tokens = $this->jwtService->refreshAccessToken($request->input('refresh_token'));

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => $tokens
            ]);
        } catch (\Illuminate\Auth\AuthenticationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user (revoke refresh token)
     */
    public function logout(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'refresh_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $revoked = $this->jwtService->revokeToken($request->input('refresh_token'));

            if ($revoked) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully logged out'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Token not found or already revoked'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user
     */
    public function me()
    {
        try {
            $user = auth('api')->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => $user->makeHidden(['password'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user info',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate valid ETA test data (debug endpoint - remove in production)
     */
    public function generateTestData(Request $request)
    {
        if (!config('app.debug')) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only available in debug mode'
            ], 403);
        }

        try {
            $token = config('services.eitaa.token');
            
            if (empty($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'EITAA_TOKEN not configured'
                ], 500);
            }

            // Generate test data
            $authDate = time();
            $deviceId = $request->input('device_id', bin2hex(random_bytes(16)));
            $queryId = $request->input('query_id', (string)mt_rand(1000000000000000, 9999999999999999));

            // User data
            $userData = [
                'id' => (int)($request->input('user_id', 10865407)),
                'first_name' => $request->input('first_name', 'MahdiAli'),
                'last_name' => $request->input('last_name', 'Pak'),
                'language_code' => $request->input('language_code', 'en'),
                'allows_write_to_pm' => true
            ];

            // Build data array (without hash)
            $data = [
                'auth_date' => (string)$authDate,
                'device_id' => $deviceId,
                'query_id' => (string)$queryId,
                'user' => json_encode($userData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ];

            // Sort by key
            ksort($data);

            // Create data check string
            $dataCheckString = '';
            foreach ($data as $key => $value) {
                $dataCheckString .= $key . '=' . $value . "\n";
            }
            $dataCheckString = rtrim($dataCheckString);

            // Calculate secret key
            $secretKey = hash_hmac('sha256', $token, 'WebAppData', true);

            // Calculate hash
            $hash = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey, true));

            // Build final query string
            $queryString = http_build_query($data) . '&hash=' . $hash;

            return response()->json([
                'success' => true,
                'data' => [
                    'eitaa_data' => $queryString,
                    'full_request' => [
                        'eitaa_data' => $queryString,
                        'utm' => 'source=test&medium=postman'
                    ]
                ],
                'debug' => [
                    'auth_date' => $authDate,
                    'device_id' => $deviceId,
                    'query_id' => $queryId,
                    'user_data' => $userData,
                    'data_check_string' => $dataCheckString,
                    'calculated_hash' => $hash
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating test data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test ETA validation (debug endpoint - remove in production)
     */
    public function testEtaValidation(Request $request)
    {
        if (!config('app.debug')) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is only available in debug mode'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'eitaa_data' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        try {
            $eitaaData = $request->input('eitaa_data');
            $eitaaToken = config('services.eitaa.token');
            $eitaaToken2 = config('services.eitaa.token2');

            // Parse data
            $decoded = urldecode($eitaaData);
            parse_str($decoded, $parsed);

            // Extract hash
            $hash = $parsed['hash'] ?? null;
            unset($parsed['hash']);

            // Sort and create check string
            ksort($parsed);
            $dataCheckString = '';
            foreach ($parsed as $key => $value) {
                $dataCheckString .= $key . '=' . $value . "\n";
            }
            $dataCheckString = rtrim($dataCheckString);

            // Calculate hashes
            $secretKey1 = hash_hmac('sha256', $eitaaToken, 'WebAppData', true);
            $calculatedHash1 = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey1, true));

            $result = [
                'provided_hash' => $hash,
                'calculated_hash_token1' => $calculatedHash1,
                'hash_match_token1' => hash_equals($calculatedHash1, $hash),
                'data_check_string' => $dataCheckString,
                'parsed_data' => $parsed,
                'has_token1' => !empty($eitaaToken),
                'token1_length' => strlen($eitaaToken ?? ''),
            ];

            if ($eitaaToken2) {
                $secretKey2 = hash_hmac('sha256', $eitaaToken2, 'WebAppData', true);
                $calculatedHash2 = bin2hex(hash_hmac('sha256', $dataCheckString, $secretKey2, true));
                $result['calculated_hash_token2'] = $calculatedHash2;
                $result['hash_match_token2'] = hash_equals($calculatedHash2, $hash);
                $result['has_token2'] = true;
            }

            // Try actual validation
            $valid = $this->etaValidationService->validateEitaData($eitaaData, $eitaaToken);
            if (!$valid && $eitaaToken2) {
                $valid = $this->etaValidationService->validateEitaData($eitaaData, $eitaaToken2);
            }

            $result['validation_result'] = $valid;

            return response()->json([
                'success' => true,
                'debug' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}
