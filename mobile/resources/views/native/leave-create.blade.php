<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:select
            label="Leave type"
            placeholder="Choose a leave type"
            :options="$this->typeNames()"
            native:model="typeName"
        />
        @if (($fieldErrors['leave_type_id'] ?? '') !== '')
            <native:text class="text-sm text-theme-destructive">{{ $fieldErrors['leave_type_id'] }}</native:text>
        @endif

        <native:outlined-text-input
            label="From (YYYY-MM-DD)"
            native:model="startDate"
            keyboard="text"
            :is-error="($fieldErrors['start_date'] ?? '') !== ''"
            :supporting="$fieldErrors['start_date'] ?? 'Weekends and public holidays are not counted.'"
        />

        <native:outlined-text-input
            label="To (YYYY-MM-DD)"
            native:model="endDate"
            keyboard="text"
            :is-error="($fieldErrors['end_date'] ?? '') !== ''"
            :supporting="$fieldErrors['end_date'] ?? ''"
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
