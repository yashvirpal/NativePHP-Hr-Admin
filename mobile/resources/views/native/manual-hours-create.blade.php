<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:column class="w-full gap-1">
            <native:text class="text-sm text-theme-on-surface-variant">{{ __('Date') }}</native:text>
            <native:text class="text-lg font-bold text-theme-on-surface">{{ $workDate }}</native:text>
        </native:column>

        <native:outlined-text-input
            label="{{ __('Regular hours') }}"
            native:model="regularHours"
            keyboard="decimal"
            :is-error="($fieldErrors['regularHours'] ?? '') !== ''"
            :supporting="$fieldErrors['regularHours'] ?? __('E.g. 1.5. Max 24. Leave blank to leave unchanged.')"
        />

        <native:outlined-text-input
            label="{{ __('Overtime hours') }}"
            native:model="overtimeHours"
            keyboard="decimal"
            :is-error="($fieldErrors['overtimeHours'] ?? '') !== ''"
            :supporting="$fieldErrors['overtimeHours'] ?? __('E.g. 1.5. Max 24. Leave blank to leave unchanged.')"
        />

        @if ($onLeave)
            <native:column class="w-full p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                <native:text class="text-sm font-bold text-theme-leave">{{ __('On leave') }}</native:text>
                <native:text class="text-sm text-theme-on-surface-variant">
                    {{ __('Marked as :type. Cancel it from the Leave tab if this was a mistake.', ['type' => $leaveTypeName ?? __('leave')]) }}
                </native:text>
            </native:column>
        @elseif ($canMarkLeave)
            <native:column class="w-full gap-3">
                <native:toggle label="{{ __('Mark as leave') }}" native:model="markLeave" />

                @if ($markLeave)
                    <native:date-picker
                        label="{{ __('Through') }}"
                        mode="date"
                        value="{{ $leaveEndDate }}"
                        @change="setLeaveEndDate"
                        min="{{ $workDate }}"
                        :is-error="($fieldErrors['leaveEndDate'] ?? '') !== ''"
                        :supporting="$fieldErrors['leaveEndDate'] ?? __('Same day, or extend the range.')"
                    />
                @endif
            </native:column>
        @endif

        <native:outlined-text-input
            label="{{ __('Reason (optional)') }}"
            native:model="reason"
            multiline
            :is-error="($fieldErrors['reason'] ?? '') !== ''"
            :supporting="$fieldErrors['reason'] ?? ''"
        />

        <native:button
            label="{{ __('Save') }}"
            variant="primary"
            size="lg"
            :loading="$busy"
            @press="submit"
        />
    </native:column>
</native:scroll-view>
