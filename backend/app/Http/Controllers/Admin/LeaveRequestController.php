<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'all');

        return view('admin.leave.index', [
            'status' => $status,
            'requests' => LeaveRequest::with('employee.user', 'leaveType')
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->orderByDesc('start_date')
                ->paginate(25)
                ->withQueryString(),
        ]);
    }
}
