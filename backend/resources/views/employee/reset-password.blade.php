@extends('layouts.panel')
@section('title', 'Reset password')
@section('content')
<form method="post" action="{{ route('employee.password.update') }}" class="card" style="width:min(400px,92vw)">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="logo" style="color:var(--ink);padding:0 0 4px"><i>H</i> My HR</div>
    <h1 style="font-size:20px;margin:6px 0 4px">Reset your password</h1>
    <p class="sub" style="margin-bottom:18px">Choose a new password for your account.</p>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autofocus autocomplete="username"></div>
    <div class="field"><label for="password">New password</label>
        <div style="position:relative">
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" style="padding-right:42px;width:100%">
            <button type="button" class="ghost" aria-label="Show password" onclick="togglePasswordVisibility('password', this)" style="position:absolute;right:3px;top:3px;width:32px;height:32px;min-height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
    </div>
    <div class="field"><label for="password_confirmation">Confirm new password</label>
        <div style="position:relative">
            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" style="padding-right:42px;width:100%">
            <button type="button" class="ghost" aria-label="Show password" onclick="togglePasswordVisibility('password_confirmation', this)" style="position:absolute;right:3px;top:3px;width:32px;height:32px;min-height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
    </div>
    <button style="width:100%;justify-content:center">Reset password</button>
</form>
@endsection
