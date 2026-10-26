<native:column class="w-full h-full justify-center p-6 gap-4 bg-theme-background">
    <native:text class="text-3xl font-bold text-theme-on-surface">HR Attendance</native:text>
    <native:text class="text-base text-theme-on-surface-variant">Sign in with your employee account.</native:text>

    <native:outlined-text-input
        label="Email"
        placeholder="you@company.com"
        native:model="email"
        keyboard="email"
        autocapitalize="none"
        :is-error="($fieldErrors['email'] ?? '') !== ''"
        :supporting="$fieldErrors['email'] ?? ''"
    />

    <native:column class="relative w-full">
        <native:outlined-text-input
            label="Password"
            native:model="password"
            :secure="! $showPassword"
            :is-error="($fieldErrors['password'] ?? '') !== ''"
            :supporting="$fieldErrors['password'] ?? ''"
            @submit="signIn"
        />
        <native:pressable
            class="absolute right-[0] top-[26] w-[44] h-[44] items-center justify-center"
            a11y-label="{{ $showPassword ? 'Hide password' : 'Show password' }}"
            @press="toggleShowPassword"
        >
            <native:icon name="{{ $showPassword ? 'visibility_off' : 'visibility' }}" class="text-theme-on-surface-variant" />
        </native:pressable>
    </native:column>

    @if ($error !== '')
        <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
    @endif

    <native:button label="Sign in" :loading="$busy" @press="signIn" />
    <native:button label="Forgot password?" variant="ghost" @press="forgotPassword" />
</native:column>
