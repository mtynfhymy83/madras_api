<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\ApiRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *   schema="RefreshTokenRequest",
 *   type="object",
 *   description="Request for refreshing user access token"
 * )
 */
class RefreshTokenRequest extends ApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Pull Miniapp-UUID header into the validator as 'mini_app_uuid'
        $this->merge([
            'mini_app_uuid' => $this->header('Miniapp-UUID'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mini_app_uuid' => ['required', 'uuid', 'exists:mini_apps,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'mini_app_uuid.required' => 'شناسه مینی اپ الزامی است.',
            'mini_app_uuid.uuid' => 'شناسه مینی‌اپ باید یک UUID معتبر باشد.',
            'mini_app_uuid.exists' => 'شناسه مینی‌اپ نامعتبر است.',
        ];
    }
}