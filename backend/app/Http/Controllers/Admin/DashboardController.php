<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $today = Carbon::today()->toDateString();
        $todayRecords = AttendanceRecord::whereDate('work_date', $today);

        return view('admin.dashboard', [
            'activeEmployees' => Employee::where('status', 'active')->count(),
            'presentToday' => (clone $todayRecords)->whereIn('status', ['present', 'late'])->count(),
            'lateToday' => (clone $todayRecords)->where('status', 'late')->count(),
            'onLeaveToday' => LeaveRequest::where('status', 'approved')
                ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)->count(),
            'pendingLeave' => LeaveRequest::where('status', 'pending')->count(),
            'pendingOvertime' => OvertimeRequest::where('status', 'pending')->count(),
            'pendingLeaveList' => LeaveRequest::with('employee.user', 'leaveType')
                ->where('status', 'pending')->orderBy('start_date')->limit(6)->get(),
            'recentAttendance' => AttendanceRecord::with('employee.user')
                ->whereNotNull('first_in_at')->orderByDesc('work_date')->orderByDesc('first_in_at')->limit(8)->get(),
        ]);
    }
}
