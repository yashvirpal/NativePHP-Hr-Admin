<?php

namespace App\Http\Controllers\Employee;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends PortalController
{
    public function __invoke(Request $request): View
    {
        return view('employee.dashboard', $this->portal->dashboard($this->employee($request)));
    }
}
