@extends('admin.layout')
@section('title', $role->exists ? 'Edit role' : 'Add role')
@section('content')
@php $selected = (array) old('permissions', $role->permissions ?? []); @endphp
<div class="page-head"><div><h1>{{ $role->exists ? 'Edit role' : 'Add role' }}</h1></div></div>
<form method="post" class="card"
      action="{{ $role->exists ? route('admin.roles.update', $role) : route('admin.roles.store') }}">
    @csrf
    @if ($role->exists) @method('PUT') @endif

    <div class="form-grid">
        <div class="field"><label for="name">Role name</label><input id="name" name="name" value="{{ old('name', $role->name) }}" maxlength="100" required></div>
        <div class="field"><label for="description">Description</label><input id="description" name="description" value="{{ old('description', $role->description) }}" maxlength="255"></div>
    </div>

    <h2 style="padding:8px 0 12px;margin:8px 0 12px">Permissions</h2>
    <p class="muted" style="margin-top:0">Every admin can open the dashboard. Everything else needs the permission ticked here.</p>
    @foreach ($groups as $group => $permissions)
        <div style="margin-bottom:16px">
            <div class="nav-label" style="color:var(--muted);padding:0 0 8px">{{ $group }}</div>
            <div class="form-grid" style="gap:10px 18px">
                @foreach ($permissions as $permission)
                    <label class="check">
                        <input type="checkbox" name="permissions[]" value="{{ $permission->value }}" @checked(in_array($permission->value, $selected, true))>
                        <span>{{ $permission->label() }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach
    <p class="muted">Tip: "Create and edit employees" lets someone change employee logins, so give it deliberately.</p>

    <div class="row"><button>Save role</button><a class="btn ghost" href="{{ route('admin.roles.index') }}">Cancel</a></div>
</form>
@endsection
