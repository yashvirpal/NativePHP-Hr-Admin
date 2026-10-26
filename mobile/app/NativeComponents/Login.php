<?php

namespace App\NativeComponents;

use Illuminate\View\View;

class Login extends Screen
{
    public string $email = '';

    public string $password = '';

    public function forgotPassword(): void
    {
        $this->navigate('/forgot-password');
    }

    public function signIn(): void
    {
        $email = trim($this->email);

        $this->fieldErrors = [];
        if ($email === '' || $this->password === '') {
            $this->error = '';
            $this->fieldErrors = array_filter([
                'email' => $email === '' ? 'Enter your email.' : '',
                'password' => $this->password === '' ? 'Enter your password.' : '',
            ]);

            return;
        }

        $this->busy = true;
        $user = $this->call(fn ($api) => $api->login($email, $this->password));
        $this->busy = false;

        if ($user !== null) {
            $this->password = '';
            $this->replace('/home');
        }
    }

    public function render(): View
    {
        return view('native.login');
    }
}
