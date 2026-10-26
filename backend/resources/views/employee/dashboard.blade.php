@extends('layouts.panel')
@section('title', 'Dashboard')
@section('content')
<div class="page-head">
    <div>
        <h1>Hello, {{ explode(' ', $employee->user->name)[0] }}</h1>
        <p class="sub">{{ now()->format('l, d F Y') }}
            @if ($employee->department) &middot; {{ $employee->department->name }} @endif
            @if ($employee->shift) &middot; Shift {{ substr($employee->shift->start_time, 0, 5) }}&ndash;{{ substr($employee->shift->end_time, 0, 5) }} @endif
        </p>
    </div>
</div>

<div class="card pad">
    <div class="muted">Today</div>
    <div style="font-size:22px;font-weight:700;margin-top:4px">
        @if ($is_punched_in) Punched in since {{ $today->first_in_at->format('H:i') }}
        @elseif ($today?->last_out_at) Done for the day &middot; out at {{ $today->last_out_at->format('H:i') }}
        @else Not punched in @endif
    </div>
    @if ($today) <span class="badge {{ $today->status }}" style="margin-top:8px">{{ str_replace('_', ' ', $today->status) }}</span> @endif
</div>

<div class="grid">
    <div class="card stat ok"><span>Days present (this month)</span><b>{{ $month['days_present'] }}</b></div>
    <div class="card stat warn"><span>Late arrivals</span><b>{{ $month['days_late'] }}</b></div>
    <div class="card stat accent"><span>Regular (this month)</span><b>{{ intdiv($totals['regular_minutes'], 60) }}h {{ $totals['regular_minutes'] % 60 }}m</b></div>
    <div class="card stat accent">
        <span>Overtime (this month)</span>
        <b>{{ intdiv($totals['overtime_minutes'], 60) }}h {{ $totals['overtime_minutes'] % 60 }}m</b>
        @if ($totals['overtime_amount'] !== null) <div class="muted">{{ $totals['overtime_amount'] }}</div> @endif
    </div>
</div>

<div class="card">
    <h2>Leave balance ({{ now()->year }}) <a class="btn ghost sm" href="{{ route('employee.leave.index') }}">Apply for leave</a></h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Type</th><th>Allocated</th><th>Used</th><th>Remaining</th></tr></thead>
        <tbody>
        @foreach ($balances->where('allocated', '>', 0) as $b)
            <tr><td>{{ $b['leave_type']->name }}</td><td>{{ $b['allocated'] }}</td><td>{{ $b['used'] }}</td><td><b>{{ $b['remaining'] }}</b></td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="card">
    <h2>Upcoming holidays</h2>
    <div class="table-wrap"><table>
        <tbody>
        @forelse ($upcoming_holidays as $holiday)
            <tr><td>{{ $holiday->date->format('D, d M Y') }}</td><td>{{ $holiday->name }}</td>
                <td><span class="badge {{ $holiday->is_optional ? 'pending' : 'approved' }}">{{ $holiday->is_optional ? 'Optional' : 'Public' }}</span></td></tr>
        @empty
            <tr><td class="empty">No upcoming holidays.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
