@extends('layouts.panel')
@section('title', 'Sign in')
@section('content')
<form method="post" action="{{ route('employee.login') }}" class="card" style="width:min(400px,92vw)">
    @csrf
    <div class="logo" style="color:var(--ink);padding:0 0 4px"><i>H</i> My HR</div>
    <h1 style="font-size:20px;margin:6px 0 4px">Employee sign in</h1>
    <p class="sub" style="margin-bottom:18px">Use the same account as the mobile app.</p>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
    <div class="field"><label class="check" style="font-weight:400"><input type="checkbox" name="remember" value="1"> Remember me</label></div>
    <button style="width:100%;justify-content:center">Sign in</button>
    <p class="muted" style="margin:16px 0 0;font-size:13px">HR staff? <a href="{{ route('admin.login') }}">Admin sign in</a></p>
</form>
@endsection
