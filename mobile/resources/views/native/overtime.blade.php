<native:column class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:column class="w-full p-4 gap-2 rounded-2xl bg-theme-surface-variant/40">
            <native:row class="w-full items-center justify-between">
                <native:column class="gap-1">
                    <native:text class="text-xs font-bold text-theme-on-surface-variant">Your hourly rate</native:text>
                    @if ($rate !== '')
                        <native:text class="text-2xl font-bold text-theme-accent">{{ $rate }} / hr</native:text>
                    @else
                        <native:text class="text-xl font-bold text-theme-on-surface">Not set</native:text>
                    @endif
                </native:column>
                <native:button label="{{ $rate !== '' ? 'Change rate' : 'Set rate' }}" variant="primary" size="sm" @press="changeRate" />
            </native:row>
        </native:column>

        <native:row class="w-full items-center justify-between">
            <native:button label="Prev" variant="ghost" size="sm" @press="previousMonth" />
            <native:text class="text-lg font-bold text-theme-on-surface">{{ $monthLabel }}</native:text>
            <native:button label="Next" variant="ghost" size="sm" :disabled="! $canGoNext" @press="nextMonth" />
        </native:row>

        <native:row class="w-full items-center justify-between">
            <native:text class="text-sm font-bold text-theme-on-surface-variant">My overtime</native:text>
            <native:text class="text-sm font-bold text-theme-accent">
                {{ $totalDuration }}@if ($totalAmount !== null) - {{ $totalAmount }} @endif
            </native:text>
        </native:row>
    </native:column>

    <native:refreshable @refresh="refresh" class="w-full flex-1">
        <native:column class="w-full px-4 pb-4 gap-3">
            @forelse ($requests as $r)
                <native:column class="w-full p-3 gap-2 rounded-2xl bg-theme-surface-variant/40">
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base font-bold text-theme-on-surface">{{ $r['date'] }}</native:text>
                        <native:text class="text-base font-bold text-theme-accent">{{ $r['amount'] ?? 'No rate set' }}</native:text>
                    </native:row>
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-sm text-theme-on-surface-variant">{{ $r['duration'] }}</native:text>
                        @if ($r['ratePerHour'] !== null)
                            <native:text class="text-sm text-theme-on-surface-variant">{{ $r['ratePerHour'] }}</native:text>
                        @endif
                    </native:row>
                    @if ($r['reason'] !== '')
                        <native:text class="text-sm text-theme-on-surface-variant" :max-lines="2">{{ $r['reason'] }}</native:text>
                    @endif
                </native:column>
            @empty
                <native:text class="text-base text-theme-on-surface-variant">No overtime logged for {{ $monthLabel }}.</native:text>
            @endforelse
        </native:column>
    </native:refreshable>
</native:column>
