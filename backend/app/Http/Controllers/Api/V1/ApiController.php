<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /** The authenticated user's employee profile; 403 if the account has none or is inactive. */
    protected function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;

        abort_if(! $employee || $employee->status !== 'active', 403, 'No active employee profile for this account.');

        return $employee;
    }
}
