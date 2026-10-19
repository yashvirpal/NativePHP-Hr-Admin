<native:column class="w-full h-full bg-theme-background">
    <native:refreshable @refresh="refresh" class="w-full flex-1">
        <native:column class="w-full p-4 gap-4">
            @if ($error !== '')
                <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
            @endif

            <native:button label="Add company" variant="primary" @press="add" />

            @forelse ($companies as $c)
                <native:column class="w-full p-3 gap-1 rounded-2xl bg-theme-surface-variant/40">
                    <native:row class="w-full items-center justify-between">
                        <native:text class="text-base font-bold text-theme-on-surface">{{ $c['name'] }}</native:text>
                        @if ($c['is_current'])
                            <native:text class="text-sm font-bold text-theme-primary">Current</native:text>
                        @endif
                    </native:row>
                    <native:text class="text-sm text-theme-on-surface-variant">{{ $c['period'] }}</native:text>
                    @if ($c['designation'] !== '' || $c['industry'] !== '')
                        <native:text class="text-sm text-theme-on-surface-variant">{{ collect([$c['designation'], $c['industry']])->filter()->implode(' - ') }}</native:text>
                    @endif
                    <native:row class="w-full gap-2">
                        <native:button label="Edit" variant="ghost" size="sm" @press="edit({{ $c['id'] }})" />
                        <native:button label="Remove" variant="ghost" size="sm" @press="confirmRemove({{ $c['id'] }})" />
                    </native:row>
                </native:column>
            @empty
                <native:text class="text-base text-theme-on-surface-variant">No companies yet. Add your employer details.</native:text>
            @endforelse
        </native:column>
    </native:refreshable>
</native:column>
