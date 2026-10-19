@extends('admin.layout')
@section('title', 'Departments')
@section('content')
<div class="page-head"><div><h1>Departments</h1><p class="sub">{{ $departments->count() }} departments</p></div></div>
<form method="post" action="{{ route('admin.departments.store') }}" class="card row">
    @csrf
    <input name="name" placeholder="Department name" value="{{ old('name') }}" required>
    <input name="code" placeholder="Code (optional)" value="{{ old('code') }}" style="max-width:180px">
    <button>Add department</button>
</form>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Code</th><th>Employees</th></tr></thead>
        <tbody>
        @forelse ($departments as $d)
            <tr><td>{{ $d->name }}</td><td>{{ $d->code ?? '—' }}</td><td>{{ $d->employees_count }}</td></tr>
        @empty
            <tr><td colspan="3" class="empty">No departments yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
