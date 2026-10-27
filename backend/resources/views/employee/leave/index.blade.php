@extends('layouts.panel')
@section('title', 'Leave')
@section('content')
<div class="page-head"><div><h1>Leave</h1><p class="sub">My leaves</p></div></div>

<div class="card">
    <h2>My leaves</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td>{{ $r->leaveType->name }}</td>
                <td>{{ $r->start_date->format('d M Y') }}</td><td>{{ $r->end_date->format('d M Y') }}</td>
                <td>{{ $r->days }}</td><td class="muted">{{ \Illuminate\Support\Str::limit($r->reason, 32) ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">You have not requested any leave yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $requests->links() }}
@endsection
