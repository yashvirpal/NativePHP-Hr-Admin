<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id', 'work_date', 'first_in_at', 'last_out_at', 'worked_minutes', 'status',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'first_in_at' => 'datetime',
            'last_out_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }
}
