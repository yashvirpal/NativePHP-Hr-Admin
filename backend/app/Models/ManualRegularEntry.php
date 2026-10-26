<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualRegularEntry extends Model
{
    protected $fillable = ['employee_id', 'work_date', 'minutes', 'reason'];

    protected function casts(): array
    {
        return ['work_date' => 'date:Y-m-d'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
