<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An employee-declared "I worked N regular hours on this day" entry, independent of the
     * punch clock (attendance_records/attendance_punches) — not reconciled or merged with it.
     * No status column: like overtime_requests, there is no approval gate for this app.
     */
    public function up(): void
    {
        Schema::create('manual_regular_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('work_date');
            $table->unsignedInteger('minutes');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_regular_entries');
    }
};
