<?php

namespace App\Http\Controllers\Employee;

use App\Http\Requests\OvertimeRateRequest;
use App\Http\Requests\StoreOvertimeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OvertimeController extends PortalController
{
    public function index(Request $request): View
    {
        $employee = $this->employee($request);

        return view('employee.overtime.index', [
            'requests' => $this->portal->overtimeRequests($employee),
            'rate' => $this->portal->overtimeRate($employee),
        ]);
    }

    public function store(StoreOvertimeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->portal->requestOvertime(
            $this->employee($request),
            $data['work_date'],
            (int) $data['minutes'],
            $data['reason'] ?? null,
        );

        return redirect()->route('employee.overtime.index')->with('status', 'Overtime request submitted.');
    }

    public function updateRate(OvertimeRateRequest $request): RedirectResponse
    {
        $this->portal->setOvertimeRate($this->employee($request), $request->validated('hourly_rate'));

        return redirect()->route('employee.overtime.index')
            ->with('status', 'Hourly rate updated. It applies to overtime you submit from now on; earlier requests keep their rate.');
    }
}
