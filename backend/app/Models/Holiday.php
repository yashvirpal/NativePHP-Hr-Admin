<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = ['name', 'date', 'is_optional'];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'is_optional' => 'boolean',
        ];
    }
}
