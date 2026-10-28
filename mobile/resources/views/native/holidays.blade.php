<native:column class="w-full h-full bg-theme-background">
    <native:row class="w-full p-4 items-center justify-between">
        <native:button label="{{ __('Prev') }}" variant="ghost" size="sm" @press="previousYear" />
        <native:text class="text-lg font-bold text-theme-on-surface">{{ $year }}</native:text>
        <native:button label="{{ __('Next') }}" variant="ghost" size="sm" @press="nextYear" />
    </native:row>

    <native:refreshable @refresh="refresh" class="w-full flex-1">
        <native:column class="w-full px-4 pb-4 gap-3">
            @if ($error !== '')
                <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
            @endif

            @forelse ($holidays as $h)
                <native:column class="w-full p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                    <native:text class="text-base font-bold text-theme-on-surface">{{ $h['name'] }}</native:text>
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $h['date'] }}{{ $h['optional'] ? ' - '.__('Optional') : '' }}</native:text>
                </native:column>
            @empty
                @if ($error === '')
                    <native:text class="text-base text-theme-on-surface-variant">{{ __('No holidays listed for :year.', ['year' => $year]) }}</native:text>
                @endif
            @endforelse
        </native:column>
    </native:refreshable>
</native:column>
