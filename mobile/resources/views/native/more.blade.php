<native:scroll-view class="w-full h-full bg-theme-background">
    <native:column class="w-full p-4 gap-4">
        @if ($error !== '')
            <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
        @endif

        <native:column class="w-full gap-1">
            <native:text class="text-2xl font-bold text-theme-on-surface">{{ $name }}</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">{{ $email }}</native:text>
        </native:column>

        @if (count($details) > 0)
            <native:column class="w-full p-4 gap-3 rounded-2xl bg-theme-surface-variant/40">
                @foreach ($details as $row)
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-sm text-theme-on-surface-variant">{{ $row['label'] }}</native:text>
                        <native:text class="text-base text-theme-on-surface">{{ $row['value'] }}</native:text>
                    </native:row>
                @endforeach
            </native:column>
        @endif

        <native:button label="Holidays" variant="secondary" @press="holidays" />
        <native:button label="My companies" variant="secondary" @press="companies" />
        <native:button label="Sign out" variant="destructive" :loading="$busy" @press="signOut" />
    </native:column>
</native:scroll-view>
