@extends('admin.layout')
@section('title', $admin->exists ? 'Edit admin' : 'Add admin')
@section('content')
<div class="page-head"><div><h1>{{ $admin->exists ? 'Edit admin' : 'Add admin' }}</h1></div></div>
<form method="post" class="card"
      action="{{ $admin->exists ? route('admin.admins.update', $admin) : route('admin.admins.store') }}">
    @csrf
    @if ($admin->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $admin->name) }}" required></div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $admin->email) }}" required></div>
        <div class="field"><label for="password">Password{{ $admin->exists ? ' (leave blank to keep)' : '' }}</label>
            <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" {{ $admin->exists ? '' : 'required' }}></div>
        <div class="field"><label for="role_id">Role</label>
            <select id="role_id" name="role_id" required>
                <option value="" disabled @selected(! old('role_id', $admin->role_id))>Choose a role</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->id }}" @selected((int) old('role_id', $admin->role_id) === $r->id)>{{ $r->name }}</option>
                @endforeach
            </select></div>
    </div>
    <p class="muted">The role decides which pages this admin can open and what they can change.
        You can only assign roles that give no more access than your own.</p>
    <div class="row"><button>Save</button><a class="btn ghost" href="{{ route('admin.admins.index') }}">Cancel</a></div>
</form>
@endsection
