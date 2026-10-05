<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Workspace extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'slug'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_memberships')
            ->withPivot('role');
    }
}
