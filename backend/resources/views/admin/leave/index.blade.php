@extends('admin.layout')
@section('title', 'Leave requests')
@section('content')
<div class="page-head"><div><h1>Leave requests</h1><p class="sub">{{ $requests->total() }} total</p></div></div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Employee</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td><div class="person"><div class="avatar">{{ strtoupper(substr($r->employee->user->name, 0, 1)) }}</div>{{ $r->employee->user->name }}</div></td>
                <td>{{ $r->leaveType->name }}</td>
                <td>{{ $r->start_date->format('d M Y') }}</td><td>{{ $r->end_date->format('d M Y') }}</td>
                <td>{{ $r->days }}</td><td class="muted">{{ \Illuminate\Support\Str::limit($r->reason, 32) ?: '—' }}</td>
                <td><span class="badge {{ $r->status }}">{{ $r->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No leave requests.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $requests->links() }}
@endsection
