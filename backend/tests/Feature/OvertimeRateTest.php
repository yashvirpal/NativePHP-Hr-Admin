<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\OvertimeRate;
use App\Models\OvertimeRequest;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

class OvertimeRateTest extends ApiTestCase
{
    private function request(int $minutes = 90, string $date = '2026-11-01'): array
    {
        return $this->postJson('/api/v1/overtime-requests', [
            'work_date' => $date, 'minutes' => $minutes, 'reason' => 'Deadline',
        ])->assertCreated()->json();
    }

    // ─── Setting and reading the rate ───────────────────────────────────────────

    public function test_an_employee_without_a_rate_sees_null(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/v1/overtime-rate')->assertOk()
            ->assertJsonPath('rate', null)->assertJsonPath('history', []);
    }

    public function test_employee_sets_their_hourly_rate(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 150])->assertOk()
            ->assertJsonPath('rate', '150.00')
            ->assertJsonCount(1, 'history');

        $this->getJson('/api/v1/overtime-rate')->assertOk()->assertJsonPath('rate', '150.00');
        $this->assertDatabaseHas('overtime_rates', ['employee_id' => $user->employee->id, 'hourly_rate' => '150.00']);
    }

    public function test_setting_the_same_rate_again_adds_no_history_row(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => '150.00'])->assertOk();
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 150])->assertOk()->assertJsonCount(1, 'history');

        $this->assertSame(1, OvertimeRate::where('employee_id', $user->employee->id)->count());
    }

    public function test_history_lists_every_change_newest_first(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 100])->assertOk();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 120])->assertOk();
        Carbon::setTestNow('2026-11-20 09:00:00');
        $response = $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 140])->assertOk();

        $this->assertSame(['140.00', '120.00', '100.00'], array_column($response->json('history'), 'hourly_rate'));
        $this->assertSame('140.00', $response->json('rate'));
    }

    // ─── The requirement: a new rate never rewrites old overtime ────────────────

    public function test_a_request_is_priced_with_the_rate_in_force_when_it_is_submitted(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 150]);

        $this->request(90);

        $this->assertSame('150.00', $this->lastRequest()->rate_per_hour);
        $this->assertSame('225.00', $this->lastRequest()->amount); // 1.5 h x 150
    }

    public function test_changing_the_rate_does_not_change_old_overtime_but_applies_to_new_overtime(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 150]);
        $oldId = $this->request(120, '2026-10-30')['id'];            // 2 h at 150 = 300.00

        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 200])->assertOk()->assertJsonPath('rate', '200.00');
        $newId = $this->request(60, '2026-11-04')['id'];             // 1 h at 200 = 200.00

        $old = OvertimeRequest::find($oldId);
        $new = OvertimeRequest::find($newId);

        // Old request: untouched by the raise.
        $this->assertSame('150.00', $old->rate_per_hour);
        $this->assertSame('300.00', $old->amount);
        // New request: priced at the new rate.
        $this->assertSame('200.00', $new->rate_per_hour);
        $this->assertSame('200.00', $new->amount);

        // Reading the list back through the API shows the same snapshots.
        $list = collect($this->getJson('/api/v1/overtime-requests')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame('300.00', $list[$oldId]['amount']);
        $this->assertSame('150.00', $list[$oldId]['rate_per_hour']);
        $this->assertSame('200.00', $list[$newId]['amount']);
    }

    public function test_lowering_the_rate_also_leaves_old_requests_alone(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 300]);
        $id = $this->request(60)['id'];

        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 100]);

        $this->assertSame('300.00', OvertimeRequest::find($id)->amount);
    }

    public function test_a_request_decided_after_a_rate_change_keeps_its_amount(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 150]);
        $id = $this->request(60)['id'];
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 999]);

        $admin = $this->makeAdmin('manager');
        $this->actingAs($admin, 'admin')->post("/admin/overtime-requests/{$id}/approve")->assertRedirect();

        $request = OvertimeRequest::find($id);
        $this->assertSame('approved', $request->status);
        $this->assertSame('150.00', $request->amount);
    }

    public function test_requests_made_before_any_rate_was_set_stay_unpriced(): void
    {
        Sanctum::actingAs($this->makeUser());
        $id = $this->request(60)['id'];                   // no rate yet
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 150]);

        $request = OvertimeRequest::find($id);
        $this->assertNull($request->rate_per_hour);
        $this->assertNull($request->amount);
    }

    public function test_amount_is_rounded_to_two_decimals(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 99.99]);

        $id = $this->request(45)['id'];                   // 0.75 h x 99.99 = 74.9925

        $this->assertSame('74.99', OvertimeRequest::find($id)->amount);
    }

    public function test_zero_is_a_valid_rate(): void
    {
        Sanctum::actingAs($this->makeUser());
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 0])->assertOk()->assertJsonPath('rate', '0.00');

        $this->assertSame('0.00', OvertimeRequest::find($this->request(60)['id'])->amount);
    }

    // ─── Validation and isolation ───────────────────────────────────────────────

    public function test_invalid_rates_are_rejected(): void
    {
        Sanctum::actingAs($this->makeUser());

        foreach ([-1, 'abc', 100000, 12.345, null, ''] as $bad) {
            $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => $bad])
                ->assertUnprocessable()->assertJsonValidationErrors('hourly_rate');
        }

        $this->putJson('/api/v1/overtime-rate', [])->assertUnprocessable();
        $this->assertSame(0, OvertimeRate::count());
    }

    public function test_rates_are_per_employee(): void
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();

        Sanctum::actingAs($alice);
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 500]);

        Sanctum::actingAs($bob);
        $this->getJson('/api/v1/overtime-rate')->assertJsonPath('rate', null);
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 80]);

        Sanctum::actingAs($alice->fresh());
        $this->getJson('/api/v1/overtime-rate')->assertJsonPath('rate', '500.00');
    }

    public function test_the_rate_endpoints_need_a_signed_in_employee(): void
    {
        $this->getJson('/api/v1/overtime-rate')->assertUnauthorized();
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 10])->assertUnauthorized();
    }

    // ─── Web portals ────────────────────────────────────────────────────────────

    public function test_employee_portal_shows_rate_and_each_requests_own_amount(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'web');

        $this->put('/employee/overtime/rate', ['hourly_rate' => '150'])->assertRedirect('/employee/overtime');
        $this->post('/employee/overtime', ['work_date' => '2026-11-01', 'minutes' => 120]);   // 300.00 at 150

        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->put('/employee/overtime/rate', ['hourly_rate' => '200']);
        $this->post('/employee/overtime', ['work_date' => '2026-11-04', 'minutes' => 60]);    // 200.00 at 200

        $this->get('/employee/overtime')->assertOk()
            ->assertSee('200.00')           // current rate and the new request
            ->assertSee('300.00')           // the OLD request still shows its old amount
            ->assertSee('150.00')           // ...and the rate it was priced with
            ->assertSee('Rate history');
    }

    public function test_employee_portal_rate_form_validates(): void
    {
        $this->actingAs($this->makeUser(), 'web');

        $this->put('/employee/overtime/rate', ['hourly_rate' => '-5'])->assertSessionHasErrors('hourly_rate');
        $this->assertSame(0, OvertimeRate::count());
    }

    public function test_admin_overtime_page_shows_the_snapshot_columns(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/overtime-rate', ['hourly_rate' => 175]);
        $this->request(60);

        $this->actingAs($this->makeAdmin('manager'), 'admin')->get('/admin/overtime-requests')->assertOk()
            ->assertSee('Rate / hour')->assertSee('Amount')->assertSee('175.00');
    }

    // ─── Seeded data ────────────────────────────────────────────────────────────

    public function test_seed_data_has_rate_history_and_old_requests_keep_their_old_rate(): void
    {
        $this->seed();

        $this->assertGreaterThanOrEqual(10, OvertimeRate::count());

        // Some employees got a raise; at least one seeded request predates it and so carries a rate
        // that differs from that employee's current rate.
        $stale = OvertimeRequest::whereNotNull('rate_per_hour')->get()->filter(function (OvertimeRequest $r) {
            $current = OvertimeRate::where('employee_id', $r->employee_id)->orderByDesc('effective_from')->orderByDesc('id')->value('hourly_rate');

            return $current !== null && $current !== $r->rate_per_hour;
        });
        $this->assertGreaterThan(0, $stale->count());

        // And nothing is mispriced: amount always equals minutes x its own snapshot rate.
        foreach (OvertimeRequest::whereNotNull('rate_per_hour')->get() as $r) {
            $this->assertSame(number_format(round($r->minutes / 60 * (float) $r->rate_per_hour, 2), 2, '.', ''), $r->amount);
        }
    }

    public function test_reseeding_does_not_duplicate_rates_or_reprice_requests(): void
    {
        $this->seed();
        $before = [OvertimeRate::count(), OvertimeRequest::count(), OvertimeRequest::sum('amount')];

        $this->seed();

        $this->assertEquals($before, [OvertimeRate::count(), OvertimeRequest::count(), OvertimeRequest::sum('amount')]);
    }

    private function lastRequest(): OvertimeRequest
    {
        return OvertimeRequest::orderByDesc('id')->firstOrFail();
    }
}
