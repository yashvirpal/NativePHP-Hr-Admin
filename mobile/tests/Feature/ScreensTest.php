<?php

namespace Tests\Feature;

use App\NativeComponents\Attendance;
use App\NativeComponents\Boot;
use App\NativeComponents\Companies;
use App\NativeComponents\CompanyForm;
use App\NativeComponents\Holidays;
use App\NativeComponents\Home;
use App\NativeComponents\Leave;
use App\NativeComponents\LeaveCreate;
use App\NativeComponents\Login;
use App\NativeComponents\More;
use App\NativeComponents\Overtime;
use App\NativeComponents\OvertimeCreate;
use App\NativeComponents\OvertimeRate;
use App\Services\Api\TokenStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

class ScreensTest extends TestCase
{
    private const BASE = 'http://hr.test/api/v1';

    protected function setUp(): void
    {
        parent::setUp();

        config(['hr.api_url' => self::BASE]);
        app(TokenStore::class)->forget();
    }

    private function signIn(): void
    {
        app(TokenStore::class)->put('tok-123');
    }

    private function dashboard(array $overrides = []): array
    {
        return array_replace_recursive([
            'employee' => ['designation' => 'Software Engineer', 'user' => ['name' => 'Alice Employee'], 'department' => ['name' => 'Engineering']],
            'today' => null,
            'is_punched_in' => false,
            'balances' => [
                ['leave_type' => ['name' => 'Annual Leave'], 'allocated' => '18.0', 'used' => '2.0', 'remaining' => 16],
                ['leave_type' => ['name' => 'Unpaid Leave'], 'allocated' => '0.0', 'used' => '0.0', 'remaining' => 0],
            ],
            'upcoming_holidays' => [['name' => 'Diwali', 'date' => '2026-11-08', 'is_optional' => false]],
            'pending' => ['leave' => 1, 'overtime' => 0],
            'month' => ['days_present' => 7, 'days_late' => 2, 'worked_minutes' => 3330],
        ], $overrides);
    }

    // ─── Launch & login ─────────────────────────────────────────────────────────

    public function test_launch_goes_to_login_without_a_token(): void
    {
        Native::test(Boot::class)->assertReplacedWith('/login');
    }

    public function test_launch_goes_home_with_a_token(): void
    {
        $this->signIn();

        Native::test(Boot::class)->assertReplacedWith('/home');
    }

    public function test_login_success_stores_token_and_opens_home(): void
    {
        Http::fake([self::BASE.'/auth/login' => Http::response(['token' => 'new-token', 'user' => ['name' => 'Alice']])]);

        Native::test(Login::class)
            ->set('email', 'alice@example.com')
            ->set('password', 'password')
            ->tap('Sign in')
            ->assertReplacedWith('/home');

        $this->assertSame('new-token', app(TokenStore::class)->get());
    }

    public function test_login_failure_shows_the_servers_message_and_stays_put(): void
    {
        Http::fake(['*' => Http::response(['message' => 'The provided credentials are incorrect.', 'errors' => ['email' => ['The provided credentials are incorrect.']]], 422)]);

        Native::test(Login::class)
            ->set('email', 'alice@example.com')
            ->set('password', 'wrong')
            ->tap('Sign in')
            ->assertSee('The provided credentials are incorrect.')
            ->assertNoNavigation();

        $this->assertFalse(app(TokenStore::class)->has());
    }

    public function test_login_requires_both_fields_without_calling_the_server(): void
    {
        Http::fake();

        Native::test(Login::class)
            ->tap('Sign in')
            ->assertSee('Enter your email.')
            ->assertSee('Enter your password.');

        Http::assertNothingSent();
    }

    // ─── Home / punch ───────────────────────────────────────────────────────────

    public function test_home_shows_the_dashboard(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/dashboard' => Http::response($this->dashboard())]);

        Native::test(Home::class)
            ->assertSee('Hello, Alice')
            ->assertSee('Software Engineer - Engineering')
            ->assertSee('Not punched in')
            ->assertSee('Punch in')
            ->assertSee('Annual Leave')
            ->assertSee('16 / 18')
            ->assertDontSee('Unpaid Leave')            // zero-allocation types are hidden
            ->assertSee('Diwali')
            ->assertSee('55h 30m')
            ->assertSee('1 leave, 0 overtime');
    }

