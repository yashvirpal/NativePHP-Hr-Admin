<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['Engineering', 'ENG'], ['Human Resources', 'HR'], ['Finance', 'FIN'], ['Sales', 'SAL'],
            ['Marketing', 'MKT'], ['Operations', 'OPS'], ['Customer Support', 'SUP'], ['Legal', 'LEG'],
            ['Design', 'DSN'], ['Quality Assurance', 'QA'],
        ] as [$name, $code]) {
            Department::firstOrCreate(['name' => $name], ['code' => $code]);
        }
    }
}
