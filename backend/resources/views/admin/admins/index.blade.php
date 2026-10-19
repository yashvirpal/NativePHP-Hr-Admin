@extends('admin.layout')
@section('title', 'Admins')
@section('content')
<div class="page-head">
    <div><h1>Admins</h1><p class="sub">Back-office accounts. Employees sign in at the employee portal instead.</p></div>
    <div class="row">
        @can('roles.manage') <a class="btn ghost" href="{{ route('admin.roles.index') }}">Manage roles</a> @endcan
        <a class="btn" href="{{ route('admin.admins.create') }}">+ Add admin</a>
    </div>
</div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead>
        <tbody>
        @forelse ($admins as $a)
            <tr>
                <td><div class="person"><div class="avatar">{{ strtoupper(substr($a->name, 0, 1)) }}</div>{{ $a->name }}</div></td>
                <td>{{ $a->email }}</td>
                <td>
                    @if ($a->role) <span class="badge {{ $a->isSuperAdmin() ? 'admin' : 'hr' }}">{{ $a->role->name }}</span>
                    @else <span class="badge rejected">No role</span> @endif
                </td>
                <td class="actions">
                    @if (! $a->isSuperAdmin() || auth('admin')->user()->isSuperAdmin())
                        <a class="btn ghost sm" href="{{ route('admin.admins.edit', $a) }}">Edit</a>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">No admins.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $admins->links() }}
@endsection
