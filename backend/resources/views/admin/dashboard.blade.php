@extends('admin.layout')
@section('title', 'Dashboard')
@section('content')
<div class="page-head">
    <div><h1>Dashboard</h1><p class="sub">{{ now()->format('l, d F Y') }}</p></div>
</div>

<div class="grid">
    <div class="card stat accent"><span>Active employees</span><b>{{ $activeEmployees }}</b></div>
    <div class="card stat ok"><span>Present today</span><b>{{ $presentToday }}</b></div>
    <div class="card stat warn"><span>Late today</span><b>{{ $lateToday }}</b></div>
    <div class="card stat"><span>On leave today</span><b>{{ $onLeaveToday }}</b></div>
    @can('leave.manage')
        <a class="card stat bad" href="{{ route('admin.leave-requests.index') }}"><span>Pending leave</span><b>{{ $pendingLeave }}</b></a>
    @else
        <div class="card stat bad"><span>Pending leave</span><b>{{ $pendingLeave }}</b></div>
    @endcan
    @can('overtime.manage')
        <a class="card stat bad" href="{{ route('admin.overtime-requests.index') }}"><span>Pending overtime</span><b>{{ $pendingOvertime }}</b></a>
    @else
        <div class="card stat bad"><span>Pending overtime</span><b>{{ $pendingOvertime }}</b></div>
    @endcan
</div>

@can('leave.manage')
<div class="card">
    <h2>Leave awaiting approval <a class="btn ghost sm" href="{{ route('admin.leave-requests.index') }}">View all</a></h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Employee</th><th>Type</th><th>Dates</th><th>Days</th><th></th></tr></thead>
        <tbody>
        @forelse ($pendingLeaveList as $r)
            <tr>
                <td><div class="person"><div class="avatar">{{ strtoupper(substr($r->employee->user->name, 0, 1)) }}</div>{{ $r->employee->user->name }}</div></td>
                <td>{{ $r->leaveType->name }}</td>
                <td>{{ $r->start_date->format('d M') }} – {{ $r->end_date->format('d M Y') }}</td>
                <td>{{ $r->days }}</td>
                <td class="actions">
                    <form class="inline" method="post" action="{{ route('admin.leave-requests.decide', [$r, 'approve']) }}">@csrf<button class="ok sm">Approve</button></form>
                    <form class="inline" method="post" action="{{ route('admin.leave-requests.decide', [$r, 'reject']) }}">@csrf<button class="bad sm">Reject</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Nothing waiting for approval.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

@endcan

@can('attendance.view')
<div class="card">
    <h2>Recent attendance <a class="btn ghost sm" href="{{ route('admin.attendance.index') }}">View all</a></h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Employee</th><th>Date</th><th>In</th><th>Out</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($recentAttendance as $r)
            <tr>
                <td><div class="person"><div class="avatar">{{ strtoupper(substr($r->employee->user->name, 0, 1)) }}</div>{{ $r->employee->user->name }}</div></td>
                <td>{{ $r->work_date->format('D, d M') }}</td>
                <td>{{ $r->first_in_at?->format('H:i') ?? '—' }}</td>
                <td>{{ $r->last_out_at?->format('H:i') ?? '—' }}</td>
                <td><span class="badge {{ $r->status }}">{{ str_replace('_', ' ', $r->status) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No attendance recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endcan
@endsection
