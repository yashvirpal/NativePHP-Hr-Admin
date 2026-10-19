@extends('admin.layout')
@section('title', 'Attendance')
@section('content')
<div class="page-head">
    <div><h1>Attendance</h1><p class="sub">{{ \Illuminate\Support\Carbon::parse($date)->format('l, d F Y') }}</p></div>
    <form method="get" class="row">
        <a class="btn ghost" href="{{ route('admin.attendance.index', ['date' => \Illuminate\Support\Carbon::parse($date)->subDay()->toDateString()]) }}">‹ Prev</a>
        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()">
        <a class="btn ghost" href="{{ route('admin.attendance.index', ['date' => \Illuminate\Support\Carbon::parse($date)->addDay()->toDateString()]) }}">Next ›</a>
        <noscript><button>Show</button></noscript>
    </form>
</div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Employee</th><th>First in</th><th>Last out</th><th>Worked</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($records as $r)
            <tr>
                <td><div class="person"><div class="avatar">{{ strtoupper(substr($r->employee->user->name, 0, 1)) }}</div>{{ $r->employee->user->name }}</div></td>
                <td>{{ $r->first_in_at?->format('H:i') ?? '—' }}</td>
                <td>{{ $r->last_out_at?->format('H:i') ?? '—' }}</td>
                <td>{{ $r->worked_minutes ? intdiv($r->worked_minutes, 60).'h '.($r->worked_minutes % 60).'m' : '—' }}</td>
                <td><span class="badge {{ $r->status }}">{{ str_replace('_', ' ', $r->status) }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No attendance recorded for this date.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $records->links() }}
@endsection
