@extends('admin.layout')
@section('title', 'Overtime requests')
@section('content')
<div class="page-head"><div><h1>Overtime requests</h1><p class="sub">{{ $requests->total() }} total</p></div></div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Employee</th><th>Date</th><th>Duration</th><th>Rate / hour</th><th>Amount</th><th>Reason</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td><div class="person"><div class="avatar">{{ strtoupper(substr($r->employee->user->name, 0, 1)) }}</div>{{ $r->employee->user->name }}</div></td>
                <td>{{ $r->work_date->format('d M Y') }}</td>
                <td>{{ intdiv($r->minutes, 60) }}h {{ $r->minutes % 60 }}m</td>
                <td>{{ $r->rate_per_hour !== null ? number_format((float) $r->rate_per_hour, 2) : '-' }}</td>
                <td><b>{{ $r->amount !== null ? number_format((float) $r->amount, 2) : '-' }}</b></td>
                <td class="muted">{{ \Illuminate\Support\Str::limit($r->reason, 32) ?: '—' }}</td>
                <td><span class="badge {{ $r->status }}">{{ $r->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No overtime requests.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $requests->links() }}
@endsection
