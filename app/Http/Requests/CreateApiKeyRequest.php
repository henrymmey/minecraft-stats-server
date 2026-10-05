<?php

namespace App\Http\Requests;

use App\Services\ApiKeys\ApiKeyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'type' => ['required', Rule::in(ApiKeyService::TYPES)],
            'description' => ['nullable', 'string', 'max:500'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string', 'distinct', Rule::in(ApiKeyService::SCOPES)],
            'player_restrictions' => ['sometimes', 'array', 'max:1000'],
            'player_restrictions.*' => ['uuid', 'distinct'],
            'server_restrictions' => ['sometimes', 'array', 'max:100'],
            'server_restrictions.*' => ['uuid', 'distinct'],
            'season_restrictions' => ['sometimes', 'array', 'max:100'],
            'season_restrictions.*' => ['uuid', 'distinct'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}
