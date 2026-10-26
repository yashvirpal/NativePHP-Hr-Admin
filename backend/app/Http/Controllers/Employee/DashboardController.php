<?php

namespace App\Http\Controllers\Employee;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends PortalController
{
    public function __invoke(Request $request): View
    {
        $employee = $this->employee($request);
        $data = $this->portal->dashboard($employee);
        $totals = $this->portal->attendanceTotals($employee);
        $data['totals'] = $totals['month'];
        $data['yearTotals'] = $totals['year'];
        $data['lifetimeOvertime'] = $totals['lifetime'];

        return view('employee.dashboard', $data);
    }
}
