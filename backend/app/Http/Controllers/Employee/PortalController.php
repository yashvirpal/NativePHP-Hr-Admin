<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\EmployeePortalService;
use Illuminate\Http\Request;

/** Base for employee web pages: resolves the signed-in employee and exposes the common service. */
abstract class PortalController extends Controller
{
    public function __construct(protected EmployeePortalService $portal) {}

    protected function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;

        abort_if(! $employee || $employee->status !== 'active', 403, 'No active employee profile for this account.');

        return $employee;
    }
}
