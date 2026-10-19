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

    <native:outlined-text-input
        label="Password"
        native:model="password"
        secure
        :is-error="($fieldErrors['password'] ?? '') !== ''"
        :supporting="$fieldErrors['password'] ?? ''"
        @submit="signIn"
    />

    @if ($error !== '')
        <native:text class="text-sm text-theme-destructive">{{ $error }}</native:text>
    @endif

    <native:button label="Sign in" :loading="$busy" @press="signIn" />
</native:column>
