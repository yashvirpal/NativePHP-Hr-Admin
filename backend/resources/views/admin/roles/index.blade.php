@extends('admin.layout')
@section('title', 'Roles')
@section('content')
<div class="page-head">
    <div><h1>Roles</h1><p class="sub">Choose what each kind of admin is allowed to do.</p></div>
    <a class="btn" href="{{ route('admin.roles.create') }}">+ Add role</a>
</div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Role</th><th>Permissions</th><th>Admins</th><th></th></tr></thead>
        <tbody>
        @forelse ($roles as $r)
            <tr>
                <td>
                    <b>{{ $r->name }}</b> @if ($r->is_system) <span class="badge admin">System</span> @endif
                    <div class="muted" style="font-size:12px;white-space:normal;max-width:340px">{{ $r->description }}</div>
                </td>
                <td style="white-space:normal;max-width:420px">
                    @if ($r->isSuperAdmin())
                        <span class="badge approved">Everything</span>
                    @else
                        @forelse ($r->permissions as $key)
                            <span class="badge" style="margin:1px 2px 1px 0">{{ \App\Enums\Permission::tryFrom($key)?->label() ?? $key }}</span>
                        @empty
                            <span class="muted">No permissions (dashboard only)</span>
                        @endforelse
                    @endif
                </td>
                <td>{{ $r->admins_count }}</td>
                <td class="actions">
                    @if ($r->is_system)
                        <span class="muted">Locked</span>
                    @else
                        <a class="btn ghost sm" href="{{ route('admin.roles.edit', $r) }}">Edit</a>
                        <form class="inline" method="post" action="{{ route('admin.roles.destroy', $r) }}"
                              onsubmit="return confirm('Delete the role {{ $r->name }}?')">@csrf @method('DELETE')
                            <button class="danger sm" @disabled($r->admins_count > 0) title="{{ $r->admins_count > 0 ? 'Reassign its admins first' : '' }}">Delete</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">No roles.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
