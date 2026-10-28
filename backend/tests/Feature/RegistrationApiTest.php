<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;

class RegistrationApiTest extends ApiTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alex Employee',
            'email' => 'alex@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'device_name' => 'iPhone',
        ], $overrides);
    }

    public function test_registration_creates_an_account_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $response->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);
        $response->assertJsonPath('user.email', 'alex@example.com');

        $user = User::where('email', 'alex@example.com')->firstOrFail();
        $employee = Employee::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('active', $employee->status);
        $this->assertNull($employee->department_id);
        $this->assertNull($employee->shift_id);
        $this->assertNull($employee->manager_id);
        $this->assertNotNull($employee->employee_code);
    }

    public function test_the_returned_token_works_immediately(): void
    {
        $token = $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated()->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/dashboard')
            ->assertOk();
    }

    public function test_registration_rejects_a_duplicate_email(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $this->postJson('/api/v1/auth/register', $this->payload(['name' => 'Someone Else']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload(['password_confirmation' => 'different']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('password');
    }

    public function test_registration_requires_a_name_email_and_password(): void
    {
        $this->postJson('/api/v1/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name']);
    }

    public function test_employee_codes_do_not_collide_across_registrations(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();
        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'second@example.com']))->assertCreated();

        $codes = Employee::pluck('employee_code');
        $this->assertSame($codes->count(), $codes->unique()->count());
    }
}
