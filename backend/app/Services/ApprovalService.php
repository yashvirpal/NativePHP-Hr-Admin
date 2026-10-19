<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Approval;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    /**
     * Record an admin's decision on a pending request. Caller must run this inside a transaction.
     */
    public function decide(LeaveRequest|OvertimeRequest $request, Admin $approver, string $decision, ?string $remarks = null): void
    {
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'Invalid decision.']);
        }

        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This request has already been decided.']);
        }

        Approval::create([
            'approvable_type' => $request->getMorphClass(),
            'approvable_id' => $request->id,
            'approver_id' => $approver->id,
            'decision' => $decision,
            'remarks' => $remarks,
            'decided_at' => Carbon::now(),
        ]);

        $request->update(['status' => $decision]);
    }
}
