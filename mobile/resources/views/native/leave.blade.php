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

            @if (count($balances) > 0)
                <native:text class="text-lg font-bold text-theme-on-surface">Balance</native:text>
                <native:column class="w-full gap-2">
                    @foreach ($balances as $b)
                        <native:row class="w-full items-center justify-between">
                            <native:text class="text-base text-theme-on-surface">{{ $b['name'] }}</native:text>
                            <native:text class="text-base font-bold text-theme-on-surface">{{ $b['remaining'] }} / {{ $b['allocated'] }} ({{ $b['used'] }} used)</native:text>
                        </native:row>
                    @endforeach
                </native:column>
            @endif

            <native:text class="text-lg font-bold text-theme-on-surface">My requests</native:text>

            @forelse ($requests as $r)
                <native:column class="w-full p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base font-bold text-theme-on-surface">{{ $r['type'] }}</native:text>
                        <native:text class="text-sm font-bold {{ $tone($r['statusKey']) }}">{{ $r['status'] }}</native:text>
                    </native:row>
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $r['period'] }} - {{ $r['days'] }} day(s)</native:text>
                    @if ($r['reason'] !== '')
                        <native:text class="text-sm text-theme-on-surface-variant" :max-lines="2">{{ $r['reason'] }}</native:text>
                    @endif
                    @if ($r['canCancel'])
                        <native:button label="Cancel request" variant="ghost" size="sm" @press="confirmCancel({{ $r['id'] }})" />
                    @endif
                </native:column>
            @empty
                <native:text class="text-base text-theme-on-surface-variant">You have not requested any leave yet.</native:text>
            @endforelse
        </native:column>
    </native:refreshable>
</native:column>
