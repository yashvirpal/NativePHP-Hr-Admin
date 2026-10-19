@if (session('status')) <div class="flash ok" role="status">{{ session('status') }}</div> @endif
@if ($errors->any())
    <div class="flash bad" role="alert">@foreach ($errors->all() as $e) <div>{{ $e }}</div> @endforeach</div>
@endif
