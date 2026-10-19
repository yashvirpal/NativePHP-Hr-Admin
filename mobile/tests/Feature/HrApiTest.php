<?php

namespace Tests\Feature;

use App\Services\Api\ApiException;
use App\Services\Api\HrApi;
use App\Services\Api\TokenStore;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HrApiTest extends TestCase
{
    private const BASE = 'http://hr.test/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        config(['hr.api_url' => self::BASE, 'hr.device_name' => 'test-phone']);
        app(TokenStore::class)->forget();
    }

    private function api(): HrApi
    {
        return app(HrApi::class);
    }

    private function signIn(string $token = 'tok-123'): void
    {
        app(TokenStore::class)->put($token);
    }

    public function test_login_posts_credentials_and_device_name_and_stores_the_token(): void
    {
        Http::fake([self::BASE.'/auth/login' => Http::response(['token' => 'abc', 'user' => ['name' => 'Alice']])]);

        $user = $this->api()->login('alice@example.com', 'secret');

        $this->assertSame('Alice', $user['name']);
        $this->assertSame('abc', app(TokenStore::class)->get());
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/auth/login'
            && $r['email'] === 'alice@example.com' && $r['password'] === 'secret' && $r['device_name'] === 'test-phone'
            && ! $r->hasHeader('Authorization'));
    }

    public function test_authenticated_calls_send_the_bearer_token_and_json_accept_header(): void
    {
        $this->signIn('tok-123');
        Http::fake([self::BASE.'/dashboard' => Http::response(['ok' => true])]);

        $this->assertSame(['ok' => true], $this->api()->dashboard());

        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer tok-123') && $r->hasHeader('Accept', 'application/json'));
    }

    public function test_get_parameters_become_a_query_string(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->api()->attendance('2026-10');

        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_contains($r->url(), '/attendance?month=2026-10'));
    }

    public function test_no_token_fails_fast_without_calling_the_server(): void
    {
        Http::fake();

        try {
            $this->api()->dashboard();
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertTrue($e->isUnauthenticated());
        }

        Http::assertNothingSent();
    }

    public function test_a_401_forgets_the_token(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        try {
            $this->api()->dashboard();
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertTrue($e->isUnauthenticated());
        }

        $this->assertFalse(app(TokenStore::class)->has());
    }

    public function test_a_failed_login_is_a_validation_error_and_not_a_session_expiry(): void
    {
        Http::fake(['*' => Http::response(['message' => 'The provided credentials are incorrect.', 'errors' => ['email' => ['The provided credentials are incorrect.']]], 422)]);

        try {
            $this->api()->login('a@b.c', 'wrong');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertTrue($e->isValidation());
            $this->assertSame(['email' => 'The provided credentials are incorrect.'], $e->fieldMessages());
        }
    }

    public function test_an_unreachable_server_is_reported_as_a_connection_problem(): void
    {
        $this->signIn();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        try {
            $this->api()->dashboard();
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertTrue($e->isConnection());
            $this->assertStringContainsString('Cannot reach the server', $e->getMessage());
            $this->assertTrue(app(TokenStore::class)->has(), 'a network blip must not sign the user out');
        }
    }

    public function test_server_errors_carry_the_servers_message(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response(['message' => 'You are already punched in.'], 422)]);

        try {
            $this->api()->punchIn();
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('You are already punched in.', $e->getMessage());
        }
    }

    public function test_logout_always_forgets_the_token_even_if_the_server_is_unreachable(): void
    {
        $this->signIn();
        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->api()->logout();

        $this->assertFalse(app(TokenStore::class)->has());
    }

    public function test_overtime_rate_is_read_and_changed_with_get_and_put(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response(['rate' => '175.00', 'effective_from' => null, 'history' => []])]);

        $this->assertSame('175.00', $this->api()->overtimeRate()['rate']);
        $this->api()->setOvertimeRate('175.50');

        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && $r->url() === self::BASE.'/overtime-rate');
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === self::BASE.'/overtime-rate' && $r['hourly_rate'] === '175.50');
    }

    public function test_delete_and_update_use_the_right_verbs(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response([], 204)]);

        $this->api()->deleteCompany(9);
        $this->api()->saveCompany(['name' => 'X'], 9);
        $this->api()->saveCompany(['name' => 'Y']);

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::BASE.'/companies/9');
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === self::BASE.'/companies/9' && $r['name'] === 'X');
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/companies' && $r['name'] === 'Y');
    }
}
