<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['date' => ['nullable', 'date']]);
        $date = Carbon::parse($data['date'] ?? 'today')->toDateString();

        return view('admin.attendance.index', [
            'date' => $date,
            'records' => AttendanceRecord::with('employee.user')
                ->whereDate('work_date', $date)
                ->orderBy('first_in_at')
                ->paginate(50)
                ->withQueryString(),
        ]);
    }
}
