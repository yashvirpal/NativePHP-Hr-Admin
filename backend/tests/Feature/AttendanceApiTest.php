<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

class AttendanceApiTest extends ApiTestCase
{
    public function test_punch_in_and_out_computes_worked_minutes(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/punch-in')->assertCreated()->assertJsonPath('record.status', 'present');

        Carbon::setTestNow('2026-11-02 12:55:00');
        $this->postJson('/api/v1/attendance/punch-out')->assertOk()->assertJsonPath('record.worked_minutes', 240);

        $this->getJson('/api/v1/attendance/today')->assertOk()->assertJsonPath('is_punched_in', false);
    }

    public function test_second_punch_in_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/punch-in')->assertCreated();
        $this->postJson('/api/v1/attendance/punch-in')->assertUnprocessable()->assertJsonValidationErrors('punch');
    }

    public function test_punch_out_without_punch_in_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/punch-out')->assertUnprocessable();
    }

    public function test_late_arrival_is_flagged_after_grace(): void
    {
        Sanctum::actingAs($this->makeUser());

        Carbon::setTestNow('2026-11-02 09:20:00'); // grace is 15 min
        $this->postJson('/api/v1/attendance/punch-in')->assertCreated()->assertJsonPath('record.status', 'late');
    }

    public function test_within_grace_is_not_late(): void
    {
        Sanctum::actingAs($this->makeUser());

        Carbon::setTestNow('2026-11-02 09:10:00');
        $this->postJson('/api/v1/attendance/punch-in')->assertCreated()->assertJsonPath('record.status', 'present');
    }

    public function test_client_cannot_spoof_source_or_time(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/v1/attendance/punch-in', ['source' => 'manual', 'punched_at' => '2020-01-01 00:00:00'])
            ->assertCreated()
            ->assertJsonPath('record.punches.0.source', 'mobile');

        $this->assertDatabaseHas('attendance_punches', ['punched_at' => '2026-11-02 08:55:00']);
    }

    public function test_user_without_employee_profile_is_forbidden(): void
    {
        Sanctum::actingAs(\App\Models\User::factory()->create());

        $this->postJson('/api/v1/attendance/punch-in')->assertForbidden();
    }
}
