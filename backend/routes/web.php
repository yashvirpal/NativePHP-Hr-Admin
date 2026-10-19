<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Employee;
use Illuminate\Support\Facades\Route;

// Employee-first: the root is the employee portal.
Route::redirect('/', '/employee');

/*
| Employee portal: employees (users table, default "web" guard). Same data as the mobile app,
| served by the same EmployeePortalService.
*/
Route::prefix('employee')->name('employee.')->group(function () {
    Route::get('login', [Employee\AuthController::class, 'showLogin'])->name('login')->middleware('guest:web');
    Route::post('login', [Employee\AuthController::class, 'login'])->middleware(['guest:web', 'throttle:5,1']);
    Route::post('logout', [Employee\AuthController::class, 'logout'])->name('logout')->middleware('auth:web');

    Route::middleware('auth:web')->group(function () {
        Route::get('/', Employee\DashboardController::class)->name('dashboard');
        Route::post('punch-in', [Employee\AttendanceController::class, 'punchIn'])->name('punch-in');
        Route::post('punch-out', [Employee\AttendanceController::class, 'punchOut'])->name('punch-out');
        Route::get('attendance', [Employee\AttendanceController::class, 'index'])->name('attendance.index');

        Route::get('leave', [Employee\LeaveController::class, 'index'])->name('leave.index');
        Route::post('leave', [Employee\LeaveController::class, 'store'])->name('leave.store');
        Route::post('leave/{leaveRequest}/cancel', [Employee\LeaveController::class, 'cancel'])->name('leave.cancel');

        Route::get('overtime', [Employee\OvertimeController::class, 'index'])->name('overtime.index');
        Route::post('overtime', [Employee\OvertimeController::class, 'store'])->name('overtime.store');
        Route::put('overtime/rate', [Employee\OvertimeController::class, 'updateRate'])->name('overtime.rate');

        Route::get('holidays', [Employee\HolidayController::class, 'index'])->name('holidays.index');

        Route::resource('companies', Employee\CompanyController::class)->except('show');
    });
});

/*
| Admin panel: back-office staff (admins table, "admin" guard): super_admin and manager.
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [Admin\AuthController::class, 'showLogin'])->name('login')->middleware('guest:admin');
    Route::post('login', [Admin\AuthController::class, 'login'])->middleware(['guest:admin', 'throttle:5,1']);
    Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout')->middleware('auth:admin');

    Route::middleware('auth:admin')->group(function () {
        // Every signed-in admin gets the dashboard; everything else is gated by a permission from their role.
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::middleware('can:employees.view')->get('employees', [Admin\EmployeeController::class, 'index'])->name('employees.index');
        Route::middleware('can:employees.manage')->group(function () {
            Route::resource('employees', Admin\EmployeeController::class)->only(['create', 'store', 'edit', 'update']);
        });

        Route::middleware('can:departments.manage')->group(function () {
            Route::resource('departments', Admin\DepartmentController::class)->only(['index', 'store']);
        });
        Route::middleware('can:holidays.manage')->group(function () {
            Route::resource('holidays', Admin\HolidayController::class)->only(['index', 'store', 'destroy']);
        });

        Route::middleware('can:attendance.view')->get('attendance', [Admin\AttendanceController::class, 'index'])->name('attendance.index');

        Route::middleware('can:leave.manage')->group(function () {
            Route::get('leave-requests', [Admin\LeaveRequestController::class, 'index'])->name('leave-requests.index');
            Route::post('leave-requests/{leaveRequest}/{decision}', [Admin\LeaveRequestController::class, 'decide'])
                ->whereIn('decision', ['approve', 'reject'])->name('leave-requests.decide');
        });

        Route::middleware('can:overtime.manage')->group(function () {
            Route::get('overtime-requests', [Admin\OvertimeRequestController::class, 'index'])->name('overtime-requests.index');
            Route::post('overtime-requests/{overtimeRequest}/{decision}', [Admin\OvertimeRequestController::class, 'decide'])
                ->whereIn('decision', ['approve', 'reject'])->name('overtime-requests.decide');
        });

        // Admin accounts, and (super admin only) the roles that define what they can do.
        Route::middleware('can:admins.manage')->group(function () {
            Route::resource('admins', Admin\AdminController::class)->except(['show', 'destroy']);
        });
        Route::middleware('can:roles.manage')->group(function () {
            Route::resource('roles', Admin\RoleController::class)->except('show');
        });
    });
});