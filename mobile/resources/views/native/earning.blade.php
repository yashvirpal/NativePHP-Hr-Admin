@php
    $presetVariant = fn (string $key) => $activePreset === $key ? 'primary' : 'ghost';
@endphp
<native:column class="w-full h-full bg-theme-background">
    <native:refreshable @refresh="refresh" class="w-full flex-1">
        <native:column class="w-full p-4 gap-5">
            @if ($error !== '')
                <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
            @endif

            <native:column class="w-full gap-2">
                <native:text class="text-xs font-bold text-theme-on-surface-variant">Period</native:text>
                <native:row class="w-full gap-2">
                    <native:button class="flex-1" label="This month" variant="{{ $presetVariant('this_month') }}" size="sm" @press="selectThisMonth" />
                    <native:button class="flex-1" label="Last month" variant="{{ $presetVariant('last_month') }}" size="sm" @press="selectLastMonth" />
                    <native:button class="flex-1" label="3 months" variant="{{ $presetVariant('3') }}" size="sm" @press="selectPeriod(3)" />
                </native:row>
                <native:row class="w-full gap-2">
                    <native:button class="flex-1" label="6 months" variant="{{ $presetVariant('6') }}" size="sm" @press="selectPeriod(6)" />
                    <native:button class="flex-1" label="1 year" variant="{{ $presetVariant('12') }}" size="sm" @press="selectPeriod(12)" />
                    <native:button class="flex-1" label="Custom range" variant="{{ $presetVariant('custom') }}" size="sm" @press="showCustomRange" />
                </native:row>
            </native:column>

            @if ($showingCustomInputs)
                <native:column class="w-full p-3 gap-3 rounded-2xl bg-theme-surface-variant/40">
                    <native:date-picker
                        label="From"
                        mode="date"
                        value="{{ $customFrom }}"
                        @change="setCustomFrom"
                        max="{{ now()->toDateString() }}"
                        :is-error="($fieldErrors['customFrom'] ?? '') !== ''"
                        :supporting="$fieldErrors['customFrom'] ?? ''"
                    />
                    <native:date-picker
                        label="To"
                        mode="date"
                        value="{{ $customTo }}"
                        @change="setCustomTo"
                        max="{{ now()->toDateString() }}"
                        :is-error="($fieldErrors['customTo'] ?? '') !== ''"
                        :supporting="$fieldErrors['customTo'] ?? 'Cannot be after today.'"
                    />
                    <native:button label="Apply" variant="primary" size="sm" :loading="$busy" @press="applyCustomRange" />
                </native:column>
            @endif

            @if ($periodLabel !== '')
                <native:text class="text-sm text-theme-on-surface-variant">{{ $periodLabel }}</native:text>
            @endif

            <native:row class="w-full gap-3">
                <native:column class="flex-1 p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                    <native:text class="text-2xl font-bold text-theme-on-surface">{{ $regular }}</native:text>
                    <native:text class="text-xs text-theme-on-surface-variant">Regular hours</native:text>
                </native:column>
                <native:column class="flex-1 p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                    <native:text class="text-2xl font-bold text-theme-leave">{{ $leaveDays }}</native:text>
                    <native:text class="text-xs text-theme-on-surface-variant">Leave days</native:text>
                </native:column>
            </native:row>

            <native:column class="w-full p-3 gap-2 rounded-2xl bg-theme-surface-variant/40">
                <native:text class="text-xs font-bold text-theme-on-surface-variant">Overtime</native:text>
                <native:row class="w-full items-end justify-between">
                    <native:text class="text-2xl font-bold text-theme-accent">{{ $overtime }}</native:text>
                    @if ($overtimeAmount !== null)
                        <native:text class="text-lg font-bold text-theme-on-surface">{{ $overtimeAmount }}</native:text>
                    @endif
                </native:row>
            </native:column>
        </native:column>
    </native:refreshable>
</native:column>
