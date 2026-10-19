<?php

namespace App\Services;

use App\Models\AttendancePunch;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function today(Employee $employee): ?AttendanceRecord
    {
        return AttendanceRecord::with('punches')
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', Carbon::today())
            ->first();
    }

    /**
     * @param  array{latitude?: float|null, longitude?: float|null, source?: string, device_info?: string|null}  $meta
     */
    public function punchIn(Employee $employee, array $meta = []): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $meta) {
            $now = Carbon::now();

            $record = AttendanceRecord::firstOrCreate(
                ['employee_id' => $employee->id, 'work_date' => $now->toDateString()],
                ['status' => 'present'],
            );

            $last = $record->punches()->latest('punched_at')->latest('id')->first();
            if ($last?->type === 'in') {
                throw ValidationException::withMessages(['punch' => 'You are already punched in.']);
            }

            $this->addPunch($record, 'in', $now, $meta);

            if ($record->first_in_at === null) {
                $record->first_in_at = $now;
                $record->status = $this->isLate($employee, $now) ? 'late' : 'present';
                $record->save();
            }

            return $record->load('punches');
        });
    }

    /**
     * @param  array{latitude?: float|null, longitude?: float|null, source?: string, device_info?: string|null}  $meta
     */
    public function punchOut(Employee $employee, array $meta = []): AttendanceRecord
    {
        return DB::transaction(function () use ($employee, $meta) {
            $now = Carbon::now();

            $record = AttendanceRecord::where('employee_id', $employee->id)
                ->whereDate('work_date', $now->toDateString())
                ->lockForUpdate()
                ->first();

            $last = $record?->punches()->latest('punched_at')->latest('id')->first();
            if ($last?->type !== 'in') {
                throw ValidationException::withMessages(['punch' => 'You are not punched in.']);
            }

            $this->addPunch($record, 'out', $now, $meta);

            $record->load('punches');
            $record->last_out_at = $now;
            $record->worked_minutes = $this->workedMinutes($record);
            $record->save();

            return $record;
        });
    }

    public function history(Employee $employee, ?string $month = null)
    {
        $query = AttendanceRecord::where('employee_id', $employee->id)->orderByDesc('work_date');

        if ($month !== null) {
            $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $query->whereBetween('work_date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
        }

        return $query->paginate(31);
    }

    private function addPunch(AttendanceRecord $record, string $type, CarbonInterface $at, array $meta): AttendancePunch
    {
        return $record->punches()->create([
            'type' => $type,
            'punched_at' => $at,
            'latitude' => $meta['latitude'] ?? null,
            'longitude' => $meta['longitude'] ?? null,
            'source' => $meta['source'] ?? 'mobile',
            'device_info' => $meta['device_info'] ?? null,
        ]);
    }

    private function isLate(Employee $employee, CarbonInterface $at): bool
    {
        $shift = $employee->shift;
        if (! $shift) {
            return false;
        }

        $deadline = Carbon::parse($at->toDateString().' '.$shift->start_time)->addMinutes($shift->grace_minutes);

        return $at->greaterThan($deadline);
    }

    /** Sum of completed in→out pairs, in whole minutes. */
    private function workedMinutes(AttendanceRecord $record): int
    {
        $total = 0;
        $openIn = null;

        foreach ($record->punches->sortBy([['punched_at', 'asc'], ['id', 'asc']]) as $punch) {
            if ($punch->type === 'in') {
                $openIn = $punch->punched_at;
            } elseif ($openIn !== null) {
                $total += $openIn->diffInMinutes($punch->punched_at);
                $openIn = null;
            }
        }

        return (int) $total;
    }
}
