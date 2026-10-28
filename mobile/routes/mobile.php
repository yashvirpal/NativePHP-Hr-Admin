<?php

use App\NativeComponents\Attendance;
use App\NativeComponents\Boot;
use App\NativeComponents\Companies;
use App\NativeComponents\CompanyForm;
use App\NativeComponents\Earning;
use App\NativeComponents\ForgotPassword;
use App\NativeComponents\Holidays;
use App\NativeComponents\Home;
use App\NativeComponents\Layouts\MainTabsLayout;
use App\NativeComponents\Layouts\StackLayout;
use App\NativeComponents\Leave;
use App\NativeComponents\Login;
use App\NativeComponents\ManualHoursCreate;
use App\NativeComponents\More;
use App\NativeComponents\Overtime;
use App\NativeComponents\OvertimeRate;
use App\NativeComponents\Register;
use Illuminate\Support\Facades\Route;

/*
| Native screens. Loaded only in native contexts (device, Jump, native:* commands, tests).
| The app talks to the HR backend through App\Services\Api\HrApi; see config/hr.php.
*/

// Launch: routes to /home or /login depending on a stored token.
Route::native('/', Boot::class);
Route::native('/login', Login::class);

// The five bottom tabs.
Route::nativeGroup(MainTabsLayout::class, function () {
    Route::native('/home', Home::class);
    Route::native('/attendance', Attendance::class);
    Route::native('/leave', Leave::class);
    Route::native('/overtime', Overtime::class);
    Route::native('/more', More::class);
});

// Screens pushed on top of a tab (with a back button, no tab bar).
Route::nativeGroup(StackLayout::class, function () {
    Route::native('/forgot-password', ForgotPassword::class);
    Route::native('/register', Register::class);
    Route::native('/attendance/manual', ManualHoursCreate::class);
    Route::native('/overtime/rate', OvertimeRate::class);
    Route::native('/earning', Earning::class);
    Route::native('/holidays', Holidays::class);
    Route::native('/companies', Companies::class);
    Route::native('/companies/form', CompanyForm::class);
});
