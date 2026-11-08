<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One package event, kept so the Console can show what each command set off.
 */
class DemoEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
