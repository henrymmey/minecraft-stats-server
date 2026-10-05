<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

class ApiKey extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'name',
        'type',
        'prefix',
        'hash',
        'description',
        'enabled',
        'expires_at',
        'created_by',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = ['hash'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function playerRestrictions(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'api_key_player_restrictions');
    }

    public function serverRestrictions(): BelongsToMany
    {
        return $this->belongsToMany(MinecraftServer::class, 'api_key_server_restrictions');
    }

    public function seasonRestrictions(): BelongsToMany
    {
        return $this->belongsToMany(Season::class, 'api_key_season_restrictions');
    }

    public function hasScope(string $scope): bool
    {
        return \DB::table('api_key_scopes')
            ->where('api_key_id', $this->id)
            ->where('scope', $scope)
            ->exists();
    }

    public function isUsable(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->enabled
            && $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->gt($now));
    }
}
