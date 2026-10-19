<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:outlined-text-input
            label="Date worked (YYYY-MM-DD)"
            native:model="workDate"
            :is-error="($fieldErrors['work_date'] ?? '') !== ''"
            :supporting="$fieldErrors['work_date'] ?? ''"
        />

        <native:outlined-text-input
            label="Minutes"
            native:model="minutes"
            keyboard="number"
            :is-error="($fieldErrors['minutes'] ?? '') !== ''"
            :supporting="$fieldErrors['minutes'] ?? 'Between 1 and 720.'"
        />

        <native:outlined-text-input
            label="Reason (optional)"
            native:model="reason"
            multiline
            :is-error="($fieldErrors['reason'] ?? '') !== ''"
            :supporting="$fieldErrors['reason'] ?? ''"
        />

        <native:button label="Submit request" variant="primary" size="lg" :loading="$busy" @press="submit" />
    </native:column>
</native:scroll-view>
