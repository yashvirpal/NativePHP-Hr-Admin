<?php

namespace App\NativeComponents;

use Illuminate\View\View;

class Register extends Screen
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public function navTitle(): string
    {
        return __('Create account');
    }

    public function signUp(): void
    {
        $name = trim($this->name);
        $email = trim($this->email);

        $this->error = '';
        $this->fieldErrors = array_filter([
            'name' => $name === '' ? __('Enter your name.') : '',
            'email' => $email === '' || ! str_contains($email, '@') ? __('Enter a valid email.') : '',
            'password' => $this->password === '' ? __('Enter a password.') : '',
            'passwordConfirmation' => $this->passwordConfirmation !== $this->password ? __('Passwords do not match.') : '',
        ]);

        if ($this->fieldErrors !== []) {
            return;
        }

        $this->busy = true;
        $user = $this->call(fn ($api) => $api->register($name, $email, $this->password, $this->passwordConfirmation));
        $this->busy = false;

        if ($user !== null) {
            $this->password = '';
            $this->passwordConfirmation = '';
            $this->replace('/home');
        }
    }

    public function render(): View
    {
        return view('native.register');
    }
}
