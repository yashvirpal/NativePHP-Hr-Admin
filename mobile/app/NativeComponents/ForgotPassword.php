<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Facades\Dialog;

class ForgotPassword extends Screen
{
    public string $email = '';

    public function navTitle(): string
    {
        return __('Forgot password');
    }

    public function send(): void
    {
        $email = trim($this->email);

        $this->fieldErrors = [];
        if ($email === '' || ! str_contains($email, '@')) {
            $this->error = '';
            $this->fieldErrors = ['email' => __('Enter a valid email.')];

            return;
        }

        $this->busy = true;
        $sent = $this->call(fn ($api) => $api->forgotPassword($email));
        $this->busy = false;

        if ($sent !== null) {
            Dialog::toast(__('Reset link sent. Check your email.'), 'long');
            $this->back();
        }
    }

    public function render(): View
    {
        return view('native.forgot-password');
    }
}
