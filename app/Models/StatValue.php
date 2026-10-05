<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StatValue extends Pivot
{
    protected $table = 'player_stats';
}
