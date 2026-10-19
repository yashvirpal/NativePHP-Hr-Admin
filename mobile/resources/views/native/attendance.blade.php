@php
    $tone = fn (string $key) => match ($key) {
        'present' => 'text-theme-primary',
        'late', 'half_day' => 'text-theme-on-surface',
        'absent' => 'text-theme-destructive',
        default => 'text-theme-on-surface-variant',
    };
@endphp
<native:column class="w-full h-full bg-theme-background">
    <native:row class="w-full p-4 items-center justify-between">
        <native:button label="Prev" variant="ghost" size="sm" @press="previousMonth" />
        <native:text class="text-lg font-bold text-theme-on-surface">{{ $monthLabel }}</native:text>
        <native:button label="Next" variant="ghost" size="sm" :disabled="! $canGoNext" @press="nextMonth" />
    </native:row>

    <native:refreshable @refresh="refresh" class="w-full flex-1">
        <native:column class="w-full px-4 pb-4 gap-3">
            @if ($error !== '')
                <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
            @endif

            @forelse ($rows as $row)
                <native:column class="w-full p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base font-bold text-theme-on-surface">{{ $row['date'] }}</native:text>
                        <native:text class="text-sm font-bold {{ $tone($row['statusKey']) }}">{{ $row['status'] }}</native:text>
                    </native:row>
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $row['detail'] }}</native:text>
                </native:column>
            @empty
                @if ($error === '')
                    <native:text class="text-base text-theme-on-surface-variant">No attendance recorded for {{ $monthLabel }}.</native:text>
                @endif
            @endforelse
        </native:column>
    </native:refreshable>
</native:column>
