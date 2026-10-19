@extends('layouts.panel')
@section('title', 'Holidays')
@section('content')
<div class="page-head">
    <div><h1>Holidays {{ $year }}</h1><p class="sub">{{ $holidays->count() }} holidays</p></div>
    <div class="row">
        <a class="btn ghost" href="{{ route('employee.holidays.index', ['year' => $year - 1]) }}">&lsaquo; {{ $year - 1 }}</a>
        <a class="btn ghost" href="{{ route('employee.holidays.index', ['year' => $year + 1]) }}">{{ $year + 1 }} &rsaquo;</a>
    </div>
</div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Holiday</th><th>Type</th></tr></thead>
        <tbody>
        @forelse ($holidays as $h)
            <tr>
                <td>{{ $h->date->format('D, d M Y') }}</td><td>{{ $h->name }}</td>
                <td><span class="badge {{ $h->is_optional ? 'pending' : 'approved' }}">{{ $h->is_optional ? 'Optional' : 'Public' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="3" class="empty">No holidays listed for {{ $year }}.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
