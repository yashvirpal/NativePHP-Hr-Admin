@extends('layouts.panel')
@section('title', 'Overtime')
@section('content')
<div class="page-head"><div><h1>Overtime</h1><p class="sub">Request approval for extra hours you worked</p></div></div>

<div class="card pad">
    <div class="row" style="justify-content:space-between;align-items:flex-start;gap:24px">
        <div>
            <div class="muted">Your hourly overtime rate</div>
            <div style="font-size:26px;font-weight:700">
                {{ $rate['rate'] !== null ? number_format((float) $rate['rate'], 2) : 'Not set' }}
                @if ($rate['rate'] !== null) <span class="muted" style="font-size:14px;font-weight:500">per hour</span> @endif
            </div>
            <p class="muted" style="margin:6px 0 0;max-width:440px">
                Changing your rate only affects overtime you submit <b>from now on</b>.
                Requests you already submitted keep the rate they were submitted with.
            </p>
        </div>
        <form method="post" action="{{ route('employee.overtime.rate') }}" class="row">
            @csrf @method('PUT')
            <div class="field" style="margin:0">
                <label for="hourly_rate">New hourly rate</label>
                <input id="hourly_rate" type="number" name="hourly_rate" step="0.01" min="0" max="99999.99"
                       value="{{ old('hourly_rate', $rate['rate']) }}" required style="width:160px">
            </div>
            <button style="align-self:flex-end">Update rate</button>
        </form>
    </div>

    @if (count($rate['history']) > 1)
        <details style="margin-top:14px">
            <summary class="muted" style="cursor:pointer">Rate history</summary>
            <table style="margin-top:8px">
                <thead><tr><th>Rate / hour</th><th>Effective from</th></tr></thead>
                <tbody>
                @foreach ($rate['history'] as $h)
                    <tr>
                        <td>{{ number_format((float) $h['hourly_rate'], 2) }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($h['effective_from'])->format('d M Y, H:i') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </details>
    @endif
</div>

<form method="post" action="{{ route('employee.overtime.store') }}" class="card">
    @csrf
    <div class="form-grid">
        <div class="field"><label for="work_date">Date worked</label><input id="work_date" type="date" name="work_date" value="{{ old('work_date') }}" max="{{ now()->toDateString() }}" required></div>
        <div class="field"><label for="minutes">Minutes</label><input id="minutes" type="number" name="minutes" value="{{ old('minutes', 60) }}" min="1" max="720" required></div>
        <div class="field"><label for="reason">Reason</label><input id="reason" name="reason" value="{{ old('reason') }}" maxlength="1000"></div>
    </div>
    <p class="muted" style="margin-top:0">
        @if ($rate['rate'] !== null) Will be worth {{ number_format((float) $rate['rate'], 2) }} per hour. @else No rate set: set one above to see an amount. @endif
    </p>
    <button>Submit request</button>
</form>

<div class="card">
    <h2>My requests</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Duration</th><th>Rate / hour</th><th>Amount</th><th>Reason</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td>{{ $r->work_date->format('D, d M Y') }}</td>
                <td>{{ intdiv($r->minutes, 60) }}h {{ $r->minutes % 60 }}m</td>
                <td>{{ $r->rate_per_hour !== null ? number_format((float) $r->rate_per_hour, 2) : '-' }}</td>
                <td><b>{{ $r->amount !== null ? number_format((float) $r->amount, 2) : '-' }}</b></td>
                <td class="muted">{{ \Illuminate\Support\Str::limit($r->reason, 40) ?: '-' }}</td>
                <td><span class="badge {{ $r->status }}">{{ $r->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No overtime requests yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
{{ $requests->links() }}
@endsection
