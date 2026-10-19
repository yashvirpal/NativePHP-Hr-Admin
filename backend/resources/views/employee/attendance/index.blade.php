@extends('layouts.panel')
@section('title', 'Attendance')
@section('content')
<div class="page-head">
    <div><h1>My attendance</h1><p class="sub">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</p></div>
    <form method="get" class="row">
        <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()">
        <noscript><button>Show</button></noscript>
    </form>
</div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>First in</th><th>Last out</th><th>Worked</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($records as $r)
            <tr>
                <td>{{ $r->work_date->format('D, d M Y') }}</td>
                <td>{{ $r->first_in_at?->format('H:i') ?? '-' }}</td>
                <td>{{ $r->last_out_at?->format('H:i') ?? '-' }}</td>
                <td>{{ $r->worked_minutes ? intdiv($r->worked_minutes, 60).'h '.($r->worked_minutes % 60).'m' : '-' }}</td>
                <td><span class="badge {{ $r->status }}">{{ str_replace('_', ' ', $r->status) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No attendance for this month.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $records->links() }}
@endsection
