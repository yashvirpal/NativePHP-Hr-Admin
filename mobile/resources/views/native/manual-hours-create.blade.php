<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:column class="w-full gap-1">
            <native:text class="text-sm text-theme-on-surface-variant">Date</native:text>
            <native:text class="text-lg font-bold text-theme-on-surface">{{ $workDate }}</native:text>
        </native:column>

        <native:outlined-text-input
            label="Regular hours"
            native:model="regularHours"
            keyboard="decimal"
            :is-error="($fieldErrors['regularHours'] ?? '') !== ''"
            :supporting="$fieldErrors['regularHours'] ?? 'E.g. 1.5. Max 24. Leave blank to leave unchanged.'"
        />

        <native:outlined-text-input
            label="Overtime hours"
            native:model="overtimeHours"
            keyboard="decimal"
            :is-error="($fieldErrors['overtimeHours'] ?? '') !== ''"
            :supporting="$fieldErrors['overtimeHours'] ?? 'E.g. 1.5. Max 24. Leave blank to leave unchanged.'"
        />

        @if ($onLeave)
            <native:column class="w-full p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                <native:text class="text-sm font-bold text-theme-leave">On leave</native:text>
                <native:text class="text-sm text-theme-on-surface-variant">
                    Marked as {{ $leaveTypeName ?? 'leave' }}. Cancel it from the Leave tab if this was a mistake.
                </native:text>
            </native:column>
        @elseif ($canMarkLeave)
            <native:column class="w-full gap-3">
                <native:toggle label="Mark as leave" native:model="markLeave" />

                @if ($markLeave)
                    <native:date-picker
                        label="Through"
                        mode="date"
                        value="{{ $leaveEndDate }}"
                        @change="setLeaveEndDate"
                        min="{{ $workDate }}"
                        :is-error="($fieldErrors['leaveEndDate'] ?? '') !== ''"
                        :supporting="$fieldErrors['leaveEndDate'] ?? 'Same day, or extend the range.'"
                    />
                @endif
            </native:column>
        @endif

        <native:outlined-text-input
            label="Reason (optional)"
            native:model="reason"
            multiline
            :is-error="($fieldErrors['reason'] ?? '') !== ''"
            :supporting="$fieldErrors['reason'] ?? ''"
        />

        <native:button
            label="Save"
            variant="primary"
            size="lg"
            :loading="$busy"
            @press="submit"
        />
    </native:column>
</native:scroll-view>
