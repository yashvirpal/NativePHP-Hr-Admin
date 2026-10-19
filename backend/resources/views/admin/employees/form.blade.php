@extends('admin.layout')
@section('title', $employee->exists ? 'Edit employee' : 'Add employee')
@section('content')
<div class="page-head">
    <div><h1>{{ $employee->exists ? 'Edit employee' : 'Add employee' }}</h1>
        <p class="sub">{{ $employee->exists ? $employee->user->name.' · '.$employee->employee_code : 'Creates a login and an employee profile.' }}</p></div>
</div>
<form method="post" class="card"
      action="{{ $employee->exists ? route('admin.employees.update', $employee) : route('admin.employees.store') }}">
    @csrf
    @if ($employee->exists) @method('PUT') @endif

    <h2 style="padding:0 0 12px;margin-bottom:16px">Employee login</h2>
    <div class="form-grid">
        <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $employee->user?->name) }}" required></div>
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email', $employee->user?->email) }}" required></div>
        <div class="field"><label for="password">Password{{ $employee->exists ? ' (leave blank to keep)' : '' }}</label>
            <input id="password" type="password" name="password" minlength="8" autocomplete="new-password" {{ $employee->exists ? '' : 'required' }}></div>
    </div>

    <h2 style="padding:12px 0;margin:8px 0 16px">Employment</h2>
    <div class="form-grid">
        <div class="field"><label for="employee_code">Employee code</label><input id="employee_code" name="employee_code" value="{{ old('employee_code', $employee->employee_code) }}" required></div>
        <div class="field"><label for="designation">Designation</label><input id="designation" name="designation" value="{{ old('designation', $employee->designation) }}"></div>
        <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $employee->phone) }}"></div>
        <div class="field"><label for="joined_on">Joined on</label><input id="joined_on" type="date" name="joined_on" value="{{ old('joined_on', $employee->joined_on?->toDateString()) }}"></div>
        <div class="field"><label for="department_id">Department</label>
            <select id="department_id" name="department_id"><option value="">—</option>@foreach ($departments as $d)
                <option value="{{ $d->id }}" @selected((int) old('department_id', $employee->department_id) === $d->id)>{{ $d->name }}</option>
            @endforeach</select></div>
        <div class="field"><label for="shift_id">Shift</label>
            <select id="shift_id" name="shift_id"><option value="">—</option>@foreach ($shifts as $s)
                <option value="{{ $s->id }}" @selected((int) old('shift_id', $employee->shift_id) === $s->id)>{{ $s->name }} ({{ substr($s->start_time, 0, 5) }}–{{ substr($s->end_time, 0, 5) }})</option>
            @endforeach</select></div>
        <div class="field"><label for="manager_id">Manager</label>
            <select id="manager_id" name="manager_id"><option value="">—</option>@foreach ($managers as $m)
                @continue($m->id === $employee->id)
                <option value="{{ $m->id }}" @selected((int) old('manager_id', $employee->manager_id) === $m->id)>{{ $m->user->name }}</option>
            @endforeach</select></div>
        <div class="field"><label for="status">Status</label>
            <select id="status" name="status">@foreach (['active', 'inactive'] as $st)
                <option value="{{ $st }}" @selected(old('status', $employee->status) === $st)>{{ ucfirst($st) }}</option>
            @endforeach</select></div>
    </div>

    <div class="row" style="margin-top:8px"><button>Save</button><a class="btn ghost" href="{{ route('admin.employees.index') }}">Cancel</a></div>
</form>
@endsection
