<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegister(): View
    {
        return view('employee.register');
    }

    public function register(Request $request, RegistrationService $registration): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $employee = $registration->register($data['name'], $data['email'], $data['password']);

        Auth::guard('web')->login($employee->user);
        $request->session()->regenerate();

        return redirect()->intended(route('employee.dashboard'));
    }
}
