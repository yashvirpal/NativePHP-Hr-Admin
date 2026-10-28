@php
    $tone = fn (string $key) => match ($key) {
        'present' => 'text-theme-primary',
        'late', 'half_day' => 'text-theme-on-surface',
        'leave' => 'text-theme-leave',
        'logged' => 'text-theme-accent',
        'absent' => 'text-theme-destructive',
        default => 'text-theme-on-surface-variant',
    };
    // A leave day gets a tinted cell background on the grid, not just a colored number -
    // otherwise the distinction is too subtle to read at a glance.
    $cellBg = fn (string $key) => $key === 'leave' ? 'bg-theme-leave/15' : 'bg-theme-surface-variant/40';
    $weekdays = [__('Sun'), __('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat')];
@endphp
<native:column class="w-full h-full bg-theme-background">
    <native:row class="w-full p-4 items-center justify-between">
        <native:button label="{{ __('Prev') }}" variant="ghost" size="sm" @press="previousMonth" />
        <native:text class="text-lg font-bold text-theme-on-surface">{{ $monthLabel }}</native:text>
        <native:button label="{{ __('Next') }}" variant="ghost" size="sm" :disabled="! $canGoNext" @press="nextMonth" />
    </native:row>

    <native:column class="w-full px-4 pb-2">
        <native:column class="w-full p-3 rounded-xl bg-theme-surface-variant/40 gap-1">
            <native:text class="text-xs font-bold text-theme-on-surface-variant">{{ __('This month') }}</native:text>
            <native:row class="w-full items-center justify-between">
                <native:text class="text-sm text-theme-on-surface">{{ __('Regular') }}</native:text>
                <native:text class="text-sm font-bold text-theme-on-surface">{{ $monthTotals['regular'] }}</native:text>
            </native:row>
            <native:row class="w-full items-center justify-between">
                <native:text class="text-sm text-theme-on-surface">{{ __('Overtime') }}</native:text>
                <native:text class="text-sm font-bold text-theme-primary">
                    {{ $monthTotals['overtime'] }}@if ($monthTotals['overtimeAmount'] !== null) - {{ $monthTotals['overtimeAmount'] }} @endif
                </native:text>
            </native:row>
            <native:row class="w-full items-center justify-between">
                <native:text class="text-sm text-theme-on-surface">{{ __('Leave') }}</native:text>
                <native:text class="text-sm font-bold text-theme-leave">{{ $monthTotals['leaveDays'] }} {{ __('day(s)') }}</native:text>
            </native:row>
        </native:column>
    </native:column>

    <native:refreshable @refresh="refresh" class="w-full flex-1">
        <native:column class="w-full px-4 pb-4 gap-3">
            @if ($error !== '')
                <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
            @endif

            @if (count($rows) > 0)
                <native:row class="w-full">
                    @foreach ($weekdays as $day)
                        <native:column class="flex-1 items-center">
                            <native:text class="text-xs font-bold text-theme-on-surface-variant">{{ $day }}</native:text>
                        </native:column>
                    @endforeach
                </native:row>

                <native:lazy-grid :columns="7" :gap="4" class="w-full">
                    @foreach ($leadingOverflowDays as $day)
                        <native:column :height="48" class="items-center justify-center">
                            <native:text class="font-bold text-theme-on-surface-variant/40">{{ $day }}</native:text>
                        </native:column>
                    @endforeach

                    @foreach ($rows as $row)
                        <native:pressable @press="selectDay('{{ $row['date'] }}')">
                            <native:column :height="48" class="items-center justify-center rounded-xl {{ $cellBg($row['statusKey']) }}">
                                <native:text class="font-bold {{ $tone($row['statusKey']) }}">{{ $row['day'] }}</native:text>
                            </native:column>
                        </native:pressable>
                    @endforeach

                    @foreach ($trailingOverflowDays as $day)
                        <native:column :height="48" class="items-center justify-center">
                            <native:text class="font-bold text-theme-on-surface-variant/40">{{ $day }}</native:text>
                        </native:column>
                    @endforeach
                </native:lazy-grid>
            @elseif ($error === '')
                {{-- Only reachable if the API call itself failed without setting $error (shouldn't
                     normally happen - Screen::call() always fills $error on failure) --}}
                <native:text class="text-base text-theme-on-surface-variant">{{ __('No attendance recorded for :month.', ['month' => $monthLabel]) }}</native:text>
            @endif
        </native:column>
    </native:refreshable>

    <native:modal :visible="$selectedDay !== null" @dismiss="closeDay">
        <native:column class="w-full h-full p-4 gap-3 safe-area bg-theme-background">
            <native:button label="{{ __('Close') }}" variant="ghost" size="sm" @press="closeDay" />
            @if ($selectedDay !== null)
                <native:text class="text-xl font-bold text-theme-on-surface">{{ $selectedDay['dateLabel'] }}</native:text>
                @if ($selectedDay['status'] !== '')
                    <native:text class="text-sm font-bold {{ $tone($selectedDay['statusKey']) }}">{{ $selectedDay['status'] }}</native:text>
                @endif
                <native:text class="text-sm text-theme-on-surface-variant">{{ $selectedDay['detail'] }}</native:text>

                <native:column class="w-full gap-2 mt-2">
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base text-theme-on-surface">{{ __('Regular') }}</native:text>
                        <native:text class="text-base font-bold text-theme-on-surface">{{ \App\Support\Format::minutes($selectedDay['regularMinutes']) }}</native:text>
                    </native:row>
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base text-theme-on-surface">{{ __('Overtime') }}</native:text>
                        <native:text class="text-base font-bold text-theme-primary">
                            {{ \App\Support\Format::minutes($selectedDay['overtimeMinutes']) }}
                            @if ($selectedDay['overtimeAmount'] !== null) - {{ $selectedDay['overtimeAmount'] }} @endif
                        </native:text>
                    </native:row>
                </native:column>

                <native:button label="{{ __('Log hours') }}" variant="primary" size="sm" @press="logHours" />
            @endif
        </native:column>
    </native:modal>
</native:column>