    public function test_home_punch_in_calls_the_api_and_refreshes(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/attendance/punch-in' => Http::response(['record' => ['status' => 'present']], 201),
            self::BASE.'/dashboard' => Http::sequence()
                ->push($this->dashboard())
                ->push($this->dashboard([
                    'is_punched_in' => true,
                    'today' => ['status' => 'present', 'first_in_at' => '2026-10-13T09:02:00.000000Z', 'last_out_at' => null],
                ])),
        ]);

        Native::test(Home::class)
            ->assertSee('Punch in')
            ->tap('Punch in')
            ->assertSee('Punched in since 09:02')
            ->assertSee('Punch out');

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/attendance/punch-in'
            && $r->hasHeader('Authorization', 'Bearer tok-123'));
    }

    public function test_home_punch_out(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/attendance/punch-out' => Http::response(['record' => []]),
            self::BASE.'/dashboard' => Http::sequence()
                ->push($this->dashboard(['is_punched_in' => true, 'today' => ['status' => 'present', 'first_in_at' => '2026-10-13T09:02:00Z']]))
                ->push($this->dashboard(['today' => ['status' => 'present', 'first_in_at' => '2026-10-13T09:02:00Z', 'last_out_at' => '2026-10-13T18:05:00Z', 'worked_minutes' => 543]])),
        ]);

        Native::test(Home::class)
            ->assertSee('Punch out')
            ->tap('Punch out')
            ->assertSee('Last out at 18:05 - 9h 3m worked')
            ->assertSee('Punch in');
    }

    public function test_a_punch_rejected_by_the_server_shows_its_message(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/dashboard' => Http::response($this->dashboard()),
            self::BASE.'/attendance/punch-in' => Http::response(['message' => 'You are already punched in.', 'errors' => ['punch' => ['You are already punched in.']]], 422),
        ]);

        Native::test(Home::class)
            ->tap('Punch in')
            ->assertSee('You are already punched in.');
    }

    public function test_an_expired_session_sends_the_user_to_login(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        Native::test(Home::class)->assertReplacedWith('/login');

        $this->assertFalse(app(TokenStore::class)->has());
    }

    public function test_home_survives_the_server_being_down(): void
    {
        $this->signIn();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));

        Native::test(Home::class)->assertSee('Cannot reach the server');

        $this->assertTrue(app(TokenStore::class)->has());
    }

    // ─── Attendance ─────────────────────────────────────────────────────────────

    public function test_attendance_lists_the_month_and_pages_between_months(): void
    {
        $this->signIn();
        $month = now()->format('Y-m');
        $previous = now()->subMonthNoOverflow()->format('Y-m');
        Http::fake(function (Request $r) use ($month) {
            return Http::response(['data' => str_contains($r->url(), "month={$month}") ? [
                ['work_date' => $month.'-02', 'first_in_at' => $month.'-02T09:05:00Z', 'last_out_at' => $month.'-02T18:00:00Z', 'worked_minutes' => 535, 'status' => 'late'],
                ['work_date' => $month.'-03', 'first_in_at' => null, 'last_out_at' => null, 'worked_minutes' => 0, 'status' => 'absent'],
            ] : []]);
        });

        Native::test(Attendance::class)
            ->assertSee(now()->format('F Y'))
            ->assertSee('In 09:05 - Out 18:00 - 8h 55m')
            ->assertSee('Late')
            ->assertSee('Absent')
            ->assertSee('No punches recorded')
            ->tap('Prev')
            ->assertSee(now()->subMonthNoOverflow()->format('F Y'))
            ->assertSee('No attendance recorded');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), "month={$previous}"));
    }

    // ─── Leave ──────────────────────────────────────────────────────────────────

    private function leavePage(): array
    {
        return ['data' => [
            ['id' => 5, 'leave_type' => ['name' => 'Annual Leave'], 'start_date' => '2026-11-02', 'end_date' => '2026-11-03', 'days' => '2.0', 'status' => 'pending', 'reason' => 'Trip'],
            ['id' => 4, 'leave_type' => ['name' => 'Sick Leave'], 'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'days' => '1.0', 'status' => 'approved', 'reason' => null],
        ]];
    }

    public function test_leave_screen_lists_balances_and_requests(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/leave-balances' => Http::response([['leave_type' => ['name' => 'Annual Leave'], 'allocated' => '18.0', 'remaining' => 16]]),
            self::BASE.'/leave-requests*' => Http::response($this->leavePage()),
        ]);

        Native::test(Leave::class)
            ->assertSee('16 / 18')
            ->assertSee('02 Nov - 03 Nov 2026 - 2 day(s)')
            ->assertSee('Pending')
            ->assertSee('01 Oct 2026 - 1 day(s)')
            ->assertSee('Approved')
            ->assertSee('Cancel request')          // only the pending one
            ->tap('Request leave')
            ->assertNavigatedTo('/leave/new');
    }

    public function test_cancelling_a_leave_request_calls_the_api(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/leave-balances' => Http::response([]),
            self::BASE.'/leave-requests/5/cancel' => Http::response(['id' => 5, 'status' => 'cancelled']),
            self::BASE.'/leave-requests*' => Http::response(['data' => []]),
        ]);

        Native::test(Leave::class)->call('cancel', 5);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/leave-requests/5/cancel');
    }

    public function test_new_leave_request_is_submitted_with_the_chosen_type_id(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/leave-types' => Http::response([['id' => 1, 'name' => 'Annual Leave'], ['id' => 2, 'name' => 'Sick Leave']]),
            self::BASE.'/leave-requests' => Http::response(['id' => 9], 201),
        ]);

        Native::test(LeaveCreate::class)
            ->set('typeName', 'Sick Leave')
            ->set('startDate', '2026-11-03')
            ->set('endDate', '2026-11-04')
            ->set('reason', 'Flu')
            ->tap('Submit request')
            ->assertWentBack();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/leave-requests'
            && $r['leave_type_id'] === 2 && $r['start_date'] === '2026-11-03' && $r['end_date'] === '2026-11-04' && $r['reason'] === 'Flu');
    }

    public function test_leave_request_requires_a_type_and_shows_server_validation(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/leave-types' => Http::response([['id' => 1, 'name' => 'Annual Leave']]),
            self::BASE.'/leave-requests' => Http::response(['message' => 'Insufficient leave balance.', 'errors' => ['leave_type_id' => ['Insufficient leave balance.']]], 422),
        ]);

        Native::test(LeaveCreate::class)
            ->tap('Submit request')
            ->assertSee('Choose a leave type.')
            ->set('typeName', 'Annual Leave')
            ->tap('Submit request')
            ->assertSee('Insufficient leave balance.')
            ->assertNoNavigation();
    }

    // ─── Overtime ───────────────────────────────────────────────────────────────

    public function test_overtime_lists_requests_and_opens_the_form(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response(['data' => [
            ['work_date' => '2026-10-10', 'minutes' => 90, 'reason' => 'Release', 'status' => 'approved'],
        ]])]);

        Native::test(Overtime::class)
            ->assertSee('Sat, 10 Oct 2026')
            ->assertSee('1h 30m')
            ->assertSee('Release')
            ->assertSee('Approved')
            ->tap('Request overtime')
            ->assertNavigatedTo('/overtime/new');
    }

    public function test_overtime_shows_the_current_rate_and_each_requests_own_rate(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/overtime-rate' => Http::response(['rate' => '200.00', 'effective_from' => '2026-11-05T09:00:00Z', 'history' => []]),
            self::BASE.'/overtime-requests*' => Http::response(['data' => [
                // submitted AFTER the raise
                ['work_date' => '2026-11-06', 'minutes' => 60, 'rate_per_hour' => '200.00', 'amount' => '200.00', 'reason' => null, 'status' => 'pending'],
                // submitted BEFORE the raise: must keep showing 150, not 200
                ['work_date' => '2026-10-30', 'minutes' => 120, 'rate_per_hour' => '150.00', 'amount' => '300.00', 'reason' => null, 'status' => 'approved'],
                // submitted before any rate existed
                ['work_date' => '2026-10-01', 'minutes' => 30, 'rate_per_hour' => null, 'amount' => null, 'reason' => null, 'status' => 'approved'],
            ]]),
        ]);

        Native::test(Overtime::class)
            ->assertSee('Your hourly overtime rate')
            ->assertSee('200.00 per hour')
            ->assertSee('1h 0m - 200.00 (200.00 per hour)')
            ->assertSee('2h 0m - 300.00 (150.00 per hour)')
            ->assertSee('0h 30m - No rate set')
            ->assertSee('Change rate')
            ->tap('Change rate')
            ->assertNavigatedTo('/overtime/rate');
    }

    public function test_overtime_without_a_rate_offers_to_set_one(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/overtime-rate' => Http::response(['rate' => null, 'effective_from' => null, 'history' => []]),
            self::BASE.'/overtime-requests*' => Http::response(['data' => []]),
        ]);

        Native::test(Overtime::class)->assertSee('Not set')->assertSee('Set rate');
    }

    public function test_changing_the_overtime_rate_sends_the_new_rate_and_goes_back(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/overtime-rate' => Http::sequence()
                ->push(['rate' => '150.00', 'effective_from' => '2026-10-01T00:00:00Z', 'history' => [['hourly_rate' => '150.00', 'effective_from' => '2026-10-01T00:00:00Z']]])
                ->push(['rate' => '175.50', 'effective_from' => '2026-11-05T09:00:00Z', 'history' => [
                    ['hourly_rate' => '175.50', 'effective_from' => '2026-11-05T09:00:00Z'],
                    ['hourly_rate' => '150.00', 'effective_from' => '2026-10-01T00:00:00Z'],
                ]]),
        ]);

        Native::test(OvertimeRate::class)
            ->assertSee('150.00 per hour')
            ->assertSet('hourlyRate', '150.00')                       // prefilled with the current rate
            ->assertSee('Overtime you already submitted keeps the rate')
            ->set('hourlyRate', '175.50')
            ->tap('Save rate')
            ->assertWentBack();

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && $r->url() === self::BASE.'/overtime-rate' && $r['hourly_rate'] === '175.50');
    }

    public function test_overtime_rate_is_validated_before_calling_the_server(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/overtime-rate' => Http::response(['rate' => null, 'effective_from' => null, 'history' => []])]);

        foreach (['', 'abc', '-5', '12.345', '1000000'] as $bad) {
            Native::test(OvertimeRate::class)
                ->set('hourlyRate', $bad)
                ->tap('Save rate')
                ->assertSee('Enter an amount like 150 or 175.50.')
                ->assertNoNavigation();
        }

        Http::assertNotSent(fn (Request $r) => $r->method() === 'PUT');
    }

    public function test_overtime_rate_server_errors_are_shown(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/overtime-rate' => fn (Request $r) => $r->method() === 'PUT'
                ? Http::response(['message' => 'The hourly rate must not be greater than 99999.99.', 'errors' => ['hourly_rate' => ['The hourly rate must not be greater than 99999.99.']]], 422)
                : Http::response(['rate' => null, 'effective_from' => null, 'history' => []]),
        ]);

        Native::test(OvertimeRate::class)
            ->set('hourlyRate', '500')
            ->tap('Save rate')
            ->assertSee('must not be greater than 99999.99')
            ->assertNoNavigation();
    }

    public function test_rate_history_is_listed_when_there_has_been_a_change(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/overtime-rate' => Http::response(['rate' => '200.00', 'effective_from' => '2026-11-05T09:00:00Z', 'history' => [
            ['hourly_rate' => '200.00', 'effective_from' => '2026-11-05T09:00:00Z'],
            ['hourly_rate' => '150.00', 'effective_from' => '2026-10-01T00:00:00Z'],
        ]])]);

        Native::test(OvertimeRate::class)
            ->assertSee('Rate history')
            ->assertSee('from 05 Nov 2026')
            ->assertSee('150.00 per hour')
            ->assertSee('from 01 Oct 2026');
    }

    public function test_overtime_form_validates_minutes_locally_then_submits(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response(['id' => 1], 201)]);

        Native::test(OvertimeCreate::class)
            ->set('minutes', 'abc')
            ->tap('Submit request')
            ->assertSee('Enter the number of minutes worked')
            ->set('workDate', '2026-10-10')
            ->set('minutes', '120')
            ->set('reason', 'Deadline')
            ->tap('Submit request')
            ->assertWentBack();

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/overtime-requests'
            && $r['work_date'] === '2026-10-10' && $r['minutes'] === 120 && $r['reason'] === 'Deadline');
    }

    // ─── Holidays ───────────────────────────────────────────────────────────────

    public function test_holidays_for_a_year_and_year_navigation(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response([['name' => 'Christmas Day', 'date' => '2026-12-25', 'is_optional' => false], ['name' => 'Holi', 'date' => '2026-03-04', 'is_optional' => true]])]);

        $year = (int) now()->year;

        Native::test(Holidays::class)
            ->assertSee((string) $year)
            ->assertSee('Christmas Day')
            ->assertSee('Optional')
            ->tap('Next')
            ->assertSee((string) ($year + 1));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'year='.($year + 1)));
    }

    // ─── Companies ──────────────────────────────────────────────────────────────

    private function company(array $o = []): array
    {
        return $o + [
            'id' => 3, 'name' => 'Acme Ltd', 'industry' => 'Software', 'designation' => 'Engineer', 'website' => 'https://acme.example.com',
            'phone' => null, 'address' => null, 'started_on' => '2022-01-10', 'ended_on' => null, 'is_current' => true,
        ];
    }

    public function test_companies_list_shows_period_and_current_flag(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response([
            $this->company(),
            $this->company(['id' => 4, 'name' => 'Old Co', 'started_on' => '2019-03-01', 'ended_on' => '2021-12-31', 'is_current' => false]),
        ])]);

        Native::test(Companies::class)
            ->assertSee('Acme Ltd')
            ->assertSee('Jan 2022 - Present')
            ->assertSee('Current')
            ->assertSee('Old Co')
            ->assertSee('Mar 2019 - Dec 2021');
    }

    public function test_editing_a_company_opens_the_form_prefilled(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response([$this->company()])]);

        $list = Native::test(Companies::class)->call('edit', 3)->assertNavigatedTo('/companies/form');
        $form = $list->follow();

        $form->assertScreen(CompanyForm::class)
            ->assertSet('companyId', 3)
            ->assertSet('name', 'Acme Ltd')
            ->assertSet('isCurrent', true)
            ->assertNavTitle('Edit company');
    }

    public function test_current_company_hides_the_end_date_and_sends_it_as_null(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response($this->company(), 201)]);

        Native::test(CompanyForm::class)
            ->assertSet('isCurrent', true)
            ->assertDontSee('Ended on')
            ->set('name', 'Acme Ltd')
            ->set('endedOn', '2024-01-01')          // stale value must not be sent while current
            ->tap('Save')
            ->assertWentBack();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/companies'
            && $r['name'] === 'Acme Ltd' && $r['is_current'] === true && $r['ended_on'] === null);
    }

    public function test_past_company_shows_and_sends_start_and_end_dates(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response($this->company(['is_current' => false]), 201)]);

        Native::test(CompanyForm::class)
            ->set('name', 'Old Co')
            ->set('isCurrent', false)
            ->assertSee('Ended on')
            ->set('startedOn', '2019-03-01')
            ->set('endedOn', '2021-12-31')
            ->tap('Save')
            ->assertWentBack();

        Http::assertSent(fn (Request $r) => $r['is_current'] === false
            && $r['started_on'] === '2019-03-01' && $r['ended_on'] === '2021-12-31');
    }

    public function test_backend_date_rules_are_shown_next_to_the_fields(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response([
            'message' => 'Enter the date you left, since this is not your current employer.',
            'errors' => ['ended_on' => ['Enter the date you left, since this is not your current employer.']],
        ], 422)]);

        Native::test(CompanyForm::class)
            ->set('name', 'Old Co')
            ->set('isCurrent', false)
            ->set('startedOn', '2019-03-01')
            ->tap('Save')
            ->assertSee('Enter the date you left')
            ->assertNoNavigation();
    }

    public function test_removing_a_company_calls_delete(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response([$this->company()])]);

        Native::test(Companies::class)->call('remove', 3);

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::BASE.'/companies/3');
    }

    // ─── More ───────────────────────────────────────────────────────────────────

    public function test_more_shows_the_profile_and_signs_out(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/me' => Http::response([
                'name' => 'Alice Employee', 'email' => 'alice@example.com',
                'employee' => ['employee_code' => 'EMP001', 'designation' => 'Software Engineer', 'department' => ['name' => 'Engineering'],
                    'shift' => ['name' => 'General', 'start_time' => '09:00:00', 'end_time' => '18:00:00']],
            ]),
            self::BASE.'/auth/logout' => Http::response(['message' => 'Logged out.']),
        ]);

        Native::test(More::class)
            ->assertSee('Alice Employee')
            ->assertSee('alice@example.com')
            ->assertSee('EMP001')
            ->assertSee('General (09:00 - 18:00)')
            ->tap('Sign out')
            ->assertReplacedWith('/login');

        $this->assertFalse(app(TokenStore::class)->has());
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/auth/logout');
    }

    // ─── Navigation chrome ──────────────────────────────────────────────────────

    public function test_tabs_are_registered_and_the_right_one_is_active(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response($this->dashboard())]);

        Native::visit('/home')
            ->assertScreen(Home::class)
            ->assertHasTabBar()
            ->assertHasTab('Home')
            ->assertHasTab('Attendance')
            ->assertHasTab('Leave')
            ->assertHasTab('Overtime')
            ->assertHasTab('More')
            ->assertTabActive('Home');
    }

    public function test_pushed_screens_have_no_tab_bar(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response([])]);

        Native::visit('/leave/new')->assertScreen(LeaveCreate::class)->assertTabBarHidden();
    }
}
