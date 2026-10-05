<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatDefinition extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'key',
        'name',
        'category',
        'unit',
        'description',
        'public',
    ];

    protected function casts(): array
    {
        return ['public' => 'boolean'];
    }
}
