<?php

namespace Tests\Feature;

use App\NativeComponents\Attendance;
use App\NativeComponents\Boot;
use App\NativeComponents\Companies;
use App\NativeComponents\CompanyForm;
use App\NativeComponents\Earning;
use App\NativeComponents\Holidays;
use App\NativeComponents\Home;
use App\NativeComponents\Leave;
use App\NativeComponents\Login;
use App\NativeComponents\ManualHoursCreate;
use App\NativeComponents\More;
use App\NativeComponents\Overtime;
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

    /** Every day of $month, defaulted to unmarked; $overrides replaces specific days by work_date. */
    private function calendarDays(string $month, array $overrides = []): array
    {
        $start = \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $overridesByDate = collect($overrides)->keyBy('work_date');

        return collect(range(1, $start->daysInMonth))->map(function (int $day) use ($start, $overridesByDate) {
            $date = $start->copy()->day($day)->toDateString();

            return $overridesByDate->get($date) ?? [
                'work_date' => $date,
                'status' => '',
                'leave_type' => null,
                'first_in_at' => null,
                'last_out_at' => null,
                'worked_minutes' => 0,
                'regular_minutes' => 0,
                'overtime_minutes' => 0,
                'overtime_amount' => null,
            ];
        })->values()->all();
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
        Http::fake([
            self::BASE.'/dashboard' => Http::response($this->dashboard()),
            self::BASE.'/attendance/totals*' => Http::response([
                'month' => ['regular_minutes' => 125, 'overtime_minutes' => 60, 'overtime_amount' => '200.00'],
                'year' => ['regular_minutes' => 6005, 'overtime_minutes' => 600, 'overtime_amount' => '2000.00'],
                'lifetime' => ['overtime_minutes' => 900, 'overtime_amount' => '3000.00'],
            ]),
        ]);

        Native::test(Home::class)
            ->assertSee('Hello, Alice')
            ->assertSee('Software Engineer - Engineering')
            ->assertSee('Not punched in')
            ->assertDontSee('Punch in')
            ->assertDontSee('Punch out')
            ->assertSee('Annual Leave')
            ->assertSee('16 / 18')
            ->assertSee('2 used')
            ->assertDontSee('Unpaid Leave')            // zero-allocation types are hidden
            ->assertSee('Diwali')
            ->assertSee('This month')
            ->assertSee('2h 5m')
            ->assertSee('1h 0m')
            ->assertSee('200.00')
            ->assertSee('Overtime total (all-time)')
            ->assertSee('3000.00')
            ->assertSee('This year')
            ->assertSee('100h 5m')
            ->assertSee('10h 0m')
            ->assertSee('2000.00');
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
        Http::fake(function (Request $r) use ($month, $previous) {
            if (str_contains($r->url(), "month={$month}")) {
                return Http::response($this->calendarDays($month, [
                    ['work_date' => $month.'-02', 'status' => 'late', 'leave_type' => null, 'first_in_at' => $month.'-02T09:05:00Z', 'last_out_at' => $month.'-02T18:00:00Z', 'worked_minutes' => 535, 'regular_minutes' => 535, 'overtime_minutes' => 0, 'overtime_amount' => null],
                    ['work_date' => $month.'-03', 'status' => 'absent', 'leave_type' => null, 'first_in_at' => null, 'last_out_at' => null, 'worked_minutes' => 0, 'regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null],
                ]));
            }

            return Http::response($this->calendarDays($previous));
        });

        // Detail (in/out, regular/overtime) now lives in a modal opened by tapping a day;
        // selectDay() is the direct-call bypass, same pattern as Leave::confirmCancel()/cancel().
        Native::test(Attendance::class)
            ->assertSee(now()->format('F Y'))
            ->call('selectDay', $month.'-02')
            ->assertSee('In 09:05 - Out 18:00 - 8h 55m')
            ->assertSee('Late')
            ->tap('Close')
            ->assertDontSee('In 09:05 - Out 18:00 - 8h 55m')
            ->call('selectDay', $month.'-03')
            ->assertSee('Absent')
            ->assertSee('No punches recorded')
            ->tap('Prev')
            ->assertSee(now()->subMonthNoOverflow()->format('F Y'))
            // Every day of the month comes back from the backend already, even fully unmarked ones.
            ->call('selectDay', $previous.'-01')
            ->assertSee('No punches recorded');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), "month={$previous}"));
    }

    public function test_attendance_shows_leave_and_logged_statuses_distinctly(): void
    {
        $this->signIn();
        $month = now()->format('Y-m');
        Http::fake([
            self::BASE.'/attendance/calendar?*' => Http::response($this->calendarDays($month, [
                ['work_date' => $month.'-02', 'status' => 'leave', 'leave_type' => 'Annual Leave', 'first_in_at' => null, 'last_out_at' => null, 'worked_minutes' => 0, 'regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null],
                ['work_date' => $month.'-03', 'status' => 'logged', 'leave_type' => null, 'first_in_at' => null, 'last_out_at' => null, 'worked_minutes' => 0, 'regular_minutes' => 90, 'overtime_minutes' => 0, 'overtime_amount' => null],
            ])),
            self::BASE.'/attendance/totals*' => Http::response(['month' => [], 'year' => []]),
        ]);

        Native::test(Attendance::class)
            ->call('selectDay', $month.'-02')
            ->assertSee('Annual Leave')
            ->assertSee('On leave')
            ->tap('Close')
            ->call('selectDay', $month.'-03')
            ->assertSee('Logged')
            ->assertSee('Logged manually (no punch)');
    }

    public function test_attendance_shows_only_this_months_totals(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/attendance/calendar?*' => Http::response($this->calendarDays(now()->format('Y-m'))),
            self::BASE.'/attendance/totals*' => Http::response([
                'month' => ['regular_minutes' => 125, 'overtime_minutes' => 60, 'overtime_amount' => '200.00'],
                'year' => ['regular_minutes' => 6005, 'overtime_minutes' => 600, 'overtime_amount' => '2000.00'],
            ]),
        ]);

        Native::test(Attendance::class)
            ->assertSee('This month')
            ->assertSee('2h 5m')
            ->assertSee('1h 0m')
            ->assertSee('200.00')
            ->assertDontSee('This year')
            ->assertDontSee('100h 5m');
    }

    public function test_tapping_log_hours_navigates_to_the_combined_entry_screen_prefilled(): void
    {
        $this->signIn();
        $date = now()->format('Y-m').'-02';
        Http::fake([
            self::BASE.'/attendance/calendar?*' => Http::response($this->calendarDays(now()->format('Y-m'))),
            self::BASE.'/attendance/totals*' => Http::response(['month' => [], 'year' => []]),
            self::BASE.'/attendance/manual?*' => Http::response([
                'regular_minutes' => 90, 'overtime_minutes' => 60, 'overtime_amount' => '200.00',
            ]),
        ]);

        $list = Native::test(Attendance::class)
            ->call('selectDay', $date)
            ->call('logHours')
            ->assertNavigatedTo('/attendance/manual');

        $form = $list->follow();

        $form->assertScreen(ManualHoursCreate::class)
            ->assertSet('workDate', $date)
            ->assertSet('regularHours', '1.5')
            ->assertSet('overtimeHours', '1')
            ->assertNavTitle('Log hours');
    }

    public function test_manual_hours_create_prefills_blank_when_nothing_was_logged_yet(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/attendance/manual?*' => Http::response([
            'regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null,
        ])]);

        Native::test(ManualHoursCreate::class, [], ['date' => '2026-11-02'])
            ->assertSet('regularHours', '')
            ->assertSet('overtimeHours', '');
    }

    public function test_manual_hours_create_submits_regular_minutes(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/attendance/manual?*' => Http::response(['regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null]),
            self::BASE.'/attendance/manual' => Http::response(['minutes' => 90], 201),
        ]);

        Native::test(ManualHoursCreate::class, [], ['date' => '2026-11-02'])
            ->set('regularHours', '1.5')
            ->tap('Save')
            ->assertWentBack();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/attendance/manual'
            && $r->data()['work_date'] === '2026-11-02' && $r->data()['minutes'] === 90);
    }

    public function test_manual_hours_create_submits_overtime_via_the_existing_endpoint(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/attendance/manual?*' => Http::response(['regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null]),
            self::BASE.'/overtime-requests' => Http::response(['minutes' => 30, 'amount' => '100.00'], 201),
        ]);

        Native::test(ManualHoursCreate::class, [], ['date' => '2026-11-02'])
            ->set('overtimeHours', '0.5')
            ->tap('Save')
            ->assertWentBack();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/overtime-requests'
            && $r->data()['work_date'] === '2026-11-02' && $r->data()['minutes'] === 30);
    }

    public function test_manual_hours_create_submits_both_regular_and_overtime_together(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/attendance/manual?*' => Http::response(['regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null]),
            self::BASE.'/attendance/manual' => Http::response(['minutes' => 120], 201),
            self::BASE.'/overtime-requests' => Http::response(['minutes' => 60, 'amount' => '100.00'], 201),
        ]);

        Native::test(ManualHoursCreate::class, [], ['date' => '2026-11-02'])
            ->set('regularHours', '2')
            ->set('overtimeHours', '1')
            ->tap('Save')
            ->assertWentBack();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/attendance/manual' && $r->data()['minutes'] === 120);
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/overtime-requests' && $r->data()['minutes'] === 60);
    }

    public function test_manual_hours_create_sends_nothing_when_resubmitted_unchanged(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/attendance/manual?*' => Http::response([
            'regular_minutes' => 90, 'overtime_minutes' => 60, 'overtime_amount' => '200.00',
        ])]);

        Native::test(ManualHoursCreate::class, [], ['date' => '2026-11-02'])
            ->assertSet('regularHours', '1.5')
            ->assertSet('overtimeHours', '1')
            ->tap('Save')
            ->assertWentBack();

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    public function test_manual_hours_create_only_resubmits_the_field_that_changed(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/attendance/manual?*' => Http::response(['regular_minutes' => 90, 'overtime_minutes' => 60, 'overtime_amount' => '200.00']),
            self::BASE.'/overtime-requests' => Http::response(['minutes' => 120, 'amount' => '400.00'], 201),
        ]);

        Native::test(ManualHoursCreate::class, [], ['date' => '2026-11-02'])
            ->assertSet('regularHours', '1.5')
            ->set('overtimeHours', '2')
            ->tap('Save')
            ->assertWentBack();

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/attendance/manual');
        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === self::BASE.'/overtime-requests' && $r->data()['minutes'] === 120);
    }

    public function test_manual_hours_create_validates_hours_before_calling_the_server(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/attendance/manual?*' => Http::response(['regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null])]);

        Native::test(ManualHoursCreate::class, [], ['date' => '2026-11-02'])
            ->set('regularHours', 'abc')
            ->tap('Save')
            ->assertSee('Enter a number of hours greater than 0');

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    public function test_manual_hours_create_rejects_more_than_24_hours(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/attendance/manual?*' => Http::response(['regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null])]);

        Native::test(ManualHoursCreate::class, [], ['date' => '2026-11-02'])
            ->set('overtimeHours', '25')
            ->tap('Save')
            ->assertSee('24');

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    public function test_manual_hours_create_requires_at_least_one_field(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/attendance/manual?*' => Http::response(['regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null])]);

        Native::test(ManualHoursCreate::class, [], ['date' => '2026-11-02'])
            ->tap('Save')
            ->assertSee('Enter regular and/or overtime hours');

        Http::assertNotSent(fn (Request $r) => $r->method() === 'POST');
    }

    // ─── Leave ──────────────────────────────────────────────────────────────────

    private function leavePage(): array
    {
        return ['data' => [
            ['id' => 5, 'leave_type' => ['name' => 'Annual Leave'], 'start_date' => '2026-11-02', 'end_date' => '2026-11-03', 'days' => '2.0', 'status' => 'approved', 'reason' => 'Trip'],
            ['id' => 4, 'leave_type' => ['name' => 'Sick Leave'], 'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'days' => '1.0', 'status' => 'cancelled', 'reason' => null],
        ]];
    }

    public function test_leave_screen_lists_balances_and_requests(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/leave-balances' => Http::response([['leave_type' => ['name' => 'Annual Leave'], 'allocated' => '18.0', 'used' => '2.0', 'remaining' => 16]]),
            self::BASE.'/leave-requests*' => Http::response($this->leavePage()),
        ]);

        Native::test(Leave::class)
            ->assertSee('16 / 18')
            ->assertSee('2 used')
            ->assertSee('02 Nov - 03 Nov 2026 - 2 day(s)')
            ->assertSee('Approved')
            ->assertSee('01 Oct 2026 - 1 day(s)')
            ->assertSee('Cancelled')
            ->assertSee('Cancel request');         // only the still-approved one
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

    // ─── Overtime ───────────────────────────────────────────────────────────────

    public function test_overtime_lists_requests(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response(['data' => [
            ['work_date' => '2026-10-10', 'minutes' => 90, 'reason' => 'Release', 'status' => 'approved'],
        ]])]);

        Native::test(Overtime::class)
            ->assertSee('Sat, 10 Oct 2026')
            ->assertSee('1h 30m')
            ->assertSee('Release')
            ->assertSee('Approved');
    }

    public function test_overtime_shows_the_current_rate_and_each_requests_own_rate(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/overtime-rate' => Http::response(['rate' => '200.00', 'effective_from' => '2026-11-05T09:00:00Z', 'history' => []]),
            self::BASE.'/overtime-requests*' => Http::response(['data' => [
                // submitted AFTER the raise
                ['work_date' => '2026-11-06', 'minutes' => 60, 'rate_per_hour' => '200.00', 'amount' => '200.00', 'reason' => null, 'status' => 'approved'],
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

    public function test_more_links_to_earning(): void
    {
        $this->signIn();
        Http::fake(['*' => Http::response([])]);

        Native::test(More::class)->tap('Earning')->assertNavigatedTo('/earning');
    }

    // ─── Earning ────────────────────────────────────────────────────────────────

    public function test_earning_shows_the_default_monthly_period(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/earnings*' => Http::response([
            'months' => 1, 'from' => '2026-11-01', 'to' => '2026-11-30',
            'regular_minutes' => 125, 'overtime_minutes' => 60, 'overtime_amount' => '200.00', 'leave_days' => '7.0',
        ])]);

        Native::test(Earning::class)
            ->assertSee('01 Nov 2026 - 30 Nov 2026')
            ->assertSee('2h 5m')
            ->assertSee('1h 0m')
            ->assertSee('200.00')
            ->assertSee('Leave days')
            ->assertSee('7');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/earnings?months=1');
    }

    public function test_earning_switches_between_periods(): void
    {
        $this->signIn();
        Http::fake([
            self::BASE.'/earnings*' => Http::response([
                'months' => 3, 'from' => '2026-09-01', 'to' => '2026-11-30',
                'regular_minutes' => 6005, 'overtime_minutes' => 600, 'overtime_amount' => '2000.00', 'leave_days' => '7.0',
            ]),
        ]);

        Native::test(Earning::class)
            ->tap('3 months')
            ->assertSee('100h 5m')
            ->assertSee('10h 0m')
            ->assertSee('2000.00')
            ->assertSee('7');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/earnings?months=3');
    }

    public function test_earning_applies_a_custom_date_range(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/earnings*' => Http::response([
            'months' => null, 'from' => '2026-08-01', 'to' => '2026-08-31',
            'regular_minutes' => 60, 'overtime_minutes' => 30, 'overtime_amount' => '100.00', 'leave_days' => '0.0',
        ])]);

        Native::test(Earning::class)
            ->tap('Custom range')
            ->assertPicker('From', 'date')
            ->pickDate('setCustomFrom', '2026-08-01')
            ->pickDate('setCustomTo', '2026-08-31')
            ->tap('Apply')
            ->assertSee('01 Aug 2026 - 31 Aug 2026')
            ->assertSee('1h 0m')
            ->assertSee('100.00');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/earnings?from=2026-08-01&to=2026-08-31');
    }

    public function test_earning_custom_range_validates_before_calling_the_server(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/earnings*' => Http::response([
            'months' => 1, 'from' => '2026-11-01', 'to' => '2026-11-30',
            'regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null, 'leave_days' => '0.0',
        ])]);

        Native::test(Earning::class)
            ->tap('Custom range')
            ->pickDate('setCustomFrom', '2026-08-31')
            ->pickDate('setCustomTo', '2026-08-01')
            ->tap('Apply')
            ->assertSee('The start date must be before the end date.');

        Http::assertSentCount(1); // only the initial monthly load, not the invalid custom range
    }

    public function test_earning_custom_range_prefills_a_default_window(): void
    {
        $this->signIn();
        $from = now()->subDays(30)->toDateString();
        $to = now()->toDateString();
        Http::fake([self::BASE.'/earnings*' => Http::response([
            'months' => null, 'from' => $from, 'to' => $to,
            'regular_minutes' => 0, 'overtime_minutes' => 0, 'overtime_amount' => null, 'leave_days' => '0.0',
        ])]);

        // The picker can't start empty - iOS's compact date-picker never commits a selection
        // until it has adopted a non-empty value at least once, so a sensible default window
        // is pre-filled the moment the custom-range panel opens.
        Native::test(Earning::class)
            ->tap('Custom range')
            ->assertPickerValue('From', $from)
            ->assertPickerValue('To', $to)
            ->tap('Apply');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE."/earnings?from={$from}&to={$to}");
    }

    public function test_earning_custom_range_deselects_the_preset_tabs(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/earnings*' => Http::response([
            'months' => null, 'from' => '2026-08-01', 'to' => '2026-08-31',
            'regular_minutes' => 60, 'overtime_minutes' => 0, 'overtime_amount' => null, 'leave_days' => '0.0',
        ])]);

        $earning = Native::test(Earning::class)
            ->tap('Custom range')
            ->assertSet('showingCustomInputs', true)
            ->assertSet('useCustomRange', false);

        // The "1 month" preset is still the last-known $months value, but it must not render
        // as selected while the custom-range panel is open — this is the bug being guarded.
        $earning->pickDate('setCustomFrom', '2026-08-01')
            ->pickDate('setCustomTo', '2026-08-31')
            ->tap('Apply')
            ->assertSet('useCustomRange', true);
    }

    public function test_earning_adds_a_yearly_preset(): void
    {
        $this->signIn();
        Http::fake([self::BASE.'/earnings*' => Http::response([
            'months' => 12, 'from' => '2025-12-01', 'to' => '2026-11-30',
            'regular_minutes' => 6060, 'overtime_minutes' => 120, 'overtime_amount' => '400.00', 'leave_days' => '3.0',
        ])]);

        Native::test(Earning::class)
            ->tap('1 year')
            ->assertSee('01 Dec 2025 - 30 Nov 2026')
            ->assertSee('101h 0m')
            ->assertSee('2h 0m')
            ->assertSee('400.00');

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/earnings?months=12');
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

        Native::visit('/overtime/rate')->assertScreen(OvertimeRate::class)->assertTabBarHidden();
    }
}
