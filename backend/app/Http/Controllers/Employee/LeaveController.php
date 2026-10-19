<?php

namespace App\Http\Controllers\Employee;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveController extends PortalController
{
    public function index(Request $request): View
    {
        $employee = $this->employee($request);

        return view('employee.leave.index', [
            'types' => $this->portal->leaveTypes(),
            'balances' => $this->portal->leaveBalances($employee),
            'requests' => $this->portal->leaveRequests($employee),
        ]);
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->portal->requestLeave(
            $this->employee($request),
            (int) $data['leave_type_id'],
            $data['start_date'],
            $data['end_date'],
            $data['reason'] ?? null,
        );

        return redirect()->route('employee.leave.index')->with('status', 'Leave request submitted.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $this->portal->cancelLeave($this->employee($request), $leaveRequest);

        return back()->with('status', 'Leave request cancelled.');
    }
}
