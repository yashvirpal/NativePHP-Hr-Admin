<?php

namespace Tests\Feature;

use App\Models\User;
use Laravel\Sanctum\Sanctum;

class AuthApiTest extends ApiTestCase
{
    public function test_login_returns_token(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'phone',
        ])->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'employee']]);
    }

    public function test_login_rejects_bad_password(): void
    {
        $user = $this->makeUser();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'nope', 'device_name' => 'phone',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_inactive_employee_cannot_login(): void
    {
        $user = $this->makeUser(['status' => 'inactive']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'phone',
        ])->assertUnprocessable();
    }

    public function test_user_without_employee_profile_cannot_login(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'phone',
        ])->assertUnprocessable();
    }

    public function test_admin_credentials_do_not_work_on_the_employee_api(): void
    {
        $admin = $this->makeAdmin();

        $this->postJson('/api/v1/auth/login', [
            'email' => $admin->email, 'password' => 'password', 'device_name' => 'phone',
        ])->assertUnprocessable();
    }

    public function test_protected_routes_require_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_approval_endpoints_are_not_exposed_to_employees(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/v1/approvals/pending')->assertNotFound();
    }

    public function test_logout_revokes_the_token(): void
    {
        $user = $this->makeUser();
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email, 'password' => 'password', 'device_name' => 'phone',
        ])->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
