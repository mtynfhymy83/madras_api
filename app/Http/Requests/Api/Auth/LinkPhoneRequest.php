<?php

namespace App\Http\Requests\Api\Auth;

use App\Http\Requests\ApiRequest;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *   schema="LinkPhoneRequest",
 *   type="object",
 *   required={"contact_data","eitaa_data"},
 *
 *   @OA\Property(
 *     property="contact_data",
 *     type="string",
 *     description="Raw JSON with the user’s phone number from Eitaa",
 *     example="{\'user\':{\'phone_number\':\'0912…\'}}"
 *   ),
 *   @OA\Property(
 *     property="eitaa_data",
 *     type="string",
 *     description="Raw JSON payload from Eitaa for verification",
 *     example="{\'user\':{\'id\':123}}"
 *   )
 * )
 */
class LinkPhoneRequest extends ApiRequest
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
            'contact_data' => ['required', 'string'],
            'eitaa_data' => [
                'required',
                'string',
            ],
            'mini_app_uuid' => ['required', 'uuid', 'exists:mini_apps,uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'contact_data.required' => 'شماره تلفن الزامی است.',
            'contact_data.string' => 'شماره تلفن باید یک رشته باشد.',
            'eitaa_data.required' => 'اینیت دیتا الزامی است.',
            'eitaa_data.string' => 'اینیت دیتا باید یک رشته باشد.',
            'mini_app_uuid.required' => 'شناسه مینی اپ الزامی است.',
            'mini_app_uuid.uuid' => 'شناسه مینی‌اپ باید یک UUID معتبر باشد.',
            'mini_app_uuid.exists' => 'شناسه مینی‌اپ نامعتبر است.',
        ];
    }
}