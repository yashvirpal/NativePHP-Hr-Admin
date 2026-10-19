<?php

namespace Database\Seeders;

use App\Enums\Permission;
use App\Models\Admin;
use App\Models\Approval;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Illuminate\Database\Seeder;

/**
 * One approval row for every approved/rejected leave and overtime request,
 * decided by one of the seeded managers (rotating).
 */
class ApprovalSeeder extends Seeder
{
    public function run(): void
    {
        // Only admins whose role lets them decide both leave and overtime requests.
        $approvers = Admin::with('role')->orderBy('id')->get()
            ->filter(fn (Admin $a) => $a->hasPermission(Permission::LeaveManage) && $a->hasPermission(Permission::OvertimeManage))
            ->values();

        $requests = LeaveRequest::whereIn('status', ['approved', 'rejected'])->orderBy('id')->get()
            ->concat(OvertimeRequest::whereIn('status', ['approved', 'rejected'])->orderBy('id')->get());

        foreach ($requests->values() as $i => $request) {
            Approval::firstOrCreate(
                ['approvable_type' => $request->getMorphClass(), 'approvable_id' => $request->id],
                [
                    'approver_id' => $approvers[$i % $approvers->count()]->id,
                    'decision' => $request->status,
                    'remarks' => $request->status === 'approved' ? 'Approved.' : 'Rejected due to workload.',
                    'decided_at' => $request->updated_at,
                ],
            );
        }
    }
}
