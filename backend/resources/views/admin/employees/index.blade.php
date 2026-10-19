@extends('admin.layout')
@section('title', 'Employees')
@section('content')
<div class="page-head">
    <div><h1>Employees</h1><p class="sub">{{ $employees->total() }} people</p></div>
    @can('employees.manage')
    <a class="btn" href="{{ route('admin.employees.create') }}">+ Add employee</a>
    @endcan
</div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Employee</th><th>Code</th><th>Department</th><th>Designation</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($employees as $e)
            <tr>
                <td><div class="person"><div class="avatar">{{ strtoupper(substr($e->user->name, 0, 1)) }}</div>
                    <div>{{ $e->user->name }}<div class="muted" style="font-size:12px">{{ $e->user->email }}</div></div></div></td>
                <td>{{ $e->employee_code }}</td>
                <td>{{ $e->department?->name ?? '—' }}</td>
                <td>{{ $e->designation ?? '—' }}</td>
                <td><span class="badge {{ $e->status }}">{{ $e->status }}</span></td>
                <td class="actions">@can('employees.manage')<a class="btn ghost sm" href="{{ route('admin.employees.edit', $e) }}">Edit</a>@endcan</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No employees yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $employees->links() }}
@endsection
