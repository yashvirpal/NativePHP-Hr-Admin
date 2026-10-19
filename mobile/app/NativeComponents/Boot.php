<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/** Launch screen: goes straight to Home when a token is stored, otherwise to Login. */
class Boot extends Screen
{
    public function mount(): void
    {
        $this->signedIn() ? $this->replace('/home') : $this->replace('/login');
    }

    public function render(): View
    {
        return view('native.boot');
    }
}
