<native:column class="w-full h-full bg-theme-background">
    <native:refreshable @refresh="refresh" class="w-full flex-1">
        <native:column class="w-full p-4 gap-4">
            @if ($error !== '')
                <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
            @endif

            <native:text class="text-lg font-bold text-theme-on-surface">{{ __('My leaves') }}</native:text>

            @forelse ($requests as $r)
                <native:column class="w-full p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                    <native:text class="text-base font-bold text-theme-on-surface">{{ $r['type'] }}</native:text>
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $r['period'] }} - {{ $r['days'] }} {{ __('day(s)') }}</native:text>
                    @if ($r['reason'] !== '')
                        <native:text class="text-sm text-theme-on-surface-variant" :max-lines="2">{{ $r['reason'] }}</native:text>
                    @endif
                </native:column>
            @empty
                <native:text class="text-base text-theme-on-surface-variant">{{ __('You have not requested any leave yet.') }}</native:text>
            @endforelse
        </native:column>
    </native:refreshable>
</native:column>
