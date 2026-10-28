<native:column class="w-full h-full justify-center p-6 gap-4 bg-theme-background">
    <native:text class="text-3xl font-bold text-theme-on-surface">HR Attendance</native:text>
    <native:text class="text-base text-theme-on-surface-variant">Sign in with your employee account.</native:text>

    <native:outlined-text-input
        label="Email"
        placeholder="you@company.com"
        trailing-icon="email"
        native:model="email"
        keyboard="email"
        autocapitalize="none"
        :is-error="($fieldErrors['email'] ?? '') !== ''"
        :supporting="$fieldErrors['email'] ?? ''"
    />

    {{-- `revealable` is a native, built-in show/hide control (mobile-ui 0.8.0+) - it handles its own
         icon and positioning internally, so no custom overlay/centering markup is needed here. --}}
    <native:outlined-text-input
        label="Password"
        native:model="password"
        secure
        revealable
        :is-error="($fieldErrors['password'] ?? '') !== ''"
        :supporting="$fieldErrors['password'] ?? ''"
        @submit="signIn"
    />

    @if ($error !== '')
        <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
    @endif

    <native:button label="Sign in" :loading="$busy" @press="signIn" />
    <native:button label="Forgot password?" variant="ghost" @press="forgotPassword" />
    <native:button label="Create account" variant="ghost" @press="createAccount" />
</native:column>
