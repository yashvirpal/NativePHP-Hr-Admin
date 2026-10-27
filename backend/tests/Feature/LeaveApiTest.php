<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Laravel\Sanctum\Sanctum;

class LeaveApiTest extends ApiTestCase
{
    public function test_omitting_leave_type_id_defaults_to_annual_leave(): void
    {
        Sanctum::actingAs($this->makeUser());
        LeaveType::create(['name' => 'Annual Leave', 'code' => 'AL', 'days_per_year' => 18]);

        $this->postJson('/api/v1/leave-requests', [
            'start_date' => '2026-11-02', 'end_date' => '2026-11-02',
        ])->assertCreated()->assertJsonPath('leave_type.code', 'AL');
    }

    public function test_leave_request_counts_only_working_days(): void
    {
        Sanctum::actingAs($this->makeUser());
        $type = $this->leaveType(10);
        Holiday::create(['name' => 'Test', 'date' => '2026-11-04']);

        // Mon 2nd - Sun 8th with a Wednesday holiday => 4 working days
        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-08',
        ])->assertCreated()->assertJsonPath('days', '4.0')->assertJsonPath('status', 'approved');
    }

    public function test_weekend_only_range_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser());
        $type = $this->leaveType();

        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-07', 'end_date' => '2026-11-08',
        ])->assertUnprocessable();
    }

    public function test_insufficient_balance_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser());
        $type = $this->leaveType(2);

        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-06',
        ])->assertUnprocessable()->assertJsonValidationErrors('leave_type_id');
    }

    public function test_overlapping_request_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser());
        $type = $this->leaveType(10);
        $payload = ['leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-03'];

        $this->postJson('/api/v1/leave-requests', $payload)->assertCreated();
        $this->postJson('/api/v1/leave-requests', $payload)->assertUnprocessable();
    }

    public function test_submitting_leave_deducts_balance_immediately(): void
    {
        $employee = $this->makeUser();
        $type = $this->leaveType(10);

        Sanctum::actingAs($employee);
        $id = $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-03',
        ])->assertJsonPath('status', 'approved')->json('id');

        $balance = LeaveBalance::where('employee_id', $employee->employee->id)->first();
        $this->assertSame('2.0', $balance->used);
        $this->assertDatabaseMissing('approvals', ['approvable_id' => $id]);
        $this->assertSame('approved', LeaveRequest::find($id)->status);
    }

    public function test_employee_cannot_cancel_someone_elses_request(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $leave = LeaveRequest::create([
            'employee_id' => $owner->employee->id, 'leave_type_id' => $this->leaveType()->id,
            'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'days' => 1, 'status' => 'approved',
        ]);

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/leave-requests/{$leave->id}/cancel")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/leave-requests/{$leave->id}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
    }
}
