@extends('layouts.panel')
@section('title', 'Leave')
@section('content')
<div class="page-head"><div><h1>Leave</h1><p class="sub">Balances and requests</p></div></div>

<div class="grid">
    @foreach ($balances->where('allocated', '>', 0) as $b)
        <div class="card stat"><span>{{ $b['leave_type']->name }}</span><b>{{ $b['remaining'] }}</b><span>of {{ $b['allocated'] }} days left &middot; {{ $b['used'] }} used</span></div>
    @endforeach
</div>

<div class="card">
    <h2>My requests</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td>{{ $r->leaveType->name }}</td>
                <td>{{ $r->start_date->format('d M Y') }}</td><td>{{ $r->end_date->format('d M Y') }}</td>
                <td>{{ $r->days }}</td><td class="muted">{{ \Illuminate\Support\Str::limit($r->reason, 32) ?: '-' }}</td>
                <td><span class="badge {{ $r->status }}">{{ $r->status }}</span></td>
                <td class="actions">
                    @if ($r->status === 'approved')
                        <form class="inline" method="post" action="{{ route('employee.leave.cancel', $r) }}">@csrf<button class="danger sm">Cancel</button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">You have not requested any leave yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $requests->links() }}
@endsection
