<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Admin;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected Shift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        // A Monday, 08:55 - before the 09:00 shift start.
        Carbon::setTestNow('2026-11-02 08:55:00');

        $this->shift = Shift::create([
            'name' => 'General', 'start_time' => '09:00:00', 'end_time' => '18:00:00', 'grace_minutes' => 15,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** An employee: a `users` row plus an `employees` profile. Password is "password". */
    protected function makeUser(array $employee = []): User
    {
        $user = User::factory()->create();

        Employee::create($employee + [
            'user_id' => $user->id,
            'shift_id' => $this->shift->id,
            'employee_code' => 'E'.$user->id,
            'status' => 'active',
        ]);

        return $user->fresh();
    }

    /**
     * Back-office staff in the `admins` table. Password is "password".
     * $role is a role slug; "super_admin" and "manager" are created by the roles migration.
     */
    protected function makeAdmin(string|Role $role = 'manager'): Admin
    {
        static $n = 0;
        $n++;

        return Admin::create([
            'name' => "Admin {$n}",
            'email' => "admin{$n}@example.test",
            'password' => 'password',
            'role_id' => $role instanceof Role ? $role->id : Role::where('slug', $role)->firstOrFail()->id,
        ])->load('role');
    }

    /** A custom role holding exactly these permissions (strings or Permission cases). */
    protected function makeRole(array $permissions, string $name = 'Custom'): Role
    {
        static $n = 0;
        $n++;

        return Role::create([
            'name' => "{$name} {$n}",
            'slug' => 'custom_'.$n,
            'permissions' => array_map(fn ($p) => $p instanceof Permission ? $p->value : $p, $permissions),
        ]);
    }

    protected function leaveType(float $days = 10): LeaveType
    {
        return LeaveType::create(['name' => 'Annual', 'code' => 'AL'.LeaveType::count(), 'days_per_year' => $days]);
    }
}
