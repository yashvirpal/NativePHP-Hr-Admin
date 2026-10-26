<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\LeaveController;
use App\Http\Controllers\Api\V1\OvertimeController;
use Illuminate\Support\Facades\Route;

/*
| Employee API (mobile app). Authenticated users are always employees (App\Models\User);
| back-office staff use the web admin panel with the separate "admin" guard.
*/
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('dashboard', DashboardController::class);

        Route::get('attendance/today', [AttendanceController::class, 'today']);
        Route::post('attendance/punch-in', [AttendanceController::class, 'punchIn']);
        Route::post('attendance/punch-out', [AttendanceController::class, 'punchOut']);
        Route::get('attendance', [AttendanceController::class, 'index']);

        Route::get('leave-types', [LeaveController::class, 'types']);
        Route::get('leave-balances', [LeaveController::class, 'balances']);
        Route::get('leave-requests', [LeaveController::class, 'index']);
        Route::post('leave-requests', [LeaveController::class, 'store']);
        Route::post('leave-requests/{leaveRequest}/cancel', [LeaveController::class, 'cancel']);

        Route::get('holidays', [HolidayController::class, 'index']);

        Route::get('overtime-requests', [OvertimeController::class, 'index']);
        Route::post('overtime-requests', [OvertimeController::class, 'store']);
        Route::get('overtime-rate', [OvertimeController::class, 'rate']);
        Route::put('overtime-rate', [OvertimeController::class, 'updateRate']);

        Route::apiResource('companies', CompanyController::class)->except('show');
    });
});
