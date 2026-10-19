<?php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        // [name, start, end, grace, break, overnight]
        foreach ([
            ['General', '09:00', '18:00', 15, 60, false],
            ['Early Bird', '07:00', '16:00', 10, 60, false],
            ['Late Start', '11:00', '20:00', 15, 60, false],
            ['Flexi Morning', '08:00', '17:00', 30, 45, false],
            ['Flexi Midday', '10:00', '19:00', 30, 45, false],
            ['Support Day', '08:30', '17:30', 10, 60, false],
            ['Support Evening', '14:00', '23:00', 10, 60, false],
            ['Night Watch', '22:00', '06:00', 10, 45, true],
            ['Half Day AM', '09:00', '13:00', 10, 0, false],
            ['Half Day PM', '13:00', '17:00', 10, 0, false],
        ] as [$name, $start, $end, $grace, $break, $overnight]) {
            Shift::firstOrCreate(['name' => $name], [
                'start_time' => $start.':00',
                'end_time' => $end.':00',
                'grace_minutes' => $grace,
                'break_minutes' => $break,
                'is_overnight' => $overnight,
            ]);
        }
    }
}
