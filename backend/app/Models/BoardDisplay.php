<?php

namespace App\Models;

use App\Traits\HasUlid;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot for board ↔ display links. The board_displays table uses a ULID primary key;
 * Laravel's default sync()/attach() omit id, so we generate it on create.
 */
class BoardDisplay extends Pivot
{
    use HasUlid;

    protected $table = 'board_displays';
}
