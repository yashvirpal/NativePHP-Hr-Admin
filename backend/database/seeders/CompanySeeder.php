<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Database\Seeder;

/** Employer details on employee profiles: everyone gets a current company; every third also a previous one. */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            ['Acme Technologies', 'Software', 'acme-tech.example.com'],
            ['Globex Industries', 'Manufacturing', 'globex.example.com'],
            ['Initech Solutions', 'IT Services', 'initech.example.com'],
            ['Umbrella Logistics', 'Logistics', 'umbrella-log.example.com'],
            ['Stark Finance', 'Financial Services', 'stark-fin.example.com'],
            ['Wayne Retail', 'Retail', 'wayne-retail.example.com'],
            ['Hooli Cloud', 'Cloud Computing', 'hooli.example.com'],
            ['Wonka Foods', 'Food & Beverage', 'wonka.example.com'],
            ['Pied Piper Labs', 'Data Compression', 'piedpiper.example.com'],
            ['Cyberdyne Systems', 'Robotics', 'cyberdyne.example.com'],
        ];

        Employee::with('user')->orderBy('id')->get()->each(function (Employee $employee, int $i) use ($companies) {
            [$name, $industry, $host] = $companies[$i % count($companies)];

            Company::firstOrCreate(
                ['employee_id' => $employee->id, 'name' => $name],
                [
                    'industry' => $industry,
                    'designation' => $employee->designation,
                    'website' => 'https://'.$host,
                    'phone' => sprintf('+91 80%08d', 20000000 + $i * 4421),
                    'address' => sprintf('%d Business Park Road, Bengaluru', 10 + $i),
                    'started_on' => $employee->joined_on?->toDateString(),
                    'is_current' => true,
                ],
            );

            if ($i % 3 === 0) {
                [$prevName, $prevIndustry, $prevHost] = $companies[($i + 4) % count($companies)];

                // A previous employer: worked there for the two years before joining.
                Company::updateOrCreate(
                    ['employee_id' => $employee->id, 'name' => $prevName],
                    [
                        'industry' => $prevIndustry,
                        'designation' => 'Associate',
                        'website' => 'https://'.$prevHost,
                        'started_on' => $employee->joined_on?->copy()->subYears(2)->toDateString(),
                        'ended_on' => $employee->joined_on?->toDateString(),
                        'is_current' => false,
                    ],
                );
            }
        });
    }
}
