<?php

namespace App\Http\Controllers\Employee;

use App\Http\Requests\PunchRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends PortalController
{
    public function index(Request $request): View
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $data['month'] ?? now()->format('Y-m');

        return view('employee.attendance.index', [
            'month' => $month,
            'records' => $this->portal->attendanceHistory($this->employee($request), $month)->withQueryString(),
        ]);
    }

    public function punchIn(PunchRequest $request): RedirectResponse
    {
        $this->portal->punchIn($this->employee($request), $request->meta('web'));

        return back()->with('status', 'Punched in at '.now()->format('H:i').'.');
    }

    public function punchOut(PunchRequest $request): RedirectResponse
    {
        $this->portal->punchOut($this->employee($request), $request->meta('web'));

        return back()->with('status', 'Punched out at '.now()->format('H:i').'.');
    }
}
