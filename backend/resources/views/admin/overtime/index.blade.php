@extends('admin.layout')
@section('title', 'Overtime requests')
@section('content')
<div class="page-head"><div><h1>Overtime requests</h1><p class="sub">{{ $requests->total() }} {{ $status === 'all' ? 'total' : $status }}</p></div></div>
<div class="tabs">
    @foreach (['pending', 'approved', 'rejected', 'all'] as $s)
        <a @class(['active' => $status === $s]) href="{{ route('admin.overtime-requests.index', ['status' => $s]) }}">{{ ucfirst($s) }}</a>
    @endforeach
</div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Employee</th><th>Date</th><th>Duration</th><th>Rate / hour</th><th>Amount</th><th>Reason</th><th>Status</th><th></th></tr></thead>
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
                <td class="actions">
                    @if ($r->status === 'pending')
                        <form class="inline" method="post" action="{{ route('admin.overtime-requests.decide', [$r, 'approve']) }}">@csrf<button class="ok sm">Approve</button></form>
                        <form class="inline" method="post" action="{{ route('admin.overtime-requests.decide', [$r, 'reject']) }}">@csrf<button class="bad sm">Reject</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No {{ $status === 'all' ? '' : $status }} overtime requests.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $requests->links() }}
@endsection
