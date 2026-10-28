<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Self-service account creation — happens before authentication, so it doesn't belong in
 * EmployeePortalService (scoped to what an already-authenticated employee does for themselves).
 * No department/shift/manager: those stay null, same as any other optional profile field.
 */
class RegistrationService
{
    public function register(string $name, string $email, string $password): Employee
    {
        return DB::transaction(function () use ($name, $email, $password) {
            $user = User::create(['name' => $name, 'email' => $email, 'password' => $password]);

            return Employee::create([
                'user_id' => $user->id,
                'employee_code' => 'E'.$user->id,
                'status' => 'active',
                'joined_on' => now(),
            ])->load('user');
        });
    }
}
