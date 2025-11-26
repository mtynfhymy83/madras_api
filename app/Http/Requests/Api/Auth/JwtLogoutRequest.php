<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\ApiRequest;
use Illuminate\Contracts\Validation\Validator;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *   schema="JwtLogoutRequest",
 *   type="object",
 *
 *   @OA\Property(
 *     property="refresh_token",
 *     type="string",
 *     description="JWT refresh token to revoke",
 *     example="eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
 *   )
 * )
 */
class JwtLogoutRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'refresh_token' => ['nullable', 'string', 'min:64'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // Validate Miniapp-UUID header
            $miniAppUuid = $this->header('Miniapp-UUID');

            if (! $miniAppUuid) {
                $validator->errors()->add('miniapp_uuid', 'Miniapp-UUID header is required.');
            } elseif (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $miniAppUuid)) {
                $validator->errors()->add('miniapp_uuid', 'Miniapp-UUID must be a valid UUID.');
            }
        });
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'refresh_token.string' => 'Refresh token must be a string.',
            'refresh_token.min' => 'Refresh token must be at least 64 characters.',
        ];
    }
}