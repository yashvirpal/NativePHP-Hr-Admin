@extends('admin.layout')
@section('title', 'Sign in')
@section('content')
<form method="post" action="{{ route('admin.login') }}" class="card" style="width:min(400px,92vw)">
    @csrf
    <div class="logo" style="color:var(--ink);padding:0 0 4px"><i>H</i> HR Admin</div>
    <h1 style="font-size:20px;margin:6px 0 4px">Sign in</h1>
    <p class="sub" style="margin-bottom:18px">HR and admin staff only.</p>
    <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
    <div class="field"><label for="password">Password</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
    <div class="field"><label class="check" style="font-weight:400"><input type="checkbox" name="remember" value="1"> Remember me</label></div>
    <button style="width:100%;justify-content:center">Sign in</button>
</form>
@endsection
