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
</div>

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
