<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OvertimeRequest extends Model
{
    protected $fillable = ['employee_id', 'work_date', 'minutes', 'rate_per_hour', 'amount', 'reason', 'status'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            // Snapshot taken at submission; never recalculated when the employee's rate changes.
            'rate_per_hour' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }
}
