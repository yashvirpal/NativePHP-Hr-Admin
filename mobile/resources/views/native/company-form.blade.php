<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:toggle label="{{ __('I currently work here') }}" native:model="isCurrent" />

        <native:outlined-text-input
            label="{{ __('Company name') }}"
            native:model="name"
            autocapitalize="words"
            :is-error="($fieldErrors['name'] ?? '') !== ''"
            :supporting="$fieldErrors['name'] ?? ''"
        />

        <native:outlined-text-input label="{{ __('Industry') }}" native:model="industry" autocapitalize="words" />
        <native:outlined-text-input label="{{ __('My designation') }}" native:model="designation" autocapitalize="words" />

        <native:outlined-text-input
            label="{{ __('Started on (YYYY-MM-DD)') }}"
            native:model="startedOn"
            :is-error="($fieldErrors['started_on'] ?? '') !== ''"
            :supporting="$fieldErrors['started_on'] ?? ($isCurrent ? __('Optional') : __('Required: you no longer work here.'))"
        />

        @if (! $isCurrent)
            <native:outlined-text-input
                label="{{ __('Ended on (YYYY-MM-DD)') }}"
                native:model="endedOn"
                :is-error="($fieldErrors['ended_on'] ?? '') !== ''"
                :supporting="$fieldErrors['ended_on'] ?? __('Required: the date you left.')"
            />
        @endif

        <native:outlined-text-input
            label="{{ __('Website') }}"
            native:model="website"
            keyboard="url"
            autocapitalize="none"
            :is-error="($fieldErrors['website'] ?? '') !== ''"
            :supporting="$fieldErrors['website'] ?? __('Include https://')"
        />
        <native:outlined-text-input label="{{ __('Phone') }}" native:model="phone" keyboard="phone" />
        <native:outlined-text-input label="{{ __('Address') }}" native:model="address" multiline />

        <native:button label="{{ __('Save') }}" variant="primary" size="lg" :loading="$busy" @press="save" />
    </native:column>
</native:scroll-view>
