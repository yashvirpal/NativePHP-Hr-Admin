@extends('layouts.panel')
@section('title', 'My companies')
@section('content')
<div class="page-head">
    <div><h1>My companies</h1><p class="sub">Employer and client details on your profile</p></div>
    <a class="btn" href="{{ route('employee.companies.create') }}">+ Add company</a>
</div>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Company</th><th>Industry</th><th>My role</th><th>Period</th><th>Contact</th><th></th></tr></thead>
        <tbody>
        @forelse ($companies as $c)
            <tr>
                <td><b>{{ $c->name }}</b>@if ($c->is_current) <span class="badge approved">Current</span> @endif</td>
                <td>{{ $c->industry ?? '-' }}</td>
                <td>{{ $c->designation ?? '-' }}</td>
                <td>{{ $c->started_on?->format('M Y') ?? '-' }} &ndash; {{ $c->is_current ? 'Present' : ($c->ended_on?->format('M Y') ?? '-') }}</td>
                <td>
                    @if ($c->website) <a href="{{ $c->website }}" target="_blank" rel="noopener noreferrer">{{ parse_url($c->website, PHP_URL_HOST) ?: $c->website }}</a><br> @endif
                    <span class="muted">{{ $c->phone }}</span>
                </td>
                <td class="actions">
                    <a class="btn ghost sm" href="{{ route('employee.companies.edit', $c) }}">Edit</a>
                    <form class="inline" method="post" action="{{ route('employee.companies.destroy', $c) }}"
                          onsubmit="return confirm('Remove this company?')">@csrf @method('DELETE')<button class="danger sm">Remove</button></form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No companies yet. Add your employer details.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
