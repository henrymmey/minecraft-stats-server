<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IngestBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->attributes->has('api_key');
    }

    public function rules(): array
    {
        return [
            'protocol_version' => ['required', 'integer', Rule::in([1])],
            'client' => ['required', 'array'],
            'client.mod_version' => ['required', 'string', 'max:64'],
            'client.minecraft_version' => ['required', 'string', 'max:64'],
            'client.fabric_loader_version' => ['nullable', 'string', 'max:64'],

            'player' => ['required', 'array'],
            'player.uuid' => ['required', 'uuid'],
            'player.username' => ['required', 'string', 'min:1', 'max:16'],

            'server' => ['required', 'array'],
            'server.hostname' => ['required', 'string', 'min:1', 'max:253'],
            'server.port' => ['required', 'integer', 'between:1,65535'],

            'season' => ['nullable', 'string', 'max:255'],
            'session_id' => ['required', 'uuid'],
            'observed_at' => ['required', 'date_format:Y-m-d\\TH:i:sP'],

            'stats' => ['sometimes', 'array', 'max:500'],
            'stats.*' => ['required', 'array'],
            'stats.*.key' => ['required', 'string', 'min:1', 'max:255'],
            'stats.*.value' => ['required', 'integer', 'min:0'],

            'events' => ['sometimes', 'array', 'max:100'],
            'events.*' => ['required', 'array'],
            'events.*.id' => ['required', 'uuid'],
            'events.*.type' => ['required', 'string', 'min:1', 'max:64'],
            'events.*.occurred_at' => ['required', 'date_format:Y-m-d\\TH:i:sP'],
            'events.*.payload' => ['sometimes', 'array'],
        ];
    }
}
