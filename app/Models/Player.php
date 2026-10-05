<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Player extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'minecraft_uuid',
        'current_username',
        'first_seen_at',
        'last_seen_at',
        'public',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'public' => 'boolean',
        ];
    }

    public function restrictedApiKeys(): BelongsToMany
    {
        return $this->belongsToMany(ApiKey::class, 'api_key_player_restrictions');
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return $query->where($field ?? 'minecraft_uuid', strtolower((string) $value));
    }
}
