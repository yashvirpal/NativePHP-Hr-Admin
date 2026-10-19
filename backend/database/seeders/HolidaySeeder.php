<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $year = Carbon::now()->year;

        // [name, month-day, optional]
        foreach ([
            ['New Year\'s Day', '01-01', false],
            ['Republic Day', '01-26', false],
            ['Holi', '03-04', true],
            ['Good Friday', '04-03', false],
            ['Labour Day', '05-01', false],
            ['Independence Day', '08-15', false],
            ['Raksha Bandhan', '08-28', true],
            ['Gandhi Jayanti', '10-02', false],
            ['Dussehra', '10-20', false],
            ['Diwali', '11-08', false],
            ['Guru Nanak Jayanti', '11-24', true],
            ['Christmas Day', '12-25', false],
        ] as [$name, $monthDay, $optional]) {
            Holiday::firstOrCreate(['date' => "{$year}-{$monthDay}"], ['name' => $name, 'is_optional' => $optional]);
        }
    }
}
