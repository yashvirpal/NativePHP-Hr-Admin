<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Past 21 days of attendance for every employee (today is left empty so you can punch in).
 * Skips weekends and holidays; approved leave days become "on_leave".
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $holidays = Holiday::where('is_optional', false)->pluck('date')->map->toDateString()->all();
        $employees = Employee::with('shift')->orderBy('id')->get();

        $leave = LeaveRequest::where('status', 'approved')->get()->groupBy('employee_id');

        foreach ($employees as $i => $employee) {
            for ($back = 1; $back <= 21; $back++) {
                $date = Carbon::today()->subDays($back);
                if ($date->isWeekend() || in_array($date->toDateString(), $holidays, true)) {
                    continue;
                }

                $onLeave = ($leave[$employee->id] ?? collect())->contains(
                    fn ($l) => $date->between($l->start_date, $l->end_date)
                );

                // Deterministic per employee+day so reseeding is stable.
                $seed = crc32($employee->id.'|'.$date->toDateString());
                $absent = $seed % 17 === 0;
                $offset = ($seed % 66) - 20;          // -20 … +45 minutes around shift start
                $length = 480 + ($seed % 90) - 20;     // ≈ 7h40 – 9h10

                $record = AttendanceRecord::firstOrNew(['employee_id' => $employee->id, 'work_date' => $date->toDateString()]);
                if ($record->exists) {
                    continue;
                }

                if ($onLeave || $absent) {
                    $record->status = $onLeave ? 'on_leave' : 'absent';
                    $record->save();

                    continue;
                }

                $shift = $employee->shift;
                $start = Carbon::parse($date->toDateString().' '.($shift?->start_time ?? '09:00:00'))->addMinutes($offset);
                $end = $start->copy()->addMinutes($length);
                $grace = $shift?->grace_minutes ?? 0;
                $deadline = Carbon::parse($date->toDateString().' '.($shift?->start_time ?? '09:00:00'))->addMinutes($grace);

                $record->fill([
                    'first_in_at' => $start,
                    'last_out_at' => $end,
                    'worked_minutes' => $length,
                    'status' => $start->greaterThan($deadline) ? 'late' : 'present',
                ])->save();

                $record->punches()->createMany([
                    ['type' => 'in', 'punched_at' => $start, 'source' => 'mobile', 'device_info' => 'seeded', 'latitude' => 12.9716 + ($i % 5) / 1000, 'longitude' => 77.5946 + ($i % 5) / 1000],
                    ['type' => 'out', 'punched_at' => $end, 'source' => 'mobile', 'device_info' => 'seeded', 'latitude' => 12.9716 + ($i % 5) / 1000, 'longitude' => 77.5946 + ($i % 5) / 1000],
                ]);
            }
        }
    }
}
