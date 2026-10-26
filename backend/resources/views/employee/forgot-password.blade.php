@extends('layouts.panel')
@section('title', 'Forgot password')
@section('content')
<form method="post" action="{{ route('employee.password.email') }}" class="card" style="width:min(400px,92vw)">
    @csrf
    <div class="logo" style="color:var(--ink);padding:0 0 4px"><i>H</i> My HR</div>
    <h1 style="font-size:20px;margin:6px 0 4px">Forgot your password?</h1>
    <p class="sub" style="margin-bottom:18px">Enter your email and we'll send you a reset link.</p>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
    <button style="width:100%;justify-content:center">Send reset link</button>
    <p class="muted" style="margin:16px 0 0;font-size:13px"><a href="{{ route('employee.login') }}">Back to sign in</a></p>
</form>
@endsection
