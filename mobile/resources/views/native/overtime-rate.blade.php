<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:column class="w-full p-4 gap-1 rounded-2xl bg-theme-surface-variant/40">
            <native:text class="text-sm text-theme-on-surface-variant">{{ __('Current rate') }}</native:text>
            @if ($current !== '')
                <native:text class="text-2xl font-bold text-theme-on-surface">{{ __(':rate per hour', ['rate' => $current]) }}</native:text>
            @else
                <native:text class="text-xl font-bold text-theme-on-surface">{{ __('Not set') }}</native:text>
            @endif
        </native:column>

        <native:outlined-text-input
            label="{{ __('New hourly rate') }}"
            native:model="hourlyRate"
            keyboard="decimal"
            :is-error="($fieldErrors['hourly_rate'] ?? '') !== ''"
            :supporting="$fieldErrors['hourly_rate'] ?? __('Applies to overtime you submit from now on.')"
        />

        <native:text class="text-sm text-theme-on-surface-variant">
            {{ __('Overtime you already submitted keeps the rate it was submitted with. Changing your rate never changes old amounts.') }}
        </native:text>

        <native:button label="{{ __('Save rate') }}" variant="primary" size="lg" :loading="$busy" @press="save" />

        @if (count($history) > 1)
            <native:text class="text-lg font-bold text-theme-on-surface">{{ __('Rate history') }}</native:text>
            <native:column class="w-full gap-2">
                @foreach ($history as $h)
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base font-bold text-theme-on-surface">{{ __(':rate per hour', ['rate' => $h['rate']]) }}</native:text>
                        <native:text class="text-sm text-theme-on-surface-variant">{{ __('from :date', ['date' => $h['since']]) }}</native:text>
                    </native:row>
                @endforeach
            </native:column>
        @endif
    </native:column>
</native:scroll-view>
