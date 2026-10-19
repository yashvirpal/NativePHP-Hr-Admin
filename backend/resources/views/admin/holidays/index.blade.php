@extends('admin.layout')
@section('title', 'Holidays')
@section('content')
<div class="page-head"><div><h1>Holidays</h1><p class="sub">{{ $holidays->count() }} holidays</p></div></div>
<form method="post" action="{{ route('admin.holidays.store') }}" class="card row">
    @csrf
    <input name="name" placeholder="Holiday name" value="{{ old('name') }}" required>
    <input type="date" name="date" value="{{ old('date') }}" required>
    <label class="check"><input type="checkbox" name="is_optional" value="1"> Optional</label>
    <button>Add holiday</button>
</form>
<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Name</th><th>Type</th><th></th></tr></thead>
        <tbody>
        @forelse ($holidays as $h)
            <tr>
                <td>{{ $h->date->format('D, d M Y') }}</td><td>{{ $h->name }}</td>
                <td><span class="badge {{ $h->is_optional ? 'pending' : 'approved' }}">{{ $h->is_optional ? 'Optional' : 'Public' }}</span></td>
                <td class="actions"><form class="inline" method="post" action="{{ route('admin.holidays.destroy', $h) }}">@csrf @method('DELETE')<button class="danger sm">Remove</button></form></td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">No holidays yet.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
