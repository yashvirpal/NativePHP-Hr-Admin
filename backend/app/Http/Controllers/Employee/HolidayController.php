<?php

namespace App\Http\Controllers\Employee;

use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidayController extends PortalController
{
    public function index(Request $request): View
    {
        $year = (int) $request->query('year', now()->year);

        return view('employee.holidays.index', [
            'year' => $year,
            'holidays' => $this->portal->holidays($year),
        ]);
    }
}
