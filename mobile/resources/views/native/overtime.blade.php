@php
    $tone = fn (string $key) => match ($key) {
        'approved' => 'text-theme-primary',
        'rejected' => 'text-theme-destructive',
        default => 'text-theme-on-surface-variant',
    };
@endphp
<native:column class="w-full h-full bg-theme-background">
    <native:refreshable @refresh="refresh" class="w-full flex-1">
        <native:column class="w-full p-4 gap-4">
            @if ($error !== '')
                <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
            @endif

            <native:column class="w-full p-4 gap-1 rounded-2xl bg-theme-surface-variant/40">
                <native:text class="text-sm text-theme-on-surface-variant">Your hourly overtime rate</native:text>
                @if ($rate !== '')
                    <native:text class="text-2xl font-bold text-theme-on-surface">{{ $rate }} per hour</native:text>
                @else
                    <native:text class="text-xl font-bold text-theme-on-surface">Not set</native:text>
                @endif
                <native:button label="{{ $rate !== '' ? 'Change rate' : 'Set rate' }}" variant="ghost" size="sm" @press="changeRate" />
            </native:column>

            <native:button label="Request overtime" variant="primary" @press="newRequest" />

            <native:text class="text-lg font-bold text-theme-on-surface">My requests</native:text>

            @forelse ($requests as $r)
                <native:column class="w-full p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base font-bold text-theme-on-surface">{{ $r['date'] }}</native:text>
                        <native:text class="text-sm font-bold {{ $tone($r['statusKey']) }}">{{ $r['status'] }}</native:text>
                    </native:row>
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $r['duration'] }} - {{ $r['priced'] }}</native:text>
                    @if ($r['reason'] !== '')
                        <native:text class="text-sm text-theme-on-surface-variant" :max-lines="2">{{ $r['reason'] }}</native:text>
                    @endif
                </native:column>
            @empty
                <native:text class="text-base text-theme-on-surface-variant">No overtime requests yet.</native:text>
            @endforelse
        </native:column>
    </native:refreshable>
</native:column>
