<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * 20 employees: a login in `users` (default guard + mobile API) and an `employees` profile.
 * Every account uses the password "password". Back-office staff are in AdminSeeder.
 */
class UserEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make('password');
        $departments = Department::pluck('id', 'code');
        $shifts = Shift::pluck('id', 'name');

        // [name, email, dept code, title, shift]
        $people = [
            ['Alice Employee', 'alice@example.com', 'ENG', 'Software Engineer', 'General'],
            ['Bob Employee', 'bob@example.com', 'ENG', 'Software Engineer', 'General'],
            ['Rahul Verma', 'rahul@example.com', 'ENG', 'Senior Engineer', 'Flexi Morning'],
            ['Sneha Iyer', 'sneha@example.com', 'FIN', 'Accountant', 'General'],
            ['Arjun Mehta', 'arjun@example.com', 'SAL', 'Sales Executive', 'Late Start'],
            ['Neha Gupta', 'neha@example.com', 'MKT', 'Marketing Lead', 'General'],
            ['Karan Singh', 'karan@example.com', 'OPS', 'Operations Manager', 'Early Bird'],
            ['Divya Nair', 'divya@example.com', 'SUP', 'Support Agent', 'Support Day'],
            ['Vikram Rao', 'vikram@example.com', 'SUP', 'Support Agent', 'Support Evening'],
            ['Anita Desai', 'anita@example.com', 'LEG', 'Legal Counsel', 'General'],
            ['Rohit Kapoor', 'rohit@example.com', 'DSN', 'UI Designer', 'Flexi Midday'],
            ['Meera Joshi', 'meera@example.com', 'QA', 'QA Engineer', 'General'],
            ['Sanjay Patel', 'sanjay@example.com', 'ENG', 'Backend Engineer', 'General'],
            ['Pooja Reddy', 'pooja@example.com', 'ENG', 'Frontend Engineer', 'Flexi Morning'],
            ['Amit Khanna', 'amit@example.com', 'FIN', 'Finance Analyst', 'General'],
            ['Kavya Menon', 'kavya@example.com', 'DSN', 'Product Designer', 'Flexi Midday'],
            ['Manish Tiwari', 'manish@example.com', 'QA', 'Test Automation Engineer', 'General'],
            ['Ishaan Bose', 'ishaan@example.com', 'OPS', 'Logistics Coordinator', 'Early Bird'],
            ['Tanvi Shah', 'tanvi@example.com', 'HR', 'Recruiter', 'General'],
            ['Nikhil Jain', 'nikhil@example.com', 'SAL', 'Account Manager', 'Late Start'],
        ];

        foreach ($people as $i => [$name, $email, $dept, $title, $shift]) {
            $user = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => $password]);

            Employee::firstOrCreate(['user_id' => $user->id], [
                'department_id' => $departments[$dept],
                'shift_id' => $shifts[$shift],
                'employee_code' => sprintf('EMP%03d', $i + 1),
                'designation' => $title,
                'phone' => sprintf('+91 98%08d', 10000000 + $i * 7919),
                'joined_on' => now()->subMonths(3 + $i * 2)->toDateString(),
                'status' => 'active',
            ]);
        }

        // Managers: the first employee (lowest id) of each department manages the rest.
        Employee::whereNull('manager_id')->get()->groupBy('department_id')->each(function ($group) {
            $manager = $group->sortBy('id')->first();
            $group->where('id', '!=', $manager->id)->each(fn (Employee $e) => $e->update(['manager_id' => $manager->id]));
        });
    }
}
