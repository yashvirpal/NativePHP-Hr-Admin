<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Services\LeaveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function __construct(private LeaveService $leave) {}

    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');

        return view('admin.leave.index', [
            'status' => $status,
            'requests' => LeaveRequest::with('employee.user', 'leaveType')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByDesc('start_date')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }

    public function decide(Request $request, LeaveRequest $leaveRequest, string $decision): RedirectResponse
    {
        $remarks = $request->validate(['remarks' => ['nullable', 'string', 'max:500']])['remarks'] ?? null;

        $this->leave->decide($leaveRequest, $request->user(), $decision === 'approve' ? 'approved' : 'rejected', $remarks);

        return back()->with('status', 'Leave request '.($decision === 'approve' ? 'approved' : 'rejected').'.');
    }
}
