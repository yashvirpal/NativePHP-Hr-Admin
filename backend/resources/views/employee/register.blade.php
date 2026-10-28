@extends('layouts.panel')
@section('title', 'Create account')
@section('content')
<form method="post" action="{{ route('employee.register') }}" class="card" style="width:min(400px,92vw)">
    @csrf
    <div class="logo" style="color:var(--ink);padding:0 0 4px"><i>H</i> My HR</div>
    <h1 style="font-size:20px;margin:6px 0 4px">Create your account</h1>
    <p class="sub" style="margin-bottom:18px">Track your own hours, leave, and overtime — no employer sign-up needed.</p>
    <div class="field"><label for="name">Name</label><input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"></div>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username"></div>
    <div class="field"><label for="password">Password</label>
        <div style="position:relative">
            <input id="password" type="password" name="password" required autocomplete="new-password" style="padding-right:42px;width:100%">
            <button type="button" class="ghost" aria-label="Show password" onclick="togglePasswordVisibility('password', this)" style="position:absolute;right:3px;top:3px;width:32px;height:32px;min-height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
    </div>
    <div class="field"><label for="password_confirmation">Confirm password</label>
        <div style="position:relative">
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" style="padding-right:42px;width:100%">
            <button type="button" class="ghost" aria-label="Show password" onclick="togglePasswordVisibility('password_confirmation', this)" style="position:absolute;right:3px;top:3px;width:32px;height:32px;min-height:32px;padding:0;display:inline-flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>
        </div>
    </div>
    <button style="width:100%;justify-content:center">Create account</button>
    <p class="muted" style="margin:16px 0 0;font-size:13px">Already have an account? <a href="{{ route('employee.login') }}">Sign in</a></p>
</form>
@endsection
