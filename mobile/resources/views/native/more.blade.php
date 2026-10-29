@php
    $languageVariant = fn (string $code) => $code === $locale ? 'primary' : 'secondary';
@endphp
<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-6">
        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:column class="w-full p-4 gap-4 rounded-2xl bg-theme-surface-variant/40">
            <native:column class="w-full gap-1">
                <native:text class="text-2xl font-bold text-theme-on-surface">{{ $name }}</native:text>
                <native:text class="text-sm text-theme-on-surface-variant">{{ $email }}</native:text>
            </native:column>

            @if (count($details) > 0)
                <native:column class="w-full gap-3">
                    @foreach ($details as $row)
                        <native:row class="w-full items-center justify-between">
                            <native:text class="text-sm text-theme-on-surface-variant">{{ $row['label'] }}</native:text>
                            <native:text class="text-base text-theme-on-surface">{{ $row['value'] }}</native:text>
                        </native:row>
                    @endforeach
                </native:column>
            @endif
        </native:column>

        <native:column class="w-full gap-2">
            <native:text class="text-xs font-bold text-theme-on-surface-variant">{{ __('Settings') }}</native:text>
            <native:column class="w-full p-4 gap-3 rounded-2xl bg-theme-surface-variant/40">
                <native:text class="text-sm text-theme-on-surface-variant">{{ __('Language') }}</native:text>
                <native:row class="w-full gap-2">
                    <native:button label="English" variant="{{ $languageVariant('en') }}" class="flex-1" @press="setLanguage('en')" />
                    <native:button label="हिन्दी" variant="{{ $languageVariant('hi') }}" class="flex-1" @press="setLanguage('hi')" />
                </native:row>
            </native:column>
        </native:column>

        <native:column class="w-full gap-2">
            <native:text class="text-xs font-bold text-theme-on-surface-variant">{{ __('More') }}</native:text>
            <native:column class="w-full gap-2">
                <native:button label="{{ __('Earning') }}" variant="secondary" @press="earning" />
                <native:button label="{{ __('Holidays') }}" variant="secondary" @press="holidays" />
                <native:button label="{{ __('My companies') }}" variant="secondary" @press="companies" />
            </native:column>
        </native:column>

        <native:button label="{{ __('Sign out') }}" variant="destructive" :loading="$busy" @press="signOut" />
    </native:column>
</native:scroll-view>
