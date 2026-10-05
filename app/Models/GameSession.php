<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GameSession extends Model
{
    protected $table = 'game_sessions';
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'season_id',
        'player_id',
        'server_id',
        'client_session_id',
        'started_at',
        'last_seen_at',
        'ended_at',
        'client_version',
        'minecraft_version',
        'mod_version',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
