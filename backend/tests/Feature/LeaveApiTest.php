<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

class LeaveApiTest extends ApiTestCase
{
    public function test_leave_request_counts_only_working_days(): void
    {
        Sanctum::actingAs($this->makeUser());
        $type = $this->leaveType(10);
        Holiday::create(['name' => 'Test', 'date' => '2026-11-04']);

        // Mon 2nd - Sun 8th with a Wednesday holiday => 4 working days
        $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-08',
        ])->assertCreated()->assertJsonPath('days', '4.0')->assertJsonPath('status', 'pending');
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

    public function test_admin_approval_deducts_balance_and_records_the_approver(): void
    {
        $employee = $this->makeUser();
        $admin = $this->makeAdmin();
        $type = $this->leaveType(10);

        Sanctum::actingAs($employee);
        $id = $this->postJson('/api/v1/leave-requests', [
            'leave_type_id' => $type->id, 'start_date' => '2026-11-02', 'end_date' => '2026-11-03',
        ])->json('id');

        app(LeaveService::class)->decide(LeaveRequest::findOrFail($id), $admin, 'approved', 'ok');

        $balance = LeaveBalance::where('employee_id', $employee->employee->id)->first();
        $this->assertSame('2.0', $balance->used);
        $this->assertDatabaseHas('approvals', ['approvable_id' => $id, 'decision' => 'approved', 'approver_id' => $admin->id]);
        $this->assertSame('approved', LeaveRequest::find($id)->status);
    }

    public function test_decision_cannot_be_applied_twice(): void
    {
        $employee = $this->makeUser();
        $admin = $this->makeAdmin();
        $leave = LeaveRequest::create([
            'employee_id' => $employee->employee->id, 'leave_type_id' => $this->leaveType()->id,
            'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'days' => 1, 'status' => 'pending',
        ]);

        $service = app(LeaveService::class);
        $service->decide($leave, $admin, 'rejected');

        $this->expectException(ValidationException::class);
        $service->decide($leave, $admin, 'approved');
    }

    public function test_employee_cannot_cancel_someone_elses_request(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $leave = LeaveRequest::create([
            'employee_id' => $owner->employee->id, 'leave_type_id' => $this->leaveType()->id,
            'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'days' => 1, 'status' => 'pending',
        ]);

        Sanctum::actingAs($other);
        $this->postJson("/api/v1/leave-requests/{$leave->id}/cancel")->assertForbidden();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/leave-requests/{$leave->id}/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
    }
}
