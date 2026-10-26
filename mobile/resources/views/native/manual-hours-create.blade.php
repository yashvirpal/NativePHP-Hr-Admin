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
