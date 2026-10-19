@extends('layouts.panel')
@section('title', $company->exists ? 'Edit company' : 'Add company')
@section('content')
@php $isCurrent = (bool) old('is_current', $company->is_current); @endphp
<div class="page-head"><div><h1>{{ $company->exists ? 'Edit company' : 'Add company' }}</h1></div></div>
<form method="post" class="card"
      action="{{ $company->exists ? route('employee.companies.update', $company) : route('employee.companies.store') }}">
    @csrf
    @if ($company->exists) @method('PUT') @endif

    <div class="field"><label class="check">
        <input type="checkbox" id="is_current" name="is_current" value="1" @checked($isCurrent)>
        <span>I currently work here</span></label></div>

    <div class="form-grid">
        <div class="field"><label for="name">Company name</label><input id="name" name="name" value="{{ old('name', $company->name) }}" required></div>
        <div class="field"><label for="industry">Industry</label><input id="industry" name="industry" value="{{ old('industry', $company->industry) }}"></div>
        <div class="field"><label for="designation">My designation</label><input id="designation" name="designation" value="{{ old('designation', $company->designation) }}"></div>
        <div class="field"><label for="website">Website</label><input id="website" type="url" name="website" placeholder="https://" value="{{ old('website', $company->website) }}"></div>
        <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" value="{{ old('phone', $company->phone) }}"></div>
        <div class="field"><label for="started_on">Started on <span id="started_req" class="muted"></span></label>
            <input id="started_on" type="date" name="started_on" max="{{ now()->toDateString() }}" value="{{ old('started_on', $company->started_on?->toDateString()) }}"></div>
        <div class="field" id="ended_wrap" @if ($isCurrent) hidden @endif><label for="ended_on">Ended on</label>
            <input id="ended_on" type="date" name="ended_on" max="{{ now()->toDateString() }}" value="{{ old('ended_on', $company->ended_on?->toDateString()) }}"
                   @disabled($isCurrent)></div>
    </div>
    <div class="field"><label for="address">Address</label><input id="address" name="address" value="{{ old('address', $company->address) }}"></div>

    <div class="row"><button>Save</button><a class="btn ghost" href="{{ route('employee.companies.index') }}">Cancel</a></div>
</form>

<script>
    // Current employer: no end date. Past employer: start and end dates are both required, end >= start.
    (function () {
        var current = document.getElementById('is_current');
        var started = document.getElementById('started_on');
        var ended = document.getElementById('ended_on');
        var wrap = document.getElementById('ended_wrap');

        function sync() {
            var isCurrent = current.checked;
            wrap.hidden = isCurrent;
            ended.disabled = isCurrent;          // disabled inputs are not submitted
            ended.required = !isCurrent;
            started.required = !isCurrent;
            ended.min = started.value || '';
        }

        current.addEventListener('change', sync);
        started.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
