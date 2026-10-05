<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MinecraftServer extends Model
{
    protected $table = 'servers';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'name',
        'hostname',
        'port',
        'display_name',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'port' => 'integer',
        ];
    }
}
