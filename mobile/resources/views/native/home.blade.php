<native:column class="w-full h-full bg-theme-background">
    <native:refreshable @refresh="refresh" class="w-full flex-1">
        <native:column class="w-full p-4 gap-4">
            @if ($error !== '')
                <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
            @endif

            @if ($loaded)
                <native:column class="w-full gap-1">
                    <native:text class="text-2xl font-bold text-theme-on-surface">Hello, {{ explode(' ', $name)[0] }}</native:text>
                    @if ($subtitle !== '')
                        <native:text class="text-sm text-theme-on-surface-variant">{{ $subtitle }}</native:text>
                    @endif
                </native:column>

                {{-- Today / punch card --}}
                <native:column class="w-full p-4 gap-3 rounded-2xl bg-theme-surface-variant/40">
                    <native:text class="text-sm text-theme-on-surface-variant">Today</native:text>
                    <native:text class="text-xl font-bold text-theme-on-surface">{{ $todayLine }}</native:text>
                    @if ($todayStatus !== '')
                        <native:text class="text-sm text-theme-primary">{{ $todayStatus }}</native:text>
                    @endif
                </native:column>

                {{-- This month --}}
                <native:text class="text-lg font-bold text-theme-on-surface">This month</native:text>
                <native:row class="w-full gap-3">
                    <native:column class="flex-1 p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                        <native:text class="text-2xl font-bold text-theme-on-surface">{{ $month['present'] }}</native:text>
                        <native:text class="text-xs text-theme-on-surface-variant">Days present</native:text>
                    </native:column>
                    <native:column class="flex-1 p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                        <native:text class="text-2xl font-bold text-theme-leave">{{ $month['leaveDays'] }}</native:text>
                        <native:text class="text-xs text-theme-on-surface-variant">Leaves</native:text>
                    </native:column>
                </native:row>
                <native:column class="w-full p-3 gap-2 rounded-2xl bg-theme-surface-variant/40">
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base text-theme-on-surface">Regular</native:text>
                        <native:text class="text-base font-bold text-theme-on-surface">{{ $month['regular'] }}</native:text>
                    </native:row>
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base text-theme-on-surface">Overtime</native:text>
                        <native:text class="text-base font-bold text-theme-primary">
                            {{ $month['overtime'] }}@if ($month['overtimeAmount'] !== null) - {{ $month['overtimeAmount'] }} @endif
                        </native:text>
                    </native:row>
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base text-theme-on-surface">Overtime total (all-time)</native:text>
                        <native:text class="text-base font-bold text-theme-accent">{{ $lifetimeOvertime['amount'] ?? '0.00' }}</native:text>
                    </native:row>
                </native:column>

                {{-- This year --}}
                <native:text class="text-lg font-bold text-theme-on-surface">This year</native:text>
                <native:column class="w-full p-3 gap-2 rounded-2xl bg-theme-surface-variant/40">
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base text-theme-on-surface">Regular</native:text>
                        <native:text class="text-base font-bold text-theme-on-surface">{{ $year['regular'] }}</native:text>
                    </native:row>
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base text-theme-on-surface">Overtime</native:text>
                        <native:text class="text-base font-bold text-theme-primary">
                            {{ $year['overtime'] }}@if ($year['overtimeAmount'] !== null) - {{ $year['overtimeAmount'] }} @endif
                        </native:text>
                    </native:row>
                </native:column>

                {{-- Holidays --}}
                @if (count($holidays) > 0)
                    <native:text class="text-lg font-bold text-theme-on-surface">Upcoming holidays</native:text>
                    <native:column class="w-full gap-2">
                        @foreach ($holidays as $h)
                            <native:row class="w-full items-center justify-between">
                                <native:text class="text-base text-theme-on-surface">{{ $h['name'] }}{{ $h['optional'] ? ' (optional)' : '' }}</native:text>
                                <native:text class="text-sm text-theme-on-surface-variant">{{ $h['date'] }}</native:text>
                            </native:row>
                        @endforeach
                    </native:column>
                @endif
            @elseif ($error === '')
                <native:text class="text-base text-theme-on-surface-variant">Loading...</native:text>
            @endif
        </native:column>
    </native:refreshable>
</native:column>
