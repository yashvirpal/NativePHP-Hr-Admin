<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:toggle label="I currently work here" native:model="isCurrent" />

        <native:outlined-text-input
            label="Company name"
            native:model="name"
            autocapitalize="words"
            :is-error="($fieldErrors['name'] ?? '') !== ''"
            :supporting="$fieldErrors['name'] ?? ''"
        />

        <native:outlined-text-input label="Industry" native:model="industry" autocapitalize="words" />
        <native:outlined-text-input label="My designation" native:model="designation" autocapitalize="words" />

        <native:outlined-text-input
            label="Started on (YYYY-MM-DD)"
            native:model="startedOn"
            :is-error="($fieldErrors['started_on'] ?? '') !== ''"
            :supporting="$fieldErrors['started_on'] ?? ($isCurrent ? 'Optional' : 'Required: you no longer work here.')"
        />

        @if (! $isCurrent)
            <native:outlined-text-input
                label="Ended on (YYYY-MM-DD)"
                native:model="endedOn"
                :is-error="($fieldErrors['ended_on'] ?? '') !== ''"
                :supporting="$fieldErrors['ended_on'] ?? 'Required: the date you left.'"
            />
        @endif

        <native:outlined-text-input
            label="Website"
            native:model="website"
            keyboard="url"
            autocapitalize="none"
            :is-error="($fieldErrors['website'] ?? '') !== ''"
            :supporting="$fieldErrors['website'] ?? 'Include https://'"
        />
        <native:outlined-text-input label="Phone" native:model="phone" keyboard="phone" />
        <native:outlined-text-input label="Address" native:model="address" multiline />

        <native:button label="Save" variant="primary" size="lg" :loading="$busy" @press="save" />
    </native:column>
</native:scroll-view>
